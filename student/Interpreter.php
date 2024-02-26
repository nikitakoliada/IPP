<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
        enum OperandTypes: string
        {
            case VAR = "var";
            case SYMB = "symb";
            case TYPE = "type";
            case LABEL = "label";
        }
        function exit_with_error($code)
        {
            // Implement error handling or exit strategy here
            echo "An error occurred: " . $code . PHP_EOL;
            exit($code);
        }
        class Stack
        {
            private $stack;
            private $stackLen;

            public function __construct()
            {
                $this->stack = array();
                $this->stackLen = 0;
            }

            public function pushValue($valueType, $value)
            {
                array_push($this->stack, array($valueType, $value));
                $this->stackLen++;
            }

            public function popValue()
            {
                if ($this->stackLen != 0) {
                    $this->stackLen--;
                    return array_pop($this->stack);
                } else {
                    throw new Exception("Stack is empty", 55);
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

        class Instruction
        {
            public $inst_opcode;
            public $order;
            public $args;

            public function __construct($inst_opcode, $order)
            {
                $this->inst_opcode = $inst_opcode;
                $this->order = $order;
                $this->args = array();
            }

            public function sortArguments()
            {
                usort($this->args, function ($a, $b) {
                    return $a->arg_order - $b->arg_order;
                });
            }

            public function addArgument($arg)
            {
                $this->args[] = $arg;
            }
        }

        class Argument
        {
            public $kind;
            public $value;
            public $name;
            public $frame;
            public $arg_order;

            public function __construct($kind, $value = null, $name = null, $arg_order = null)
            {
                $this->kind = $kind;
                $this->value = $value;
                $this->name = $name;
                $this->frame = null;
                $this->arg_order = $arg_order;
                $this->assignFrame();
            }

            public function assignFrame()
            {
                if ($this->name !== null && $this->kind === 'var') {
                    $this->frame = substr($this->name, 0, 2);
                }
            }

            public function getPureName()
            {
                if ($this->name !== null && $this->kind === 'var') {
                    return substr($this->name, 3);
                }
            }
        }

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

        class XmlInterpret
        {
            private $order_numbers = [];
            private $instructions = [];
            private $labels = [];
            private $global_frame = [];
            private $temp_frame = [];
            private $temp_frame_valid = false;
            private $local_frame = new Stack();
            private $call_stack = new Stack();
            private $data_stack = false;


            public static function check_int_in_str($string)
            {
                if ($string[0] === '-' || $string[0] === '+') {
                    return ctype_digit(substr($string, 1));
                }
                return ctype_digit($string);
            }
            public function sortInstructions()
            {
                usort($this->instructions, function ($a, $b) {
                    return $a->order <=> $b->order;
                });
            }
            public function parse_instructions($node)
            {
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'instruction') {
                        $order = $child->getAttribute('order');
                        $opcode = $child->getAttribute('opcode');

                        if (!$order || !$opcode || !ctype_digit($order) || !$this->check_int_in_str($order) || in_array((int) $order, $this->order_numbers) || (int) $order < 1) {
                            exit_with_error(32);
                        }

                        $this->order_numbers[] = (int) $order;
                        $instruction = new Instruction($opcode, (int) $order);

                        foreach ($child->childNodes as $argNode) {
                            if ($argNode->nodeType === XML_ELEMENT_NODE) {
                                $this->parse_argument($instruction, $argNode);
                                if ($argNode) { // Ensure parse_argument returns an Argument or null
                                    $instruction->addArgument($argNode);
                                }
                            }
                        }

                        $instruction->sortArguments();

                        $counter = 1;
                        foreach ($instruction->args as $arg) {
                            if ($arg->arg_order != $counter) {
                                exit_with_error(32);
                            }
                            $counter++;
                        }

                        $this->instructions[] = $instruction;
                    }
                }
            }
            public function parse_argument($inst, $arg)
            {
                $allowedTags = ['arg1', 'arg2', 'arg3'];
                if (!in_array($arg->tagName, $allowedTags)) {
                    exit_with_error(32);
                }

                $arg_order = intval(substr($arg->tagName, 3));
                $type = $arg->getAttribute('type');
                $text = $arg->nodeValue;

                switch ($type) {
                    case 'var':
                        if (strlen($text) < 4 || !in_array(substr($text, 0, 3), ['GF@', 'LF@', 'TF@'])) {
                            exit_with_error(31);
                        }
                        $inst->addArgument(new Argument('var', $text, $arg_order));
                        break;

                    case 'string':
                        // Simplifying here; you may need to adjust based on specific needs
                        $value = $text ?: '';
                        $inst->addArgument(new Argument('string', $value, $arg_order));
                        break;

                    case 'int':
                        if (!$text || !$this->check_int_in_str($text)) {
                            exit_with_error(32);
                        }
                        $inst->addArgument(new Argument('int', intval($text), $arg_order));
                        break;

                    case 'bool':
                        if (!in_array($text, ['false', 'true'])) {
                            exit_with_error(31);
                        }
                        $inst->addArgument(new Argument('bool', $text, $arg_order));
                        break;

                    case 'nil':
                        if ($text !== "nil") {
                            exit_with_error(31);
                        }
                        $inst->addArgument(new Argument('nil', 'nil', $arg_order));
                        break;

                    case 'label':
                        if (!$text) {
                            exit_with_error(31);
                        }
                        $inst->addArgument(new Argument('label', $text, $arg_order));
                        break;

                    case 'type':
                        if (!in_array($text, ['int', 'string', 'bool'])) {
                            exit_with_error(31);
                        }
                        $inst->addArgument(new Argument($text, $text, $arg_order));
                        break;

                    default:
                        exit_with_error(32);
                }
            }
            public function findLabels()
            {
                $instNum = 0;
                foreach ($this->instructions as $inst) {
                    if ($inst->inst_opcode == 'LABEL') {
                        $label = $inst->args[0]; // Assuming $inst->args is an array of objects
                        if (array_key_exists($label->value, $this->labels)) {
                            exit_with_error(52);
                        }
                        $this->labels[$label->value] = $instNum;
                    }
                    $instNum++;
                }
            }
            private function getFrame($arg)
            {
                switch ($arg->frame) {
                    case 'GF':
                        return $this->global_frame;
                    case 'TF':
                        if (!$this->temp_frame_valid) {
                            exit_with_error(55);
                        }
                        return $this->temp_frame;
                    case 'LF':
                        if ($this->local_frame->isEmpty()) {
                            exit_with_error(55);
                            return;
                        }
                        return $this->local_frame->top()[1];
                }
            }
            private function getValueAndTypeOfSymbol($arg)
            {
                if ($arg->kind === 'var') {
                    $var = $this->getVar($arg);
                    return [$var->value, $var->type_v, $var->initialized];
                } elseif (in_array($arg->kind, ['string', 'int', 'bool', 'nil'])) {
                    return [$arg->value, $arg->kind, true];
                }
            }
            private function getVar($arg)
            {
                $frame = $this->getFrame($arg);
                foreach ($frame as $var) {
                    // Assuming $var->pure_name and a method $arg->getPureName() exist
                    if ($var->pure_name === $arg->getPureName()) {
                        return $var;
                    }
                }
                exit_with_error(54); // Assuming this method is defined to handle errors
            }
            public function generate()
            {
                $inst_num = 0;
                while (true) {
                    if ($inst_num > count($this->instructions) - 1) {
                        break;
                    }
                    $inst = $this->instructions[$inst_num];
                    $opcode = $inst->inst_opcode;
                    if ($opcode == 'LABEL') {
                        $inst_num++;
                        continue;
                    } else if ($opcode == 'JUMP') {
                        $label = $inst->args[0];
                        if (!array_key_exists($label->value, $this->labels)) {
                            exit_with_error(52);
                        }
                        $inst_num = $this->labels[$label->value];
                        continue;
                    } else if ($opcode == 'DEFVAR') {
                        $arg = $inst->args[0];
                        $frame = $this->getFrame($arg);
                        $frame->push(new Variable($arg->name, null, false, null));
                        @$inst_num++;
                    } else if ($opcode == "MOVE") {
                        $arg = $inst->args[0];
                        $var = $this->getVar($arg);
                        $symbol = $inst->args[1];
                        $value = $this->getValueAndTypeOfSymbol($symbol);
                        if ($value[2] === null) {
                            exit_with_error(56);
                        }
                        $var->value = $value[0];
                        $var->type_v = $value[1];
                        $var->initialized = true;
                    } else if ($opcode == "CALL") {
                        $label = $inst->args[0];
                        $this->call_stack->pushValue('int', $inst_num);
                        if (!array_key_exists($label->value, $this->labels)) {
                            exit_with_error(52);
                        }
                        $inst_num = $this->labels[$label->value];
                    } else if ($opcode == "RETURN") {
                        if ($this->call_stack->isEmpty()) {
                            exit_with_error(56);
                        }
                        $inst_num = $this->call_stack->popValue()[1];
                    } else if ($opcode == 'CREATEFRAME') {
                        $this->temp_frame = [];
                        $this->temp_frame_valid = true;
                        $inst_num++;
                    } elseif ($opcode == 'PUSHFRAME') {
                        if (!$this->temp_frame_valid) {
                            exit_with_error(55);
                        }
                        $this->local_frame->push(['frame', $this->temp_frame]);
                        $this->temp_frame_valid = false;
                        $inst_num++;
                    } elseif ($opcode == 'POPFRAME') {
                        if ($this->local_frame->isEmpty()) {
                            exit_with_error(55);
                            return;
                        }
                        $frameData = $this->local_frame->pop();
                        $this->temp_frame = $frameData[1];
                        $this->temp_frame_valid = true;
                        $inst_num++;
                    } elseif ($opcode == 'PUSHS') {
                        list($value, $type_v, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[0]);
                        if (!$initialized) {
                            exit_with_error(56);
                        }
                        $this->data_stack->push([$type_v, $value]);
                        $inst_num++;
                    } elseif ($opcode == 'POPS') {
                        if ($this->data_stack->isEmpty()) {
                            exit_with_error(56);
                        }
                        list($type_v, $value) = $this->data_stack->pop();
                        $var = $this->getVar($inst->args[0]);
                        $var->type_v = $type_v;
                        $var->value = $value;
                        $var->initialized = true;
                        $inst_num++;
                    } else if (in_array($opcode, ['ADD', 'SUB', 'MUL', 'IDIV', 'DIV'])) {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);

                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }

                        if ($type_v1 !== 'int' || $type_v2 !== 'int') {
                            exit_with_error(53);
                            return;
                        }

                        $var->type_v = 'int';
                        $var->initialized = true;

                        switch ($opcode) {
                            case 'ADD':
                                $var->value = $value1 + $value2;
                                break;
                            case 'SUB':
                                $var->value = $value1 - $value2;
                                break;
                            case 'MUL':
                                $var->value = $value1 * $value2;
                                break;
                            case 'IDIV':
                                if ($value2 == 0) {
                                    exit_with_error(57);
                                    return;
                                }
                                $var->value = intdiv($value1, $value2);
                                break;
                        }

                        $inst_num++;
                    } else if ($opcode === 'AND') {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);

                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }

                        if ($type_v1 !== 'bool' || $type_v2 !== 'bool') {
                            exit_with_error(53);
                        }

                        $var->type_v = 'bool';
                        $var->initialized = true;
                        $var->value = ($value1 === 'true' && $value2 === 'true') ? 'true' : 'false';
                        $inst_num++;
                    } elseif ($opcode === 'OR') {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);

                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }

                        if ($type_v1 !== 'bool' || $type_v2 !== 'bool') {
                            exit_with_error(53);
                        }

                        $var->type_v = 'bool';
                        $var->initialized = true;
                        $var->value = ($value1 === 'true' || $value2 === 'true') ? 'true' : 'false';
                        $inst_num++;
                    } elseif ($opcode === 'NOT') {
                        $var = $this->getVar($inst->args[0]);
                        list($value, $type_v, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[1]);

                        if (!$initialized) {
                            exit_with_error(56);
                        }

                        if ($type_v !== 'bool') {
                            exit_with_error(53);
                        }

                        $var->type_v = 'bool';
                        $var->initialized = true;
                        $var->value = ($value === 'false') ? 'true' : 'false';
                        $inst_num++;
                    }
                    if (in_array($opcode, ['LT', 'GT', 'EQ'])) {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);

                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }

                        if (!in_array($type_v1, ['int', 'string', 'bool', 'nil']) || !in_array($type_v2, ['int', 'string', 'bool', 'nil'])) {
                            exit_with_error(53);
                            return;
                        }

                        $var->type_v = 'bool';
                        $var->initialized = true;

                        if (($type_v1 === 'nil' || $type_v2 === 'nil') && $opcode !== 'EQ') {
                            exit_with_error(53);
                        } else {
                            switch ($opcode) {
                                case 'LT':
                                    if ($type_v1 !== $type_v2) {
                                        exit_with_error(53);
                                    } else {
                                        $var->value = ($value1 < $value2) ? 'true' : 'false';
                                    }
                                    break;
                                case 'GT':
                                    if ($type_v1 !== $type_v2) {
                                        exit_with_error(53);
                                    } else {
                                        $var->value = ($value1 > $value2) ? 'true' : 'false';
                                    }
                                    break;
                                case 'EQ':
                                    $var->value = ($value1 == $value2) ? 'true' : 'false';
                                    break;
                            }
                        }

                        $inst_num++;
                    } else if ($opcode === 'READ') {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[1]);

                        // Assuming get_input_line() is implemented to fetch input appropriately
                        $line = $this->get_input_line();
                        if ($line === null) {
                            $var->type_v = 'nil';
                            $var->value = 'nil';
                            $var->initialized = true;
                        } else {
                            switch ($type_v1) {
                                case 'int':
                                    if (filter_var($line, FILTER_VALIDATE_INT) !== false) {
                                        $var->type_v = 'int';
                                        $var->value = (int) $line;
                                    } else {
                                        $var->type_v = 'nil';
                                        $var->value = 'nil';
                                    }
                                    break;
                                case 'bool':
                                    $var->type_v = 'bool';
                                    $var->value = (strtolower($line) === 'true') ? 'true' : 'false';
                                    break;
                                case 'string':
                                    $var->type_v = 'string';
                                    $var->value = $line;
                                    break;
                            }
                            $var->initialized = true;
                        }
                        $inst_num++;
                    } else if ($opcode === 'WRITE') {
                        list($value, $type_v, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[0]);
                        if (!$initialized) {
                            exit_with_error(56);
                        }
                        if ($type_v === 'nil') {
                            echo ''; // Printing nothing for 'nil'
                        } else {
                            echo $value; // Direct output of the value
                        }
                        $inst_num++;
                    } else if ($opcode === 'INT2CHAR') {
                        $var = $this->getVar($inst->args[0]);
                        list($value, $type_v, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[1]);

                        if (!$initialized) {
                            exit_with_error(56);
                        }

                        if ($type_v !== 'int') {
                            exit_with_error(53);
                        }

                        if ($value < 0 || $value > 255) {
                            exit_with_error(58);
                        } else {
                            $var->type_v = 'string';
                            $var->value = chr($value);
                            $var->initialized = true;
                        }
                        $inst_num++;
                    } elseif ($opcode === 'STRI2INT') {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);

                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }

                        if ($type_v1 !== 'string' || $type_v2 !== 'int') {
                            exit_with_error(53);
                        } elseif ($value2 < 0 || $value2 >= strlen($value1)) {
                            exit_with_error(58);
                        } else {
                            $var->type_v = 'int';
                            $var->value = ord($value1[$value2]);
                            $var->initialized = true;
                        }
                        $inst_num++;
                    } elseif ($opcode === 'CONCAT') {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);

                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }

                        if ($type_v1 !== 'string' || $type_v2 !== 'string') {
                            exit_with_error(53);
                        } else {
                            $var->type_v = 'string';
                            $var->value = $value1 . $value2;
                            $var->initialized = true;
                        }
                        $inst_num++;
                    } elseif ($opcode === 'STRLEN') {
                        $var = $this->getVar($inst->args[0]);
                        list($value, $type_v, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[1]);

                        if (!$initialized) {
                            exit_with_error(56);
                        }

                        if ($type_v !== 'string') {
                            exit_with_error(53);
                        } else {
                            $var->type_v = 'int';
                            $var->value = strlen($value);
                            $var->initialized = true;
                        }
                        $inst_num++;
                    }
                    else if ($opcode === 'BREAK') {
                        fwrite(STDERR, "Instruction number: {$inst_num}\n");
                        $inst_num++;
                    }
                    else if ($opcode === 'SETCHAR') {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);
                    
                        if (!$initialized1 || !$initialized2 || !$var->initialized) {
                            exit_with_error(56);
                        }
                    
                        if ($var->type_v !== 'string' || $type_v1 !== 'int' || $type_v2 !== 'string') {
                            exit_with_error(53);
                            return;
                        }
                    
                        if ($value1 > strlen($var->value) - 1 || $value2 === '') {
                            exit_with_error(58);
                        }
                    
                        $var->value[$value1] = $value2[0];
                        $var->type_v = 'string';
                        $var->initialized = true;
                        $inst_num++;
                    }
                    else if ($opcode === 'DPRINT') {
                        list($value1, $type_v1, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[0]);
                    
                        if (!$initialized) {
                            exit_with_error(56);
                        }
                    
                        if ($type_v1 === 'nil') {
                            fwrite(STDERR, '');
                        } else {
                            fwrite(STDERR, $value1);
                        }
                        $inst_num++;
                    }
                    else if ($opcode === 'GETCHAR') {
                        $var = $this->getVar($inst->args[0]);
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);
                    
                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }
                    
                        if ($type_v1 !== 'string' || $type_v2 !== 'int') {
                            exit_with_error(53);
                            return;
                        }
                    
                        if ($value2 > strlen($value1) - 1) {
                            exit_with_error(58);
                        }
                    
                        $var->type_v = 'string';
                        $var->value = $value1[$value2];
                        $var->initialized = true;
                        $inst_num++;
                    }
                    else if ($opcode === 'JUMP') {
                        $label = $inst->args[0];
                    
                        if (!array_key_exists($label->value, $this->labels)) {
                            exit_with_error(52);
                            return;
                        }
                    
                        $inst_num = $this->labels[$label->value];
                    }
                    elseif ($opcode === 'TYPE') {
                        $var = $this->getVar($inst->args[0]);
                        list($value, $type_v, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                    
                        if (!$initialized) {
                            $var->type_v = 'string';
                            $var->value = '';
                        } else {
                            $var->type_v = 'string';
                            $var->value = $type_v;
                        }
                        $var->initialized = true;
                        $inst_num++;
                    }
                    elseif ($opcode === 'EXIT') {
                        list($value, $type_v, $initialized) = $this->getValueAndTypeOfSymbol($inst->args[0]);
                    
                        if (!$initialized) {
                            exit_with_error(56);
                        }
                    
                        if ($type_v !== 'int') {
                            exit_with_error(53);
                        } elseif ($value < 0 || $value > 49) {
                            exit_with_error(57);
                        } else {
                            exit($value);
                        }
                    }
                    else if (in_array($opcode, ['JUMPIFEQ', 'JUMPIFNEQ'])) {
                        $label = $inst->args[0];
                        list($value1, $type_v1, $initialized1) = $this->getValueAndTypeOfSymbol($inst->args[1]);
                        list($value2, $type_v2, $initialized2) = $this->getValueAndTypeOfSymbol($inst->args[2]);
                    
                        if (!$initialized1 || !$initialized2) {
                            exit_with_error(56);
                        }
                    
                        if (!array_key_exists($label->value, $this->labels)) {
                            exit_with_error(52);
                            return;
                        }
                    
                        if ($type_v1 !== $type_v2 && $type_v1 !== 'nil' && $type_v2 !== 'nil') {
                            exit_with_error(53);
                        } else {
                            if ($opcode === 'JUMPIFEQ' && $value1 === $value2) {
                                $inst_num = $this->labels[$label->value];
                            } elseif ($opcode === 'JUMPIFNEQ' && $value1 !== $value2) {
                                $inst_num = $this->labels[$label->value];
                            } else {
                                $inst_num++;
                            }
                        }
                    }
                    else {
                        // unknown instruction
                        exit_with_error(32);
                    }
                    
                }
            }
            public function get_input_line() {
                if ($this->input_is_file) {
                    if (!$this->input_file_is_opened) {
                        $handle = fopen($this->input_file, "r");
                        if ($handle) {
                            while (($line = fgets($handle)) !== false) {
                                $this->input_file_content[] = trim($line);
                            }
                            fclose($handle);
                            $this->input_file_is_opened = true;
                        } else {
                            // Handle error opening the file
                            echo "Error opening the input file.\n";
                            return null;
                        }
                    }
        
                    if ($this->input_file_line_counter < count($this->input_file_content)) {
                        $line = $this->input_file_content[$this->input_file_line_counter];
                        $this->input_file_line_counter++;
                        return $line;
                    } else {
                        return null;
                    }
                } else {
                    return trim(fgets(STDIN));
                }
            }
        }
        
        // TODO: Start your code here
        // Check \IPP\Core\AbstractInterpreter for predefined I/O objects:
        $dom = $this->source->getDOMDocument();
        $xml_root = $dom->documentElement;

        if ($xml_root->nodeName !== 'program') {
            exit_with_error(32);
        }
        $interpeter = new XmlInterpret();
        $interpeter->parse_instructions($xml_root);
        $interpeter->sortInstructions();
        $interpeter->findLabels();
        $interpeter->generate();

        // $val = $this->input->readString()    ;
        // $this->stdout->writeString("stdout");
        // $this->stderr->writeString("stderr");


        // Parse arguments
        return 0;


    }
}