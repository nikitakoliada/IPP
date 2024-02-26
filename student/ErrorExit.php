<?php

namespace IPP\Student;

class ErrorExit
{   
    public static function exit_with_error($code)
    {
        // Implement error handling or exit strategy here
        echo "An error occurred: " . $code . PHP_EOL;
        exit($code);
    }
}
