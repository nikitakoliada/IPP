<?php

namespace IPP\Student;

class ErrorExit
{   
    public static function exit_with_error($code, $stderr)
    {
        // Implement error handling or exit strategy here
        $stderr->writeString("An error occurred: " . $code . PHP_EOL);
        exit($code);
    }
}
