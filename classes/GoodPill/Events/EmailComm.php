<?php

namespace GoodPill\Events;

use GoodPill\Events\Comm;

class EmailComm extends Comm
{

    protected $properties = [
        'message',
        'email',
        'subject',
        'bcc',
        'from',
        'attachments'
    ];

    protected $required = [
        'message',
        'email',
        'subject',
        'from'
    ];


    /**
     * Make sure the attachments are an array
     * @param array $attachments An array of google doc ids
     */
    public function setAttachments(array $attachments)
    {
        $this->stored_data['attachments'] = $attachments;
    }

    /**
     * Create a Comm Calendar compatible Email message
     * @return array
     */
    public function delivery() : array
    {
        if (!isset($this->from)) {
            $this->from = 'Good Pill Pharmacy < support@goodpill.org >';
        }

        // Email isn't to goodpill.org
        if (!preg_match('/\d\d\d\d-\d\d-\d\d@goodpill\.org/', $this->email)) {
            $this->bcc  = DEBUG_EMAIL;
        }

        // Make sure all the required fields are complete
        if (! $this->requiredFieldsComplete()) {
            throw new \Exception('Missing required fields');
        }

        return $this->toArray();
    }
}
