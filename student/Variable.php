<?php

namespace IPP\Student;


class Variable
{
    public $type_v;
    public $pure_name;
    public $name;
    public $initialized;
    public $value;

    public function __construct($name = null, $type_v = null, $initialized = false, $value = null)
    {
        $this->type_v = $type_v;
        $this->pure_name = null;
        $this->name = $name;
        $this->initialized = $initialized;
        $this->value = $value;
        $this->assignPureName();
    }

    public function assignPureName()
    {
        if ($this->name !== null) {
            $this->pure_name = substr($this->name, 3);
        }
    }
}