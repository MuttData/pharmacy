<?php
//order created -> add any additional rxs to order -> import order items -> sync all drugs in order

function get_full_order($order, $mysql) {

  //gp_orders.invoice_number and other fields at end because otherwise potentially null gp_order_items.invoice_number will override gp_orders.invoice_number
  $sql = "
    SELECT
      *,
      gp_orders.invoice_number,
      gp_rxs_grouped.* -- Need to put this first based on how we are joining, but make sure these grouped fields overwrite their single equivalents
    FROM
      gp_orders
    JOIN gp_patients ON
      gp_patients.patient_id_cp = gp_orders.patient_id_cp
    LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
      gp_rxs_grouped.patient_id_cp = gp_orders.patient_id_cp
    LEFT JOIN gp_order_items ON
      gp_order_items.invoice_number = gp_orders.invoice_number AND rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%') -- In case the rx is added in a different orders
    LEFT JOIN gp_rxs_single ON -- Needed to know qty_left for sync-to-date
      gp_order_items.rx_number = gp_rxs_single.rx_number
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic -- this is for the helper_days_dispensed msgs for unordered drugs
    WHERE
      gp_orders.invoice_number = $order[invoice_number]
  ";

  $order = $mysql->run($sql)[0];

  if ( ! $order OR ! $order[0]['invoice_number'])
    return log_error('ERROR! get_full_order: no invoice number', get_defined_vars());

  $order = add_wc_status_to_order($order);
  $order = add_gd_fields_to_order($order);

  return $order;
}

function add_wc_status_to_order($order) {

  $order_stage_wc = get_order_stage_wc($order);

  foreach($order as $i => $item)
    $order[$i]['order_stage_wc'] = $order_stage_wc;

  return $order;
}

//Simplify GDoc Invoice Logic by combining _actual
function add_gd_fields_to_order($order) {

  //Consolidate default and actual suffixes to avoid conditional overload in the invoice template and redundant code within communications
  foreach($order as $i => $item) {
    $order[$i]['drug'] = $item['drug_name'] ?: $item['drug_generic'];
    $order[$i]['days_dispensed'] = $item['days_dispensed_actual'] ?: $item['days_dispensed_default'];
    $order[$i]['payment_method'] = $item['payment_method_actual'] ?: $item['payment_method_default'];

    if ( ! $item['item_date_added']) { //if not syncing to order lets provide a reason why we are not filling
      $message = get_days_default($item)[1];
      $order[$i]['item_message_key']  = array_search($message, RX_MESSAGE);
      $order[$i]['item_message_text'] = message_text($message, $item);
    }

    $deduct_refill = $order[$i]['days_dispensed'] ? 1 : 0; //We want invoice to show refills after they are dispensed assuming we dispense items currently in order

    $order[$i]['qty_dispensed'] = (float) ($item['qty_dispensed_actual'] ?: $item['qty_dispensed_default']); //cast to float to get rid of .000 decimal
    $order[$i]['refills_total'] = (float) ($item['refills_total_actual'] ?: $item['refills_total_default'] - $deduct_refill);
    $order[$i]['price_dispensed'] = (float) ($item['price_dispensed_actual'] ?: ($item['price_dispensed_default'] ?: 0));
  }

  usort($order, 'sort_order_by_day');

  //log_info('get_full_order', get_defined_vars());

  return $order;
}

function sort_order_by_day($a, $b) {
  if ($b['days_dispensed'] > 0 AND $a['days_dispensed'] == 0) return 1;
  if ($a['days_dispensed'] > 0 AND $b['days_dispensed'] == 0) return -1;
  return strcmp($a['item_message_text'].$a['drug'], $b['item_message_text'].$b['drug']);
}

