<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_update_invoice($order) {

  if ( ! count($order)) return;

  export_gd_delete_invoice($order); //Avoid having multiple versions of same invoice

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Invoice Template v1',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME,
    'order'    => $order
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  $invoice_doc_id = json_decode($result, true);

  log_error("export_gd_update_invoice", ['file' => $args['file'], 'result' => $result, 'sql' => $sql]);

  return $invoice_doc_id;
}

//Cannot delete (with this account) once published
function export_gd_publish_invoice($order) {

 if ( ! $order[0]['tracking_number']) return; //only publish if tracking number since we can't delete extra after this point

  $args = [
    'method'   => 'publishFile',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME,
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_notice("export_gd_publish_invoice", get_defined_vars());
}

function export_gd_delete_invoice($order) {

  $args = [
    'method'   => 'removeFiles',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_info("export_gd_delete_invoice", get_defined_vars());
}
