<?php

require_once dirname(__DIR__) . '/keys.php';
require_once 'mssql_new_drivers.php';

class Mssql_Cp extends Mssql_New_Drivers {
   function __construct(){
     parent::__construct(GUARDIAN_IP, GUARDIAN_ID, GUARDIAN_PW, 'cph');
   }
}
