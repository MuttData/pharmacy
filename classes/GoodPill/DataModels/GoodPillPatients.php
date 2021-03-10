<?php

namespace GoodPill\DataModels;

use GoodPill\Storage\Goodpill;
use GoodPill\GPModelGroup;

use \PDO;
use \Exception;

/**
 * A class for loading Order data.  Right now we only have a few fields defined
 */
class GoodPillPatients extends GPModelGroup
{
    /**
     * List of possible properties names for this object
     * @var array
     */
    protected $objectClass  = '\GoodPill\DataModels\GoodPillPatient';

    /**
     * Test to see if the patient has both wc and cp ids
     * @return boolean
     */
    public function getTenPatients()
    {
        $pdo = $this->gpdb->prepare('select * from gp_patients limit 10');
        $pdo->execute();
        $this->setData($pdo);
    }
}