/*
const ORDER_STATUS_WC = [

  'late-mail-pay'              => 'Shipped (Mail Payment Not Made)',
  'late-auto-pay-card-missing' => 'Shipped (Autopay Card Missing)',
  'late-auto-pay-card-expired' => 'Shipped (Autopay Card Expired)',
  'late-auto-pay-card-failed'  => 'Shipped (Autopay Card Failed)',
  'late-online-pay'            => 'Shipped (Online Payment Not Made)',
  'late-plan-approved'         => 'Shipped (Payment Plan Approved)',

  'completed-card-pay'    => 'Completed (Paid by Card)',
  'completed-mail-pay'    => 'Completed (Paid by Mail)',
  'completed-finaid'      => 'Completed (Financial Aid)',
  'completed-fee-waived'  => 'Completed (Fee Waived)',
  'completed-clinic-pay'  => 'Completed (Paid by Clinic)',
  'completed-auto-pay'    => 'Completed (Paid by Autopay)',
  'completed-refused-pay' => 'Completed (Refused to Pay)',

  'returned-usps'         => 'Returned (USPS)',
  'returned-customer'     => 'Returned (Customer)'
];
*/
function get_order_stage_wc($order) {

  //Anything past shipped we just have to rely on WC
  if (in_array(explode('-', $order[0]['order_stage_wc'])[0], ['late', 'done', 'return']))
    return $order[0]['order_stage_wc'];

  /*
  'confirm-*' means no drugs in the order yet so check for count_items
  order_source: NULL, O Refills, Auto Refill v2, Webform eRX, Webform eRX Note, Webform Refill, Webform Refill Note, Webform Transfer, Webform Transfer Note
  */

  $count_filled = $order[0]['order_stage_cp'] == 'Shipped' ? $order[0]['count_items'] : $order[0]['count_filled'];

  if ( ! $count_filled AND ! $order[0]['order_source'])
    return 'confirm-new-rx'; //New SureScript(s) that we are not filling

  if ( ! $count_filled AND in_array($order[0]['order_source'], ['Webform Transfer', 'Webform Transfer Note']))
    return 'confirm-transfer';

  if ( ! $count_filled AND in_array($order[0]['order_source'], ['Webform Refill', 'Webform Refill Note']))
    return 'confirm-refill';

  if ( ! $count_filled AND in_array($order[0]['order_source'], ['Auto Refill v2', 'O Refills']))
    return 'confirm-autofill';

  if ( ! $count_filled) {
    log_error('get_order_stage_wc error: confirm-* unknown order_source', get_defined_vars());
    return 'on-hold';
  }

  /*
  'prepare-*' means drugs are in the order but order is not yet shipped
  rx_source: Fax, Pharmacy, Phone, Prescription, SureScripts
  */

  if ( ! $order[0]['tracking_number'] AND in_array($order[0]['order_source'], ['Webform Refill', 'Webform Refill Note', 'Auto Refill v2', 'O Refills']))
    return 'prepare-refill';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'SureScripts')
    return 'prepare-erx';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Fax')
    return 'prepare-fax';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Pharmacy')
    return 'prepare-transfer';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Phone')
    return 'prepare-phone';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Prescription')
    return 'prepare-mail';

  if ( ! $order[0]['tracking_number']) {
    log_error('get_order_stage_wc error: prepare-* unknown rx_source', get_defined_vars());
    return 'on-hold';
  }

  /*

    const PAYMENT_METHOD = [
      'COUPON'       => 'coupon',
      'MAIL'         => 'cheque',
      'ONLINE'       => 'cash',
      'AUTOPAY'      => 'stripe',
      'CARD EXPIRED' => 'stripe-card-expired'
    ];

    'shipped-*' means order was shipped but not yet paid.  We have to go by order_stage_wc here
    rx_source: Fax, Pharmacy, Phone, Prescription, SureScripts
  */

  if ($order[0]['order_stage_wc'] == 'shipped-partial-pay')
    return 'shipped-part-pay';

  if ($order[0]['payment_method_actual'] == PAYMENT_METHOD['MAIL'])
    return 'shipped-mail-pay';

  if ($order[0]['payment_method_actual'] == PAYMENT_METHOD['AUTOPAY'])
    return 'shipped-auto-pay';

  if ($order[0]['payment_method_actual'] == PAYMENT_METHOD['ONLINE'])
    return 'shipped-web-pay';

  if ($order[0]['payment_method_default'] == PAYMENT_METHOD['MAIL'])
    return 'shipped-mail-pay';

  if ($order[0]['payment_method_default'] == PAYMENT_METHOD['AUTOPAY'])
    return 'shipped-auto-pay';

  if ($order[0]['payment_method_default'] == PAYMENT_METHOD['ONLINE'])
    return 'shipped-web-pay';

  if ($order[0]['payment_method_default'] == PAYMENT_METHOD['COUPON'])
    return 'done-clinic-pay';

  log_error('get_order_stage_wc error: shipped-* unknown payment_method', get_defined_vars());
  return $order[0]['order_stage_wc'];
}
