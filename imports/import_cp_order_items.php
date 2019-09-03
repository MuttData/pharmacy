<?php

function import_cp_order_items() {

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();

  $order_items = $mssql->run("

    SELECT
      csomline.order_id+2 as invoice_number,
  		COALESCE(
        MIN(CASE
          WHEN refills_left > .1 THEN script_no
          ELSE NULL END
        ),
        MAX(script_no)
      ) as rx_number, --If multiple of same drug in order, pick the oldest one with refills.  If none have refills use the newest. See dbo.csuser for user ids
      MAX(disp.rxdisp_id) as rx_dispensed_id, --Hacky, most recent line item might not line up with the rx number we are filling
      MAX(dispense_qty) as qty_dispensed_actual,
      MAX(disp_days_supply) as days_dispensed_actual,
      MAX(add_date) as item_date_added,
      MAX(CASE
        WHEN CsOmLine.add_user_id = 901  THEN 'HL7'
        WHEN CsOmLine.add_user_id = 902  THEN 'AUT'
        WHEN CsOmLine.add_user_id = 1002 THEN 'AUTOFILL'
        WHEN CsOmLine.add_user_id = 1003 THEN 'WEBFORM'
        WHEN CsOmLine.add_user_id = 1004 THEN 'REFILL REQUEST'
        ELSE 'MANUAL' END
      ) as item_added_by -- from csuser
  	FROM csomline
  	JOIN cprx ON cprx.rx_id = csomline.rx_id
    LEFT OUTER JOIN cprx_disp disp ON csomline.rxdisp_id > 0 AND disp.rxdisp_id = csomline.rxdisp_id
    WHERE line_state_cn < 50 -- Unshipped only to cut down volume. Will qty and days be set before this?
    GROUP BY csomline.order_id, (CASE WHEN gcn_seqno > 0 THEN gcn_seqno ELSE script_no END) --This is because of Orders like 8660 where we had 4 duplicate Citalopram 40mg.  Two that were from Refills, One Denied Surescript Request, and One new Surescript.  We are only going to send one GCN so don't list it multiple times
  ");

  $keys = result_map($order_items[0]);

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_order_items_cp');

  $mysql->run("INSERT INTO gp_order_items_cp $keys VALUES ".$order_items[0]);
}
