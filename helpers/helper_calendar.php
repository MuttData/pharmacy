<?php

use GoodPill\AWS\SQS\GoogleCalendarQueue;
use GoodPill\AWS\SQS\GoogleAppRequest\Calendar\Create;
use GoodPill\AWS\SQS\GoogleAppRequest\Calendar\Delete;
use GoodPill\AWS\SQS\GoogleAppRequest\Calendar\SearchAndDelete;
use GoodPill\Logging\GPLog;
use GoodPill\Logging\AuditLog;
use GoodPill\Logging\CliLog;
use GoodPill\Storage\Goodpill;

require_once "helpers/helper_appsscripts.php";

function order_dispensed_event($order, $salesforce, $hours_to_wait)
{
    if (@$order[0]['patient_inactive']) {
        GPLog::warning('order_dispensed_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Order Dispensed: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'order_dispensed_event', ['Order Dispensed', 'Order Cancelled', 'Needs Form']);

    $comm_arr = new_comm_arr($patient_label, '', '', $salesforce);

    GPLog::info('order_dispensed_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait);
}

function order_shipped_event($order, $email, $text)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('order_shipped_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Order Shipped: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'order_shipped_event', ['Order Shipped', 'Order Dispensed', 'Order Cancelled', 'Needs Form']);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('order_shipped_event', get_defined_vars());

    create_event($event_title, $comm_arr, 10/60);
}

function refill_reminder_event($order, $email, $text, $hours_to_wait, $time_of_day = null)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('refill_reminder_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Refill Reminder: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_order($order[0]['invoice_number'], 'refill_reminder_event', ['Refill Reminder']);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::warning('refill_reminder_event', get_defined_vars()); //$cancel

    create_event($event_title, $comm_arr, $hours_to_wait, $time_of_day);
}

function autopay_reminder_event($order, $email, $text, $hours_to_wait, $time_of_day = null)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('autopay_reminder_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Autopay Reminder: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'autopay_reminder_event', ['Autopay Reminder']);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::notice('autopay_reminder_event', get_defined_vars());

    //create_event($event_title, $comm_arr, $hours_to_wait, $time_of_day);
}

function order_created_event($groups, $email, $text, $hours_to_wait)
{
    $order = $groups['ALL'];

    if ($order[0]['patient_inactive']) {
        GPLog::warning('order_created_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $count = count($groups['FILLED']) + count($groups['ADDED']);

    $patient_label = get_patient_label($order);
    $event_title   = "{$order[0]['invoice_number']}. Order Created: $count items. $patient_label.  Created:".date('Y-m-d H:i:s');

    $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'order_created_event', ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Cancelled', 'Order Hold', 'No Rx', 'Needs Form']);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('order_created_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait);
}

function transfer_requested_event($order, $email, $text, $hours_to_wait)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('transfer_requested_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Transfer Requested: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'transfer_requested_event', ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx']);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('transfer_requested_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait);
}

function transfer_out_event($order, $email, $text, $hours_to_wait)
{
    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Transfer Out: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    //$cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'transfer_out_event', []);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('transfer_out_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait);
}

function no_transfer_out_event($order, $email, $text, $hours_to_wait)
{
    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' NO Transfer Out: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    //$cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'transfer_out_event', []);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('no_transfer_out_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait);
}

function order_hold_event($order, $email, $text, $salesforce, $hours_to_wait)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('order_hold_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    if (! isset($order[0]['invoice_number'])) {
        GPLog::warning('ERROR order_hold_event: indexes not set', get_defined_vars());
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Order Hold: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'order_hold_event', ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx']);

    $comm_arr = new_comm_arr($patient_label, $email, $text, $salesforce);

    GPLog::warning('order_hold_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait);
}

function order_updated_event($groups, $email, $text, $hours_to_wait)
{
    $order = $groups['ALL'];

    if ($order[0]['patient_inactive']) {
        GPLog::warning('order_updated_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $count = count($groups['FILLED']) + count($groups['ADDED']);

    $patient_label = get_patient_label($order);
    $event_title   = "{$order[0]['invoice_number']} Order Updated: $count items. $patient_label.  Created:".date('Y-m-d H:i:s');

    //$cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'order_updated_event', ['Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx', 'Needs Form', 'Order Cancelled']);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('order_updated_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait);
}

function needs_form_event($order, $email, $text, $salesforce, $hours_to_wait, $time_of_day = 0)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('needs_form_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Needs Form: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $comm_arr = new_comm_arr($patient_label, $email, $text, $salesforce);

    GPLog::info('needs_form_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait, $time_of_day);
}

function no_rx_event($partial, $order, $email, $text, $hours_to_wait, $time_of_day = null)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('no_rx_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $partial['invoice_number'].' No Rx: '.$patient_label.'. Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], 'no_rx_event', ['No Rx']);

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('no_rx_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait, $time_of_day);
}

function order_cancelled_event($partial, $order, $email, $text, $hours_to_wait, $time_of_day  = null)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('order_cancelled_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $partial['invoice_number'].' Order Cancelled: '.$patient_label.'. Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_order(
        $partial['invoice_number'],
        'order_cancelled_event',
        [
            'Order Created',
            'Order Updated',
            'Order Dispensed'
        ]
    );

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('order_cancelled_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait, $time_of_day);
}

function order_rescheduled_event(
    $partial,
    $order,
    $email,
    $text,
    $hours_to_wait,
    $time_of_day = null
) {
    if ($order[0]['patient_inactive']) {
        GPLog::warning(
            'order_rescheduled_event rescheduled because patient inactive',
            get_defined_vars()
        );
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = "{$partial['invoice_number']} Order Rescheduled: {$patient_label} Created:";
    $event_title   .= date('Y-m-d H:i:s');

    $cancel = cancel_events_by_order(
        $partial['invoice_number'],
        'order_rescheduled_event',
        [
            'Order Created',
            'Order Updated',
            'Order Dispensed',
            'Order Rescheduled'
        ]
    );

    $comm_arr = new_comm_arr($patient_label, $email, $text);

    GPLog::info('order_rescheduled_event', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait, $time_of_day);
}

function confirm_shipment_event($order, $email, $text, $salesforce, $hours_to_wait, $time_of_day = null)
{
    if ($order[0]['patient_inactive']) {
        GPLog::warning('confirm_shipment_event cancelled because patient inactive', get_defined_vars());
        return;
    }

    $patient_label = get_patient_label($order);
    $event_title   = $order[0]['invoice_number'].' Confirm Shipment: '.$patient_label.'.  Created:'.date('Y-m-d H:i:s');

    $cancel = cancel_events_by_order(
        $order[0]['invoice_number'],
        'confirm_shipment_event',
        [
            'Order Dispensed',
            'Order Created',
            'Transfer Requested',
            'Order Updated',
            'Order Hold',
            'No Rx',
            'Needs Form',
            'Order Cancelled',
            'Confirm Shipment'
        ]
    );

    $comm_arr = new_comm_arr($patient_label, $email, $text, $salesforce);

    GPLog::info('confirm_shipment_event INACTIVE', get_defined_vars());

    create_event($event_title, $comm_arr, $hours_to_wait, $time_of_day);
}

function new_comm_arr($patient_label, $email = '', $text = '', $salesforce = '')
{
    $comm_arr = [];
    $auto     = [];
    $auto_salesforce = false;

    if (! LIVE_MODE) {
        return $comm_arr;
    }

    if ($email and @$email['email'] and ! preg_match('/\d\d\d\d-\d\d-\d\d@goodpill\.org/', $email['email'])) {
        $auto_salesforce = ($auto_salesforce or $email['email'] != DEBUG_EMAIL);

        $auto[] = "Email";
        $email['bcc']  = DEBUG_EMAIL;
        $email['from'] = 'Good Pill Pharmacy < support@goodpill.org >'; //spaces inside <> are so that google cal doesn't get rid of "HTML" if user edits description
        $comm_arr[] = $email;
    }

    if ($text and $text['sms'] and ! in_array($text['sms'], DO_NOT_SMS)) {
        //addCallFallback

        $auto_salesforce = ($auto_salesforce or $text['sms'] != DEBUG_PHONE);

        $auto[] = "Text/Call";

        /* Make a copy using JSON */
        try {
            //just in case we were sloppy with undefined
            $json = preg_replace('/ undefined/', '', json_encode($text));
            $text = json_decode($json, true);
            $call = json_decode($json, true);
        } catch (Error $e) {
            GPLog::error('format_call/text json.parse error', get_defined_vars());
        }

        $text['message'] = format_text($text['message']);

        if (! @$text['fallbacks']) { //Default to a call fallback if SMS fails

            $call['message'] = call_wrapper(format_call($call['message']));
            $call['call']    = $call['sms'];
            unset($call['sms']);

            $text['fallbacks'] = [$call];
        }

        $comm_arr[] = $text;
    }

    if (! $salesforce and $auto_salesforce and $patient_label and $comm_arr) {
        $comm_arr[] = [
          "subject"   => "Auto ".implode(', ', $auto).": ".(@$email['subject'] ?: "Text"),
          "body"      => @$text['message'] ?: format_text($email['message']),
          "contact"   => $patient_label,
          "assign_to" => null,
          "due_date"  => null
        ];
    }

    if ($salesforce and $salesforce['assign_to']) {
        $comm_arr[] = $salesforce;
    }

    GPLog::info('comm_array', [
        'patient_label' => $patient_label,
        'email' => $email,
        'text' => $text,
        'salesforce' => $salesforce,
        'comm_arr' => $comm_arr
      ]);

    return $comm_arr;
}

function format_text($text_message)
{
    return preg_replace(['/<br>/', '/<.*?>/', '/#(\d{4,})/'], ["\n", '', '$1'], $text_message);
}

function format_call($call_message)
{
    $regex = [
        '/View it at [^ ]+ /',
        '/Track it at [^ ]+ /',
        '/\(?888[)-.]? ?987[.-]?5187/',
        '/(www\.)?goodpill\.org/',
        '/(\w):(?!\/\/)/',
        '/;<br>/',
        '/;/',
        '/\./',
        '/(<br>)+/',
        '/\.(\d)(\d)?(\d)?/',
        '/ but /',
        '/(\d+)MG/',
        '/(\d+)MCG/',
        '/(\d+)MCG/',
        '/ Rxs/i',
        '/ ER /i',
        '/ DR /i',
        '/ TAB| CAP/i',
        '/\#(\d)(\d)(\d)(\d)(\d)(\d)?/'
      ];

    $replace = [
        "",
        "View and track your order online at www.goodpill.org",
        '8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7',
        'w,,w,,w,,dot,,,,good,,,,pill,,,,dot,,,,org,,,,again that is g,,,,o,,,,o,,,d,,,,p,,,,i,,,,l,,,,l,,,,dot,,,,o,,,,r,,,,g',
        '$1<Pause />', //Don't capture JSON $text or URL links
        ';<Pause /> and <Pause />', //combine drug list with "and" since it sounds more natural.  Keep semicolon so regex can still find and remove.
        ';<Pause />', //can't do commas without testing for inside quotes because that is part of json syntax. Keep semicolon so regex can still find and remove.
        ' <Pause />', //can't do commas without testing for inside quotes because that is part of json syntax
        ' <Pause length="1" />',
        ' point $1,,$2,,$3', //skips pronouncing decimal points
        ',,,,but,,,,',
        '<Pause />$1 milligrams',
        '<Pause />$1 micrograms',
        '<Pause />$1 micrograms',
        ' prescriptions',
        ' extended release ',
        ' delayed release ',
        ' <Pause />',
        'number,,,,$1,,$2,,$3,,$4,,$5,,$6' //<Pause /> again that is $order number <Pause />$1,,$2,,$3,,$4,,$5,,$6
      ];

    //Improve Pronounciation
    return preg_replace($regex, $replace, $call_message);
}

function call_wrapper($message)
{
    return 'Hi, this is Good Pill Pharmacy <Pause />' . $message . ' <Pause length="2" />if you need to speak to someone please call us at 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause length="2" /> Again our phone number is 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause />';
}

function get_patient_label($order)
{
    if (! isset($order[0])) {
        GPLog::error('ERROR: get_patient_label', get_defined_vars());
        return '';
    }

    return $order[0]['first_name'].' '.$order[0]['last_name'].' '.$order[0]['birth_date'];
}

/**
 * Create an event on the communication Calender
 * @param  string  $event_title   The ttitle of the event
 * @param  array   $comm_arr      The array to put on the calendar
 * @param  integer $hours_to_wait The number of hours to wait for the alert
 * @param  hh:mm   $time_of_day   Eastern time in 24 hours to send message.  eg '14:30' to send at 2:30pm ET
 * @return void
 */
function create_event(
    $event_title,
    $comm_arr,
    $hours_to_wait = 0,
    $time_of_day = null,
    $async = true
) {

    $startTime = get_start_time($hours_to_wait, $time_of_day);

    $create_event                = new Create();
    $create_event->cal_id        = GD_CAL_ID;
    $create_event->start         = $startTime;
    $create_event->hours         = 0.5;
    $create_event->title         = $event_title;
    $create_event->description   = $comm_arr;
    $create_event->group_id      = 'calendar-request';

    if ($async) {
        $gdq = new GoogleCalendarQueue();
        $gdq->send($create_event);
        return true;
    }

    $response = json_decode(gdoc_post(GD_HELPER_URL, $create_event->toArray()));

    GPLog::debug(
        "Communication Calendar event created: $event_title",
        [
            "message" => $create_event->toArray(),
            "result"  => $response,
            "invoice_number" => substr($event_title, 0, stripos($event_title, ' '))
        ]
    );
}

function cancel_events(array $ids, $async = true)
{

    $delete_events                = new Delete();
    $delete_events->cal_id        = GD_CAL_ID;
    $delete_events->ids           = $ids;
    $delete_events->group_id      = 'calendar-request';

    if ($async) {
        $gdq = new GoogleCalendarQueue();
        $gdq->send($delete_events);
        return true;
    }

    $response = json_decode(gdoc_post(GD_HELPER_URL, $delete_events->toArray()));

    GPLog::notice('cancel_events', get_defined_vars());
}

function modify_events($modify)
{
    $args = [
    'method'  => 'modifyCalendarEvents',
    'cal_id'  => GD_CAL_ID,
    'events'  => $modify
  ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    GPLog::info('modify_events', get_defined_vars());
}

function short_links($links)
{
    $args = [
    'method'  => 'shortLinks',
    'links'  => $links
  ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    GPLog::notice('short_links', get_defined_vars());

    return json_decode($result, true);
}

function tracking_url($tracking_number)
{
    $url = '#';

    if (strlen($tracking_number) == 22) {
        $url = 'https://tools.usps.com/go/TrackConfirmAction?tLabels=';
    } elseif (strlen($tracking_number) == 15 or strlen($tracking_number) == 12) { //Ground or Express
        $url = 'https://www.fedex.com/apps/fedextrack/?tracknumbers=';
    }

    return $url.$tracking_number;
}

//Convert gsheet hyperlink formula to an html link
function tracking_link($tracking)
{
    return '<a href="'.tracking_url($tracking).'">'.$tracking.'</a>';
}

function get_phones($order)
{
    if (! isset($order[0]) or ! isset($order[0]['phone1'])) {
        GPLog::error('get_phones', get_defined_vars());
        return '';
    }
    //email('get_phones', $order);
    return $order[0]['phone1'].($order[0]['phone2'] ? ','.$order[0]['phone2'] : '');
}

//Return a copy of the date (or now) with the 24-hour set
function get_start_time($hours_to_wait, $time_of_day = null)
{

  //PHP Issue of strtotime() with fractions https://stackoverflow.com/questions/11086022/can-strtotime-handle-fractions so convert to minutes and round
    $minutes_to_wait = round($hours_to_wait*60);

    $start = date('Y-m-d\TH:i:s', strtotime("+$minutes_to_wait minutes"));

    if ($time_of_day) {
        $start = substr($start, 0, 11)."$time_of_day:00";
    }

    return $start;
}

//TODO exactly replicate Guardian's patient matching function
function search_events_by_person($first_name, $last_name, $birth_date, $past = false, $types = [])
{
    $types = implode('|', $types);
    $first = substr($first_name, 0, 3);

    $args = [
    'method'       => 'searchCalendarEvents',
    'cal_id'       => GD_CAL_ID,
    'hours'        => DAYS_STD*24,
    'past'         => $past,
    'word_search'  => "$last_name $birth_date",
    'regex_search' => "/($types).+$first/i" //first name is partial which is not currently supported by gcal natively
  ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    $json = json_decode($result, true);

    if ($result != '[]') {
        GPLog::notice("search_events_by_person: $first_name $last_name $birth_date", $json);
    }

    return $json;
}

function search_events_by_order($invoice_number, $past = false, $types = [])
{
    $types = implode('|', $types);

    $args = [
        'method'       => 'searchCalendarEvents',
        'cal_id'       => GD_CAL_ID,
        'hours'        => DAYS_STD*24,
        'past'         => $past,
        'word_search'  => "$invoice_number",
        'regex_search' => "/($types)/i"
    ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    $json = json_decode($result, true);

    if ($result != '[]') {
        GPLog::notice(
            "search_events_by_order: $invoice_number",
            ['invoice_number' => $invoice_number, 'json' => $json]
        );
    }

    return $json;
}

//NOTE: RELIES on the assumption that ALL drugs (and their associated messages) end with a semicolon (;) and
//that NO other semicolons are used for any other reason. It removes everything between the drug name and the
//semicolon, and if no semicolons are left in the communication, then the entire communication is deleted
function remove_drugs_from_refill_reminders($first_name, $last_name, $birth_date, $drugs)
{
    if (! $drugs) {
        return;
    }

    $phone_drugs = json_decode(format_call(json_encode($drugs)), true);
    $phone_regex = implode('[^;]*|', $phone_drugs).'[^;]*';

    $email_regex = implode('[^;]*|', $drugs).'[^;]*';

    if ($phone_regex) {
        $replace = "~$email_regex|$phone_regex~";
    } else {
        $replace = "~$email_regex~";  //if phone_regex is empty and we end regex with | preg_replace will have error and return null
        GPLog::error("remove_drugs_from_refill_reminders has empty phone_regex", get_defined_vars());
    }

    return replace_text_in_events(
        $first_name,
        $last_name,
        $birth_date,
        ['Refill Reminder'],
        $replace,
        '',
        '/^[^;]*$/'
    );
}

function update_last4_in_autopay_reminders($first_name, $last_name, $birth_date, $new_last4)
{
    $new_last4 = implode(' ', str_split($new_last4));

    return replace_text_in_events(
        $first_name,
        $last_name,
        $birth_date,
        ['Autopay Reminder'],
        "~card \d \d \d \d for~",
        "card $new_last4 for"
    );
}

function replace_text_in_events($first_name, $last_name, $birth_date, $types, $replace_regex, $replace_string, $remove_regex = false)
{
    if (! LIVE_MODE) {
        return;
    }

    $modify = [];
    $remove = [];
    $events = search_events_by_person($first_name, $last_name, $birth_date, false, $types);

    foreach ($events as $event) {
        $old_desc = $event['description']; //This is still JSON.stringified

        $new_desc = preg_replace($replace_regex, $replace_string, $old_desc);

        if (is_null($new_desc) or strlen($old_desc) == strlen($new_desc)) { // == didn't seem to work but I couldn't eyeball why
            GPLog::notice('replace_text_in_events no changes', ['old' => $old_desc, 'new' => $new_desc, 'count_old' => strlen($old_desc), 'count_new' => strlen($new_desc), 'name' => "$first_name $last_name $birth_date", 'replace_regex' => $replace_regex, 'remove_regex' => $remove_regex, 'types' => $types]);
            continue;
        }

        if ($remove_regex and preg_match($remove_regex, $new_desc)) {
            GPLog::notice('replace_text_in_events removeEvent', ['old_desc' => $old_desc, 'new_desc' => $new_desc, 'name' => "$first_name $last_name $birth_date", 'replace_regex' => $replace_regex, 'remove_regex' => $remove_regex, 'types' => $types]);

            $remove[] = $event['id'];
        } else {
            GPLog::notice('replace_text_in_events modifyEvent', ['old_desc' => $old_desc, 'new_desc' => $new_desc, 'name' => "$first_name $last_name $birth_date", 'replace_regex' => $replace_regex, 'remove_regex' => $remove_regex, 'types' => $types]);

            $event['description'] = $new_desc;
            $modify[] = $event;
        }
    }

    if (count($modify)) {
        modify_events($modify);
    }


    if (count($remove)) {
        cancel_events($remove);
    }
}

function cancel_events_by_person($first_name, $last_name, $birth_date, $caller, $types = [])
{
    if (! LIVE_MODE) {
        return;
    }

    $types = implode('|', $types);

    $search_and_delete = new SearchAndDelete();
    $search_and_delete->cal_id = GD_CAL_ID;
    $search_and_delete->hours = DAYS_STD*24;
    $search_and_delete->word_search = "{$last_name} {$birth_date}";

    //first name is partial which is not currently supported by gcal natively
    $search_and_delete->regex_search = "/({$types}).+{$first_name}/i";
    $search_and_delete->group_id = 'calendar-request';

    $gdq = new GoogleCalendarQueue();
    $gdq->send($search_and_delete);

    return true;
}

function cancel_events_by_order($invoice_number, $caller, $types = [])
{
    $types = implode('|', $types);

    $search_and_delete = new SearchAndDelete();
    $search_and_delete->cal_id = GD_CAL_ID;
    $search_and_delete->hours = DAYS_STD*24;
    $search_and_delete->word_search = "{$invoice_number}";

    //first name is partial which is not currently supported by gcal natively
    $search_and_delete->regex_search = "/({$types})/i";
    $search_and_delete->group_id = 'calendar-request';

    $gdq = new GoogleCalendarQueue();
    $gdq->send($search_and_delete);

    return true;
}
