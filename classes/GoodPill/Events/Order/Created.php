<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\Order\OrderEvent;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;

class Created extends OrderEvent
{
    /**
     * The name of the event type
     * @var string
     */
    public $type = 'Order Created';

    /**
     * The path to the templates
     * @var string
     */
    protected $template_path = 'Order/Created';

    /**
     * Publish the events
     * Cancel the any events that are not longer needed and push this event to the com calendar
     */
    public function publish() : void
    {
        // Can't send notifications if the order doesn't exist
        if (!$this->order) {
            return;
        }

        $patient = $this->order->patient;

        $patient->cancelEvents(
            [
                'Order Updated',
                'Needs Form'
            ]
        );

        $patient->createEvent($this);
    }
}
