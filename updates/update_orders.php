<?php

require_once 'changes/changes_to_orders.php';

function update_orders() {

  $changes = changes_to_orders("gp_orders_cp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "
  update_orders: $count_deleted deleted, $count_created created, $count_updated updated. ";

  if ($count_deleted+$count_created+$count_updated)
    log_info($message.print_r($changes, true));

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  function get_full_order($order, $mysql) {

    $sql = "
      SELECT *
      FROM
        gp_orders
      JOIN gp_patients ON
        gp_patients.patient_id_cp = gp_orders.patient_id_cp
      LEFT JOIN gp_order_items ON -- Orders may not have any items
        gp_orders.invoice_number = gp_order_items.invoice_number
      LEFT JOIN gp_rxs_grouped ON
        rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
      WHERE
        gp_orders.invoice_number = $order[invoice_number]
    ";

    $order = $mysql->run($sql)[0];

    log_info("
    Order Before: $sql
    ".print_r($order, true));

    if ($order AND $order[0]['invoice_number']) {

      $target_date = get_sync_to_date($order)
      $order  = set_sync_to_date($order, $target_date, $mysql);

      $update = get_payment($order);
      $order  = set_payment($order, $update, $mysql);

    } else {
      log_info('set_sync_to_date error. no invoice number '.print_r($order, true).print_r($update, true));
    }

    log_info("
    Order After: $sql
    ".print_r($order, true));

    return $order;
  }

  //Group all drugs by their next fill date and get the most popular date
  function get_sync_to_date($order) {

    $sync_dates = [];
    foreach ($order as $item) {
      if (isset($sync_dates[$item['refill_date_next']]))
        $sync_dates[$item['refill_date_next']]++
      else
        $sync_dates[$item['refill_date_next']] = 0
    }

    $target_date  = null;
    $target_count = null;
    foreach($sync_dates as $date => $count) {
      if ($count > $target_count) {
        $target_count = $count;
        $target_date = $date;
      }
      else if ($count == $target_count AND $date > $target_date) { //In case of tie, longest date wins
        $target_date = $date;
      }
    }

    return $target_date;
  }

  //Sync any drug that has days to the new refill date
  function set_sync_to_date($order, $target_date, $mysql) {

    foreach($order as $i => $item) {

      if ($item['days_dispensed_default'] == 0) continue; //Don't add them to order if they are not already in it

      $days_extra  = (strtotime($target_date) - strtotime($item['refill_date_next']))/60/60/24;
      $days_synced = $item['days_dispensed_default'] + $days_extra;

      if ($days_synced >= 15 AND $days_synced <= 120) { //Limits to the amounts by which we are willing sync

        $order[$i]['refill_date_target']     = $target_date;
        $order[$i]['days_dispensed_default'] = $days_synced;

        $sql = "
          UPDATE
            gp_order_items =
          SET
            refill_date_target     = $target_date,
            days_dispensed_default = $days_synced
          WHERE
            rx_number = $item['rx_number']
        ";

        $mysql->run($sql);
      }

      export_v2_add_pended($order[$i]); //Days should be finalized now
    }

    return $order;
  }

  function get_payment($order) {

    $update = [];

    $update['payment_total'] = 0;

    foreach($order as $i => $item)
      $update['payment_total'] += $item['price_dispensed_actual'] ?: $item['price_dispensed_default'];

    //Defaults
    $update['payment_fee'] = $order[0]['refills_used'] ? $update['payment_total'] : PAYMENT_TOTAL_NEW_PATIENT;
    $update['payment_due'] = $update['payment_fee'];
    $update['payment_date_autopay'] = 'NULL';

    if ($order[0]['payment_method'] == PAYMENT_METHOD['COUPON']) {
      $update['payment_fee'] = $update['payment_total'];
      $update['payment_due'] = 0;
    }
    else if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY']) {
      $start = date('m/01', strtotime('+ 1 month'));
      $stop  = date('m/07/y', strtotime('+ 1 month'));

      $update['payment_date_autopay'] = "'$start - $stop'";
      $update['payment_due'] = 0;
    }

    return $update;
  }

  function set_payment($order, $update, $mysql) {

    $sql = "
      UPDATE
        gp_orders
      SET
        payment_total = $update[payment_total],
        payment_fee   = $update[payment_fee],
        payment_due   = $update[payment_due],
        payment_date_autopay = $update[payment_date_autopay]
      WHERE
        invoice_number = {$order[0]['invoice_number']}
    ";

    $mysql->run($sql);

    foreach($order as $i => $item)
      $order[$i] = $update + $item;

    return $order;
  }

  //If just added to CP Order we need to
  //  - Find out any other rxs need to be added
  //  - Update invoice
  //  - Update wc order count/total
  foreach($changes['created'] as $created) {

    $order = get_full_order($created, $mysql);

    export_cp_add_more_items($order); //this will cause another update and we will end back in this loop

    export_gd_update_invoice($order);

    export_wc_update_order($order);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    $order = get_full_order($deleted, $mysql);

    export_gd_update_invoice($order);

    export_wc_update_order($order);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    log_info("Order: ".print_r(changed_fields($updated), true).print_r($updated, true));

    $order = get_full_order($updated, $mysql);
    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    export_gd_update_invoice($order);

    export_wc_update_order($order);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //TODO Differentiate between actual order that are to be sent out and
  // - Ones that were faxed/called in but not due yet
  // - Ones that were surescripted in but not due yet  [order_status] => Surescripts Fill
  // [order_status] => Surescripts Authorization Approved

  //TODO Upsert WooCommerce Order Status, Order Tracking

  //TODO Upsert Salseforce Order Status, Order Tracking

  //TODO Remove Delete Orders

}
