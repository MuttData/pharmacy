<?php

namespace GoodPill\Models\v2;

use GoodPill\Models\GpPendGroup;

/**
 * Class Goodpill Drugs Table
 */
class PickListDrug
{
    /**
     * Hold the data so we don't call v2 over and over again
     * @var array
     */
    protected $pick_list;
    /**
     * keep track of the pendgroup in case we need to reload the data
     * @var GoodPill\Models\GpPendGroup
     */
    public $pend_group;

    /**
     * Keep track of the drug generic incase we need to refresh
     * @var string
     */
    public $drug_generic;

    /**
     * An override property so we can skip looking at v2
     * @var bool
     */
    protected $is_pended_override = null;

    /**
     * Lets get ready to rumble
     * @param GpPendGroup $pend_group   This should be the pendgroup we are attempting to fetch.
     * @param string      $drug_generic The generic name of the drug.
     */
    public function __construct(?GpPendGroup $pend_group = null, ?string $drug_generic = null)
    {
        if (!is_null($pend_group) && !is_null($drug_generic)) {
            $this->fetch($pend_group, $drug_generic);
        }
    }

    /**
     * Pull the list of pended/picked items from v2
     * @param  GpPendGroup $pend_group   This should be the pendgroup we are attempting to fetch.
     * @param  string      $drug_generic The generic name of the drug.
     * @return boolean True if the call to v2 was successful
     */
    public function fetch(GpPendGroup $pend_group, string $drug_generic)
    {
        if (!$pend_group->exists) {
            return false;
        }

        $this->pend_group   = $pend_group;
        $this->drug_generic = $drug_generic;

        if (empty($this->pick_list)) {
            $drug_generic = rawurlencode($drug_generic);
            $pend_url = "/account/8889875187/pend/{$pend_group->pend_group}/{$drug_generic}";
            $results  = v2_fetch($pend_url, 'GET');

            if (!empty($results)) {
                $this->pick_list = $results;
            } else {
                $this->pick_list = [];
            }
        }

        return true;
    }

    /**
     * If you already have the picklist, you can set it manually
     * @param array $list A fully formed picklist.  This can be generated by
     *      the make_pick_list function.
     * @return void
     */
    public function setPickList(array $list) : void
    {
        $this->pick_list = $list;
    }

    /**
     * A method for setting the list as pended.  This is really only used when we generated
     * the list manually, and pended it, but don't want to call v2 to confirm
     *
     * @param bool $is_pended Should the list be marked as pended.
     *
     * @return bool
     */
    public function setIsPended($is_pended = false)
    {
        $this->is_pended_override = $is_pended;
    }

    /**
     * Get the names of all the next states
     * @return array
     */
    public function getNextStates() : array
    {
        $states = array_map(
            function ($pick_item) {
                $key_states = array_keys((array) $pick_item['next'][0]);
                return reset($key_states);
            },
            $this->pick_list
        );

        $states = array_unique($states);
        return $states;
    }

    /**
     * Consider an order pended if there are any items still in a pended state.  This could
     * be incorrect.  We may want to change it to be all items have to be in pended to be isPended(),
     * but my assumption is if anything is pended, then the enire picklist hasn't been completed so it's Still
     * in a pended state
     * @return boolean
     */
    public function isPended() : bool
    {

        if (!is_null($this->is_pended_override)) {
            return $this->is_pended_override;
        }

        if (empty($this->pick_list)) {
            return false;
        }

        $next_states = $this->getNextStates();

        return (in_array('pended', $next_states));
    }

    /**
     * Consider the order picked if the only stat is picked.
     * @return boolean
     */
    public function isPicked() : bool
    {
        if (empty($this->pick_list)) {
            return false;
        }

        $next_states = $this->getNextStates();

        return (in_array('picked', $next_states) && count($next_states) == 1);
    }

    /**
     * Get the NDC that was passed to the pendlist.  right now this could be multiples,
     * but we assume it is one
     *
     * @return string
     */
    public function getNdc() : ?string
    {
        if (empty($this->pick_list)) {
            return null;
        }

        return $this->pick_list[0]['drug']['_id'];
    }

    /**
     * Get the NDC that was passed to the pendlist.  right now this could be multiples,
     * but we assume it is one
     *
     * @return string
     */
    public function getGsns() : ?string
    {
        if (empty($this->pick_list)) {
            return null;
        }

        return $this->pick_list[0]['drug']['gsns'];
    }

    /**
     * Get the total quantity pended in v2
     * @return integer
     */
    public function getPendedQty() : ?int
    {
        $quantities = array_map(
            function ($pick_item) {
                return $pick_item['qty']['to'];
            },
            $this->pick_list
        );

        return array_sum($quantities);
    }
}