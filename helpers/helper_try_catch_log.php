<?php

use Sirum\Logging\SirumLog;

function helper_try_catch_log($function, $data)
{
    try {
        call_user_func($function, $data);
    } catch (Exception $e) {
        // Log As an error
        SirumLog::emergency(
            "The loop function {$function} failed to proccess",
            [
                'data'  => $data,
                'error' => $e->getCode() . " " . $e->getMessage(),
                'file'  => $e->getFile() . ":" . $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        );
    }
}