<?php

require_once 'keys.php';

use Illuminate\Database\Capsule\Manager as Capsule;

if (!isset($lv_model_capsule)
    || !($lv_model_capsule instanceof Illuminate\Database\Capsule\Manager)) {
    $lv_model_capsule = new Capsule;

    $lv_model_capsule->addConnection(
        [
            'driver'    => 'mysql',
            'host'      => MYSQL_WC_IP,
            'database'  => 'goodpill',
            'username'  => MYSQL_WC_USER,
            'password'  => MYSQL_WC_PWD
        ],
        'default'
    );

    $lv_model_capsule->addConnection(
        [
            'driver'    => 'sqlsrv',
            'host'      => MSSQL_CP_IP,
            'database'  => MSSQL_CP_DB,
            'username'  => MSSQL_CP_USER,
            'password'  => MSSQL_CP_PWD
        ],
        'carepoint'
    );

    // Make this Capsule instance available globally via static methods... (optional)
    $lv_model_capsule->setAsGlobal();

    // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
    $lv_model_capsule->bootEloquent();
}
