<?php

namespace IPP\Student;


class Instruction
{
    public $opcode;
    public $order;
    public $args;
    public $args_count;

    public function __construct($opcode, $order)
    {
        $this->opcode = $opcode;
        $this->order = $order;
        $this->args = array();
    }

    public function sortArguments()
    {
        usort($this->args, function ($a, $b) {
            $a->arg_order <=> $b->arg_order;
        });
    }

    public function addArgument($arg)
    {
        $this->args[] = $arg;
        $this->args_count++;
    }
}