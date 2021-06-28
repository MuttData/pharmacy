<?php

ini_set('memory_limit', '512M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';
require_once 'helpers/helper_laravel.php';

use GoodPill\Utilities\SigParser;

$correct_pairs = [
    "1 tablet by mouth daily; TAKE ONE TABLET BY MOUTH ONCE DAILY" => [
        "drug_name" => "PREDNISONE 10MG TAB",
        "expected" => [
            "sig_qty" => DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tab 5 xdaily on week 1 ,1 tab 4 xdaily on week 2, 1 tab 3 xdaily on week 3, 1 tab 2 xdaily on week 4, then 1 tab daily on week 5" => [
        "drug_name" => "PREDNISONE 10MG TAB",
        "expected" => [
            "sig_qty" => 5*7 + 4*7 + 3*7 + 2*7 + 1*7,
            "sig_days" => 35,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 7.5mg three days per week (M,W,F) and 5mg four days per week OR as directed per Coumadin Clinic." => [
        "drug_name" => "WARFARIN SODIUM 5MG TAB",
        "expected" => [
            "sig_qty" => (4.5 + 4) * DAYS_STD / 7,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "1 tablet by mouth every 8 hours as needed for Blood Pressure greater than 140/90" => [
        "drug_name" => "CLONIDINE 0.1MG TAB",
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 4 tablets by mouth 3 times a day with meal and 2 tablets twice a day with snacks" => [
        "expected" => [
            "sig_qty" => 16 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablets"
        ]
    ],
    "1 capsule by mouth at bedtime for 1 week then 2 capsules at bedtime" => [
        "expected" => [
            "sig_qty" => 7 + 2 * (DAYS_STD - 7),
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "Take 1 tablet (10 mg) by mouth 2 times per day for 21 days with food, then increase to 2 tablets (20 mg) BID" => [
        "expected" => [
            "sig_qty" => 2 * 21 + 4 * (DAYS_STD - 21),
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    // Outlier
    // "Take 2 tablets in the morning and 1 at noon and 1 at supper" => [
    //     "sig_qty" => 4 * DAYS_STD,
    //     "sig_days" => DAYS_STD,
    //     "sig_unit" => "tablets"
    // ],
    "Take 1 capsule(s) 3 times a day by oral route with meals for 7 days." => [
        "expected" => [
            "sig_qty" => 21,
            "sig_days" => 7,
            "sig_unit" => "capsule"
        ]
    ],
    // Outlier
    "1 capsule by mouth every day for 7 days then continue on with 60mg capsuled" => [
        "drug_name" => "DULOXETINE DR 30MG CAP",
        "expected" => [
            "sig_qty" => 7, // We asume that the 60mg is another drug, so it doesn't count for the final qty
            "sig_days" => 7, // Only count the days for "this" drug
            "sig_unit" => "CAP"
        ]
    ],
    "Take 1 tablet by mouth 1 time daily then, if no side effects, increase to 1 tablet 2 times daily with meals" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,    // Uncertain
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "take 10 tablets (40MG total)  by ORAL route   every day for  4 days only" => [
        "expected" => [
            "sig_qty" => 40,
            "sig_days" => 4,
            "sig_unit" => "tablets"
        ]
    ],
    "Take 1 tablet (12.5 mg) by mouth per day in the morning" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 capsule by mouth 30 minutes after the same meal each day" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule",
        ]
    ],
    "1 capsule by mouth every day (Start after finishing 30mg capsules first)" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "1 capsule by mouth twice a day" => [
        "expected" => [
            "sig_qty" => 2 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "1 capsule once daily 30 minutes after the same meal each day" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsule"
        ]
    ],
    "1 tablet (5 mg total) by PEG Tube route 2 (two) times a day" => [
        "expected" => [
            "sig_qty" => 2 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth  every morning" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime as directed" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime as needed" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth at bedtime mood" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth daily" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth day" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "1 tablet by mouth every 8 hours" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 5 tablets by mouth once  at bedtime" => [
        "expected" => [
            "sig_qty" => 5 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablets"
        ]
    ],
    "Take  1 TO 2 capsules by mouth 3 times a day as needed FOR NERVE PAINS" => [
        "expected" => [
            "sig_qty" => 6 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "capsules"
        ]
    ],
    // Outlier for sig_unit
    // "Take 1 capsule once a day take along with 40mg for total of 60mg" => [
    //     "drug_name" => "PROZAC 20MG PULVULE",
    //     "expected" => [
    //         "sig_qty" => 3 * DAYS_STD,
    //         "sig_unit" => "PULVULE"
    //         "sig_days" => DAYS_STD,
    //     ]
    // ],
    "1 capsule as needed every 6 hrs Orally 30 day(s)" => [
        "expected" => [
            "sig_qty" => 120,
            "sig_days" => 30,
            "sig_unit" => "capsule"
        ]
    ],
    "1 tablet every 6 to 8 hours as needed Orally 30 day(s)" => [
        "expected" => [
            "sig_qty" => 90, // Taking the max value for dosages and frequencies gives the best results
            "sig_days" => 30,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 1 tablet by mouth every night as needed sleep" => [
        "expected" => [
            "sig_qty" => 1 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 1 tablet (800 mg) by oral route 3 times per day with food as needed for pain" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],
    "Take 1 tablet by mouth 3 times a day as needed" => [
        "expected" => [
            "sig_qty" => 3 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "tablet"
        ]
    ],

    // TODO: For now taking the unit which is in the sig itself
    // Unless it's vials, in which case parse it to ml.
    "Use 1 vial via nebulizer every 6 hours" => [
        "drug_name" => "ALBUTEROL SUL 2.5MG/3ML SOLN",
        "expected" => [
            "sig_qty" => 12 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "ML"
        ]
    ],
    "Take 20 mEq by mouth 2 (two) times a day." => [
        "drug_name" => "POTASSIUM CL ER 20MEQ TAB",
        "expected" => [
            "sig_qty" => 2 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 to 3 tablets by mouth  weekly as directed as needed" => [
        "drug_name" => "FUROSEMIDE 20MG TAB",
        "expected" => [
            "sig_qty" => 3 / 7 * DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "1 oral daily" => [
        "drug_name" => "FUROSEMIDE 20MG TAB",
        "expected" => [
            "sig_qty" => DAYS_STD,
            "sig_days" => DAYS_STD,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth 3 times a day as needed for dizziness or nausea for up to 10 days." => [
        "drug_name" => "FUROSEMIDE 20MG TAB",
        "expected" => [
            "sig_qty" => 30,
            "sig_days" => 10,
            "sig_unit" => "TAB"
        ]
    ],
    "Place 1 tablet under the tongue every 6  hours as needed for nausea or vomiting for up to 7 days." => [
        "drug_name" => "FUROSEMIDE 20MG TAB",
        "expected" => [
            "sig_qty" => 28,
            "sig_days" => 7,
            "sig_unit" => "TAB"
        ]
    ],
    "100 MG PO BID" => [
        "drug_name" => "METOPROLOL SUCC ER 100MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "50 MG PO BID" => [
        "drug_name" => "METOPROLOL SUCC ER 50MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth 3 times a day" => [
        "drug_name" => "PROPRANOLOL 10MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth twice a day" => [
        "drug_name" => "HYDRALAZINE 100MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth 3 times a day" => [
        "drug_name" => "KEPPRA 1,000MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 2 capsules by mouth 3 times a day" => [
        "drug_name" => "PHENYTOIN SOD EXT 100MG CAP",
        "expected" => [
            "sig_qty" => 540.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "CAP"
        ]
    ],
    "1 capsule as needed every 6 hrs Orally 90 days" => [
        "drug_name" => "VISTARIL 50MG CAP",
        "expected" => [
            "sig_qty" => 360.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "CAP"
        ]
    ],
    "1 tablet at bedtime Once a day Orally 90 days" => [
        "drug_name" => "AMITRIPTYLINE 25MG TAB",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1& 1/2 tablet by mouth twice a day" => [
        "drug_name" => "OXCARBAZEPINE 300MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "TAKE 2 CAPSULES BY MOUTH THREE TIMES A DAY" => [
        "drug_name" => "GABAPENTIN 400MG CAP",
        "expected" => [
            "sig_qty" => 540.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "CAP"
        ]
    ],
    "1 tab PO BID" => [
        "drug_name" => "ACID REDUCER 20MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 3 tablets by mouth in the morning" => [
        "drug_name" => "BUPROPION SR 100MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 2 tablets (250 mcg total) by mouth daily. Replaces 112 mcg" => [
        "drug_name" => "LEVOTHYROXINE 125MCG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 3 tablet by mouth at bedtime as needed take 300 mg at bedtime." => [
        "drug_name" => "TRAZODONE 100MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "1.5 tab(s) PO BID" => [
        "drug_name" => "TOPROL XL 50MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth in the morning and take 1/2 tablet by mouth at bedtime" => [
        "drug_name" => "LISINOPRIL 20MG TAB",
        "expected" => [
            "sig_qty" => 135.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tab bid" => [
        "drug_name" => "FERROUS SULF EC 324MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "TAKE ONE TABLET BY MOUTH ONE TIME DAILY" => [
        "drug_name" => "LISINOPRIL 10MG TAB",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "1 tab(s) PO BID,x90 day(s)" => [
        "drug_name" => "LAMICTAL 100MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth once a day Take with 60 mg ER tablet" => [
        "drug_name" => "NIFEDIPINE ER 30MG TAB",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth every 12 hours FOR 10 DAYS" => [
        "drug_name" => "DOXYCYCLINE HYCLATE 100MG TAB",
        "expected" => [
            "sig_qty" => 20.0000000,
            "sig_days" => 10.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth twice a day 30 minutes before meals" => [
        "drug_name" => "GLIPIZIDE 5MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 capsule by mouth once daily along with 150mg capsules" => [
        "drug_name" => "VENLAFAXINE ER 75MG CAP",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "CAP"
        ]
    ],
    "Take 1 tablet by mouth once daily along with 200mcg" => [
        "drug_name" => "LEVOTHYROXINE 75MCG TAB",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "1 tablet TID PRN Orally 90" => [
        "drug_name" => "CYCLOBENZAPRINE 10MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth at onset of migraine, may repeat once in 2 hours/ max 2 tabs per day" => [
        "drug_name" => "IMITREX 100MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 capsule by mouth every morning with meals with 75mg caps=total of 225mg" => [
        "drug_name" => "VENLAFAXINE ER 150MG CAP",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "CAP"
        ]
    ],
    "Take 1 tablet by mouth at bedtime with quetiapine 200mg tabs" => [
        "drug_name" => "QUETIAPINE FUMARATE 100MG TAB",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "2 po q day" => [
        "drug_name" => "DULOXETINE DR 60MG CAP",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "CAP"
        ]
    ],
    "1 (one) Tablet qam and 2 tabs qpm" => [
        "drug_name" => "NAMENDA 10MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "1 tablet tid prn for spasms Orally 30 day(s)" => [
        "drug_name" => "ZANAFLEX 4MG TAB",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 30.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet (25 mg total) by mouth 1 (one) time each day with dinner. Take 1/2 tablet (12.5mg) by mouth one time each day with dinner." => [
        "drug_name" => "METOPROLOL TARTRATE 25MG TAB",
        "expected" => [
            "sig_qty" => 135.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "1 DAILY" => [
        "drug_name" => "LASIX 40MG TAB",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 capsule by mouth once daily FOR DEPRESSION-TAKE WITH 75 MG" => [
        "drug_name" => "VENLAFAXINE ER 150MG CAP",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "CAP"
        ]
    ],
    "ORAL Take 1 bid" => [
        "drug_name" => "METOPROLOL TARTRATE 25MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 capsule once a day take along with 40mg for total of 60mg" => [
        "drug_name" => "PROZAC 20MG PULVULE",
        "expected" => [
            "sig_qty" => 90.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "PULVULE"
        ]
    ],
    "1 tablet at bedtime as needed Once a day Orally 30 days" => [
        "drug_name" => "TRAZODONE 50MG TAB",
        "expected" => [
            "sig_qty" => 30.0000000,
            "sig_days" => 30.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth every 8 hours as needed FOR MILD PAIN 1-3 ON A SCALE OUT OF 10" => [
        "drug_name" => "IBUPROFEN 800MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "ORAL Take 1 tid" => [
        "drug_name" => "BUSPIRONE 15MG TAB",
        "expected" => [
            "sig_qty" => 270.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ],
    "Take 1 tablet by mouth twice a day TO PREVENT CLOTS AND CHRONIC A.FIB" => [
        "drug_name" => "ELIQUIS 5MG TAB",
        "expected" => [
            "sig_qty" => 180.0000000,
            "sig_days" => 90.0000,
            "sig_unit" => "TAB"
        ]
    ]
    // Puffs/inhalators can last much longer
    // "Inhale 2 puff(s) every 4 hours by inhalation route." => [
    //     "expected" => [
    //         "sig_qty" => 12 * DAYS_STD,
    //         "sig_days" => DAYS_STD,
    //         "sig_unit" => "puff"
    //     ]
    // ]
];

$parser = new SigParser("aws-ch-responses.json");


foreach($correct_pairs as $text => $props) {
    $result = $parser->parse($text, $props['drug_name']);

    $expected = $props['expected'];
    foreach ($expected as $key => $val) {
        $msg = "For $key expected ".$expected[$key].", got ".$result[$key].". \n\tSig: $text\n";
        if (is_float($expected[$key])) {
            assert(abs($expected[$key] - $result[$key]) < 0.001, $msg);
        } else {
            assert($expected[$key] == $result[$key], $msg);
        }
    }
}


?>
