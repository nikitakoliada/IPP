<?php

namespace IPP\Student;


use IPP\Student\ErrorExit;
class Stack
{
    private $stack;
    private $stackLen;

    public function __construct()
    {
        $this->stack = array();
        $this->stackLen = 0;
    }

    public function push($valueType, $value)
    {
        array_push($this->stack, array($valueType, $value));
        $this->stackLen++;
    }

    public function pop()
    {
        if ($this->stackLen != 0) {
            $this->stackLen--;
            return array_pop($this->stack);
        } else {
            ErrorExit::exit_with_error(55, $this->stderr);
        }
    }

    public function isEmpty()
    {
        return $this->stackLen == 0;
    }

    public function top()
    {
        if (count($this->stack) == 1) {
            return $this->stack[0];
        }
        return end($this->stack); //returns the last value of the array
    }
}
