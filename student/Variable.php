<?php

namespace IPP\Student;


class Variable
{
    public $type_of_variable;
    public $pure_name;
    public $name;
    public $initialized;
    public $value;

    public function __construct($name = null, $type_of_variable = null, $initialized = false, $value = null)
    {
        $this->type_of_variable = $type_of_variable;
        $this->name = $name;
        if ($this->name !== null) {
            $this->pure_name = substr($this->name, 3);
        }
        else {
            $this->pure_name = null;
        }
        $this->initialized = $initialized;
        $this->value = $value;
    }
}