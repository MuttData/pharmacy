<?php
require_once 'exports/export_cp_rxs.php';
require_once 'helpers/helper_full_fields.php';

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

function load_full_patient($partial, $mysql, $overwrite_rx_messages = false) {

  if ( ! isset($partial['patient_id_cp'])) {
    log_error('ERROR! get_full_patient: was not given a patient_id_cp', $partial);
    return;
  }

  $patient = get_full_patient($mysql, $partial['patient_id_cp']);

  if ( ! $patient) {
    log_error("ERROR! load_full_patient: no active patient with id:$partial[patient_id_cp]. Deceased or Inactive Patient with Rxs", get_defined_vars());
    return;
  }

  GPLog::notice(
    "helper_full_patient before helper_full_fields",
    [
      "patient_id_cp" => $patient[0]['patient_id_cp'],
      "patient_id_wc" => $patient[0]['patient_id_wc'],
      "overwrite_rx_messages"       => $overwrite_rx_messages,
      "patient"       => $patient
    ]
  );

  $patient = add_full_fields($patient, $mysql, $overwrite_rx_messages);

  GPLog::notice(
    "helper_full_patient after helper_full_fields",
    [
      "patient_id_cp" => $patient[0]['patient_id_cp'],
      "patient_id_wc" => $patient[0]['patient_id_wc'],
      "overwrite_rx_messages"       => $overwrite_rx_messages,
      "patient"       => $patient
    ]
  );

  usort($patient, 'sort_drugs_by_name'); //Put Rxs in order (with Rx_Source) at the top
  $patient = add_sig_differences($patient);

  return $patient;
}

function get_full_patient($mysql, $patient_id_cp) {

  $month_interval = 6;

  $sql = "
    SELECT
      *,
      gp_rxs_grouped.*, -- Need to put this first based on how we are joining, but make sure these grouped fields overwrite their single equivalents
      gp_patients.patient_id_cp,
      0 as is_order,
      1 as is_patient,
      0 as is_item
    FROM
      gp_patients
    LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
      gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
    LEFT JOIN gp_rxs_single ON -- Needed to know qty_left for sync-to-date
      gp_rxs_grouped.best_rx_number = gp_rxs_single.rx_number
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic -- this is for the helper_days_and_message msgs for unordered drugs
    LEFT JOIN gp_order_items ON -- choice to show any order_item from this rx_group and not just if this specific rx matches
      gp_order_items.rx_dispensed_id IS NULL AND
      rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
    LEFT JOIN gp_orders ON -- ORDER MAY HAVE NOT BEEN ADDED YET
      gp_orders.invoice_number = gp_order_items.invoice_number
    WHERE
      gp_patients.patient_id_cp = $patient_id_cp
  ";

  $patient = $mysql->run($sql)[0];

  if ($patient AND @$patient[0]['patient_id_cp'])
    return $patient;
}

function add_sig_differences($patient) {

  $drug_names = []; //Append qty_per_day if multiple of same strength, do this after sorting

  foreach($patient as $i => $item) {

    if ( ! @$item['drug']) {
      log_error('add_sig_differences: why no "drug" property set?', ['item' => $item, 'patient' => $patient]);
      continue;
    }

    if (isset($drug_names[$item['drug']])) {
      $patient[$i]['drug'] .= ' ('.( (float) $item['sig_qty_per_day'] ).' per day)';
    } else {
      $drug_names[$item['drug']] = $item['sig_qty_per_day'];
    }
  }

  return $patient;
}

function sort_drugs_by_name($a, $b) {
  return strcmp($a['drug'].$a['sig_qty_per_day'], $b['drug'].$b['sig_qty_per_day']);
}
