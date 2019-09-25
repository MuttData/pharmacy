<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_v2_drugs() {

  $mysql = new Mysql_Wc();

  $context = stream_context_create([
      "http" => [
          "header" => "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD)
      ]
  ]);

  $order = file_get_contents(V2_IP.'/account/8889875187', false, $context);
  $order = json_decode($order, true);

  $drugs = file_get_contents(V2_IP.'/drug/_design/by-generic-gsns/_view/by-generic-gsns?group_level=3', false, $context);
  $drugs = json_decode($drugs, true);

  log("
  import_v2_drugs: rows ".count($drugs['rows']));

  $vals = [];
  foreach($drugs['rows'] as $row) {

    list($drug_generic, $drug_gsns, $drug_brand) = $row['key'];
    list($price_goodrx, $price_nadac, $price_retail) = $row['value'];
    $o = $order['ordered'];
    $d = $order['default'];

    $val = [
      'drug_generic'      => "'$drug_generic'",
      'drug_brand'        => clean_val($drug_brand),
      'drug_gsns'         => $drug_gsns ? "',$drug_gsns,'" : 'NULL',   //Enclose with ,, so we can do DRUG_GSNS LIKE '%,GSN,%' and still match first and list in list
      'drug_ordered'      => isset($o[$drug_generic]) ? 1 : 'NULL',
      'price30'           => clean_val($o[$drug_generic]['price30'], $d['price30']),
      'price90'           => clean_val($o[$drug_generic]['price90'], $d['price90']),
      'qty_repack'        => clean_val($o[$drug_generic]['repackQty'], $d['repackQty']),
      'qty_min'           => clean_val($o[$drug_generic]['minQty'], $d['minQty']),
      'days_min'          => clean_val($o[$drug_generic]['minDays'], $d['minDays']),
      'max_inventory'     => clean_val($o[$drug_generic]['maxInventory'], $d['maxInventory']),
      'message_display'   => clean_val($o[$drug_generic]['displayMessage']),
      'message_verified'  => clean_val($o[$drug_generic]['verifiedMessage']),
      'message_destroyed' => clean_val($o[$drug_generic]['destroyedMessage']),
      'price_goodrx'      => "'".($price_goodrx['sum']/$price_goodrx['count'])."'",
      'price_nadac'       => "'".($price_nadac['sum']/$price_nadac['count'])."'",
      'price_retail'      => "'".($price_retail['sum']/$price_retail['count'])."'",
      'count_ndcs'        => "'$price_goodrx[count]'"
    ];

    $vals[] = '('.implode(', ', $val).')';
  }

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_drugs_v2');

  $mysql->run("
    INSERT INTO gp_drugs_v2 (".implode(', ', array_keys($val)).") VALUES ".implode(', ', $vals));
}
