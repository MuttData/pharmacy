<?php

namespace  GoodPill;

use \PDOStatement;
use GoodPill\Storage\Goodpill;
use \IteratorIterator;

/**
 * Abstract for working with multiple object models as a group
 */
abstract class GPModelGroup implements \Iterator, \Countable{

    /**
     * The data used to poopulate the objects
     * @var array
     */
     private  $data;

    /**
     * The name of the class to use when iterating over objects
     * @var string
     */
    protected $objectClass  = '\GoodPill\GPModel';


    /**
     * Great giant teefees
     */
	public function __construct() {
		$this->gpdb = Goodpill::getConnection();
	}

    /**
     * Determine if the object has data
     *
     * @return boolean has the setData method been called.
     */
    public function isLoaded() {
        return $this->boolLoaded;
    }

    /**
     * Set the data array with new data.  the data must match the definitino of the
     * Object type represented by this group
     *
     * @param Array $data The data to use for he objects
     */
    public function setData(\PDOStatement $pdo) {
        if (empty($pdo)) {
            return false;
        } else {
            $this->data = new \IteratorIterator($pdo);
            $this->boolLoaded = true;
            return true;
        }
    }


    protected function getClassInstance($arrData) {
        $objInstance = new $this->objectClass();
        $objInstance->setDataArray($arrData);
        return $objInstance;
    }


    /*


    Methods required for \Countable

     */

    /**
     * The number of items inthe array
     * @return int Number of items in teh data aray
     */
    public function count() {
        if (!($this->data instanceof \IteratorIterator)) {
            return 0;
        }

        return iterator_count($this->data);
    }

    /*

    Methods required for \Iterator

     */

    /**
     * Reset the array counter to the begining
     * @return void
     */
    public function rewind() {
        if (!($this->data instanceof \IteratorIterator)) {
            return false;
        }

        return $this->data->rewind();
    }

    /**
     * Get the current object based on the array position
     * @return Object The object for this group with data stored
     */
    public function current() {
        if (!($this->data instanceof \IteratorIterator)) {
            return false;
        }

        $current = $this->data->current();
        return $this->getClassInstance($current);
    }

    /**
     * The position of the current mark
     * @return int
     */
    public function key() {
        if (!($this->data instanceof \IteratorIterator)) {
            return false;
        }

        return $this->data->key();
    }

    /**
     * Move to the next position in the array
     * @return void
     */
    public function next() {
        if (!($this->data instanceof \IteratorIterator)) {
            return false;
        }

        return $this->data->next();
    }

    /**
     * Is the current key valid
     * @return bool The current key esists and is valid
     */
    public function valid() {
        if (!($this->data instanceof \IteratorIterator)) {
            return false;
        }

        return $this->data->valid();
    }
}
