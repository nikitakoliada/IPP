<?php

namespace IPP\Student;


class Argument
{
    public $kind;
    public $value;
    public $name;
    public $frame;
    public $arg_order;

    public function __construct($kind, $value = null, $name = null, $arg_order = null)
    {
        $this->name = $name;
        $this->frame = null;
        
        $this->arg_order = $arg_order;
        $this->kind = $kind;
        $this->value = $value;
        if ($this->name !== null && $this->kind === 'var') {
            $this->frame = substr($this->name, 0, 2);
        }
        echo "Argument created: " . $this->kind . " " . $this->value . " " . $this->name . " " . $this->frame . " " . $this->arg_order . PHP_EOL;
    }

    public function getPureName()
    {
        if ($this->name !== null && $this->kind === 'var') {
            return substr($this->name, 3);
        }
    }
}