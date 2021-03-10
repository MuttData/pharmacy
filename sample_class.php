<?php
use GoodPill\DataModels\GoodPillPatient;
use GoodPill\DataModels\GoodPillPatients;

$patient  = new GoodPillPatient(['patient_id_cp' => 6585]);
$patients = new GoodPillPatients();
$patients->getTenPatients();

foreach ($patients as $patient) {
    echo $patient->patient_id_cp . "\n";
}
