<?php

namespace IPP\Student;

use IPP\Student\ErrorExit;
use IPP\Student\Stack;
use IPP\Student\Argument;
use IPP\Student\Variable;
use IPP\Student\Instruction;

class XmlInterpret
{
    private $order_numbers = [];
    private $instructions = [];
    private $labels = [];
    private $global_frame = [];
    private $temp_frame = [];
    private $temp_frame_valid = false;
    private $local_frame;
    private $call_stack;
    private $data_stack;
    private $input;

    //for writing to stdout and stderr
    private $stdout;
    private $stderr;


    public function __construct($input, $source, $stdout, $sterr)
    {
        $this->stdout = $stdout;
        $this->stderr = $sterr;
        $this->input = $input;
        $this->call_stack = new Stack();
        $this->local_frame = new Stack();
        $this->data_stack = new Stack();
    }
    //TODO rewrite
    // public static function check_int_in_str($string)
    // {
    //     if ($string[0] === '-' || $string[0] === '+') {
    //         return ctype_digit(substr($string, 1));
    //     }
    //     return ctype_digit($string);
    // }
    // public function sortInstructions()
    // {
    //     usort($this->instructions, function ($a, $b) {
    //         return $a->order <=> $b->order;
    //     });
    // }
    public function getInstructions($node)
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'instruction') {
                $order = trim($child->getAttribute('order'));
                $opcode = trim($child->getAttribute('opcode'));

                if (!$order || !$opcode || !ctype_digit($order) || !filter_var($order, FILTER_VALIDATE_INT) || in_array((int) $order, $this->order_numbers) || (int) $order < 1) {
                    //echo "line 55\n";
                    ErrorExit::exit_with_error(32, $this->stderr);
                }

                $this->order_numbers[] = (int) $order;
                $instruction = new Instruction($opcode, (int) $order);

                foreach ($child->childNodes as $argNode) {
                    if ($argNode->nodeType === XML_ELEMENT_NODE) {
                        $this->getArgument($instruction, $argNode);

                    }
                }
                // if (count($instruction->args) - 1 > 1) {
                //     $instruction->sortArguments();
                //     foreach ($instruction->args as $arg) {
                //         echo $arg->arg_order;
                //     }
                //     $counter = 1;
                //     foreach ($instruction->args as $arg) {
                //         if ($arg->arg_order != $counter) {
                //             echo "line 76\n";

                //             ErrorExit::exit_with_error(32);
                //         }
                //         $counter++;
                //     }
                // }




                $this->instructions[] = $instruction;
            }
        }
    }
    public function getArgument($inst, $arg)
    {
        $allowedTags = ['arg1', 'arg2', 'arg3'];
        if (!in_array($arg->tagName, $allowedTags)) {
            //echo "line 91\n";

            ErrorExit::exit_with_error(32, $this->stderr);
        }

        $arg_order = intval(substr(trim($arg->tagName), 3));
        $type = trim($arg->getAttribute('type'));
        $text = trim($arg->nodeValue);
        switch ($type) {
            case 'var':
                if (strlen($text) < 3 || !in_array(substr($text, 0, 3), ['GF@', 'LF@', 'TF@'])) {
                    ErrorExit::exit_with_error(31, $this->stderr);
                }
                $inst->addArgument(new Argument('var', null, $text, $arg_order));
                break;

            case 'string':
                $value = $text ?: '';
                $inst->addArgument(new Argument('string', $value, null, $arg_order));
                break;

            case 'int':
                if (!ctype_digit($text)) {
                    //echo "line 116\n";

                    ErrorExit::exit_with_error(32, $this->stderr);
                }
                $inst->addArgument(new Argument('int', intval($text), null, $arg_order));
                break;

            case 'bool':
                if (!in_array($text, ['false', 'true'])) {
                    ErrorExit::exit_with_error(31, $this->stderr);
                }
                $inst->addArgument(new Argument('bool', $text, null, $arg_order));
                break;

            case 'nil':
                if ($text !== "nil") {
                    ErrorExit::exit_with_error(31, $this->stderr);
                }
                $inst->addArgument(new Argument('nil', 'nil', null, $arg_order));
                break;

            case 'label':
                if (!$text) {
                    ErrorExit::exit_with_error(31, $this->stderr);
                }
                $inst->addArgument(new Argument('label', $text, null, $arg_order));
                break;

            case 'type':
                if (!in_array($text, ['int', 'string', 'bool'])) {
                    ErrorExit::exit_with_error(31, $this->stderr);
                }
                $inst->addArgument(new Argument($text, null, null, $arg_order));
                break;

            default:
                //echo "line 152\n";

                ErrorExit::exit_with_error(32, $this->stderr);
        }
    }
    public function checkForLabels()
    {
        $instNum = 0;
        foreach ($this->instructions as $inst) {
            if ($inst->opcode == 'LABEL') {
                $label = $inst->args[0];
                if (array_key_exists($label->value, $this->labels)) {
                    ErrorExit::exit_with_error(52, $this->stderr);
                }
                $this->labels[$label->value] = $instNum;
            }
            $instNum++;
        }
    }
    private function &checkForFrame($arg)
    {
        switch ($arg->frame) {
            case 'GF':
                return $this->global_frame;
            case 'TF':
                if (!$this->temp_frame_valid) {
                    ErrorExit::exit_with_error(55, $this->stderr);
                }
                return $this->temp_frame;
            case 'LF':
                if ($this->local_frame->isEmpty()) {
                    ErrorExit::exit_with_error(55, $this->stderr);
                    return;
                }
                return $this->local_frame->top()[1];
            default:
                ErrorExit::exit_with_error(52, $this->stderr);
            
        }
    }

    // rewrite to separate functions inside argument class
    private function getValueAndTypeAndInitOfSymbol($arg)
    {
        if ($arg->kind === 'var') {
            $var = &$this->getVar($arg);
            return [$var->value, $var->type_of_variable, $var->initialized];
        } else if (in_array($arg->kind, ['string', 'int', 'bool', 'nil'])) {
            return [$arg->value, $arg->kind, true];
        }
    }
    private function &getVar($arg)
    {
        $frame = &$this->checkForFrame($arg);
        foreach ($frame as $var) {
            // Assuming $var->pure_name and a method $arg->getPureName() exist
            if ($var->pure_name === $arg->getPureName()) {
                return $var;
            }
        }
        //echo "" . $arg->name . "line 206\n";
        ErrorExit::exit_with_error(54, $this->stderr);
    }

    private function formatString($string) {
        $replacements = [
            '\\n' => "\n", 
            '\\r' => "\r", 
            '\\t' => "\t", 
            '\\\\' => "\\",
        ];

        foreach ($replacements as $search => $replace) {
            $string = str_replace($search, $replace, $string);
        }

        $string = preg_replace_callback('/\\\\([0-9]{3})/', function($matches) {
            return chr((int)$matches[1]);
        }, $string);

        return $string;
    }
    public function generate()
    {
        $inst_num = 0;
        while (true) {
            if ($inst_num > count($this->instructions) - 1) {
                break;
            }
            $inst = $this->instructions[$inst_num];
            $opcode = strtoupper($inst->opcode);
            if ($opcode == 'LABEL') {
                $inst_num++;
                continue;
            } else if ($opcode == 'JUMP') {
                $label = $inst->args[0];
                if (!array_key_exists($label->value, $this->labels)) {
                    ErrorExit::exit_with_error(52, $this->stderr);
                }
                $inst_num = $this->labels[$label->value];
                continue;
            } else if ($opcode == 'DEFVAR') {
                $arg = $inst->args[0];
                $frame = &$this->checkForFrame($arg);
                foreach ($frame as $var) {
                    if ($var->pure_name === $arg->getPureName()) {
                        ErrorExit::exit_with_error(52, $this->stderr);
                    }
                }
                $frame[] = new Variable($arg->name, null, false, null);
                $inst_num++;
            } else if ($opcode == "MOVE") {
                $arg = $inst->args[0];
                $var = &$this->getVar($arg);
                $symbol = $inst->args[1];
                $value = $this->getValueAndTypeAndInitOfSymbol($symbol);
                if ($value[2] === null) {
                    ErrorExit::exit_with_error(56, $this->stderr);
                }
                $var->value = $value[0];
                $var->type_of_variable = $value[1];
                $var->initialized = true;
                $inst_num++;
            } else if ($opcode == "CALL") {
                $label = $inst->args[0];
                $this->call_stack->push('int', $inst_num);
                if (!array_key_exists($label->value, $this->labels)) {
                    ErrorExit::exit_with_error(52, $this->stderr);
                }
                $inst_num = $this->labels[$label->value];
            } else if ($opcode == "RETURN") {
                if ($this->call_stack->isEmpty()) {
                    ErrorExit::exit_with_error(56, $this->stderr);
                }
                $num = $this->call_stack->pop()[1];
                $inst_num = $num + 1;
            } else if ($opcode == 'CREATEFRAME') {
                $this->temp_frame = [];
                $this->temp_frame_valid = true;
                $inst_num++;
            } elseif ($opcode == 'PUSHFRAME') {
                if (!$this->temp_frame_valid) {
                    ErrorExit::exit_with_error(55, $this->stderr);
                }
                $this->local_frame->push('frame', $this->temp_frame);
                $this->temp_frame_valid = false;
                $inst_num++;
            } elseif ($opcode == 'POPFRAME') {
                if ($this->local_frame->isEmpty()) {
                    ErrorExit::exit_with_error(55, $this->stderr);
                    return;
                }
                $this->temp_frame = $this->local_frame->pop()[1];
                $this->temp_frame_valid = true;
                $inst_num++;
            } elseif ($opcode == 'PUSHS') {
                list($value, $type_of_variable, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[0]);
                if (!$initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);
                }
                $this->data_stack->push($type_of_variable, $value);
                $inst_num++;
            } elseif ($opcode == 'POPS') {
                if ($this->data_stack->isEmpty()) {
                    ErrorExit::exit_with_error(56, $this->stderr);
                }
                list($type_of_variable, $value) = $this->data_stack->pop();
                $var = &$this->getVar($inst->args[0]);
                $var->type_of_variable = $type_of_variable;
                $var->value = $value;
                $var->initialized = true;
                $inst_num++;
            } else if (in_array($opcode, ['ADD', 'SUB', 'MUL', 'IDIV'])) {
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2) {
                    ErrorExit::exit_with_error(56, $this->stderr);
                }

                if ($type_of_variable1 !== 'int' || $type_of_variable2 !== 'int') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                    return;
                }

                $var->type_of_variable = 'int';
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
                            ErrorExit::exit_with_error(57, $this->stderr);
                            return;
                        }
                        $var->value = intdiv($value1, $value2);
                        break;
                }

                $inst_num++;
            } else if (in_array($opcode, ['AND', 'OR'])) {
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable1 !== 'bool' || $type_of_variable2 !== 'bool') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                }

                $var->type_of_variable = 'bool';
                $var->initialized = true;
                if ($opcode === 'AND')
                    $var->value = ($value1 === 'true' && $value2 === 'true') ? 'true' : 'false';
                else
                    $var->value = ($value1 === 'true' || $value2 === 'true') ? 'true' : 'false';
                $inst_num++;
            } elseif ($opcode === 'NOT') {
                $var = &$this->getVar($inst->args[0]);
                list($value, $type_of_variable, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);

                if (!$initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable !== 'bool') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                }

                $var->type_of_variable = 'bool';
                $var->initialized = true;
                $var->value = ($value === 'false') ? 'true' : 'false';
                $inst_num++;
            } else if (in_array($opcode, ['LT', 'GT', 'EQ'])) {
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }
                if ($type_of_variable1 !== $type_of_variable2) {
                    if ($opcode === 'EQ' && ($type_of_variable1 === 'nil' || $type_of_variable2 === 'nil')) {
                        // continue
                    } else {
                        ErrorExit::exit_with_error(53, $this->stderr);
                    }
                }
                if (!in_array($type_of_variable1, ['int', 'string', 'bool', 'nil']) || !in_array($type_of_variable2, ['int', 'string', 'bool', 'nil'])) {
                    ErrorExit::exit_with_error(53, $this->stderr);
                    return;
                }

                $var->type_of_variable = 'bool';
                $var->initialized = true;

                if (($type_of_variable1 === 'nil' || $type_of_variable2 === 'nil') && $opcode !== 'EQ') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                } else {
                    switch ($opcode) {
                        case 'LT':
                            if ($type_of_variable1 !== $type_of_variable2) {
                                ErrorExit::exit_with_error(53, $this->stderr);
                            } else {
                                $var->value = ($value1 < $value2) ? 'true' : 'false';
                            }
                            break;
                        case 'GT':
                            if ($type_of_variable1 !== $type_of_variable2) {
                                ErrorExit::exit_with_error(53, $this->stderr);
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
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);

                // Assuming get_input_line() is implemented to fetch input appropriately
                $line = $this->getInput($type_of_variable1);
                if ($line === null) {
                    $var->type_of_variable = 'nil';
                    $var->value = 'nil';
                    $var->initialized = true;
                } else {
                    switch ($type_of_variable1) {
                        case 'int':
                            if (filter_var($line, FILTER_VALIDATE_INT) !== false) {
                                $var->type_of_variable = 'int';
                                $var->value = (int) $line;
                            } else {
                                $var->type_of_variable = 'nil';
                                $var->value = 'nil';
                            }
                            break;
                        case 'bool':
                            $var->type_of_variable = 'bool';
                            $var->value = (strtolower($line) === 'true') ? 'true' : 'false';
                            break;
                        case 'string':
                            $var->type_of_variable = 'string';
                            $var->value = $line;
                            break;
                    }
                    $var->initialized = true;
                }
                $inst_num++;
            } else if ($opcode === 'WRITE') {
                list($value, $type_of_variable, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[0]);
                if (!$initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);
                }
                if ($type_of_variable === 'nil') {
                    echo ''; // Printing nothing for 'nil'
                } else if ($type_of_variable === 'bool') {
                    if ($value === 'true')
                        $this->stdout->writeBool(true);
                    else if ($value === 'false')
                        $this->stdout->writeBool(false);
                } else if ($type_of_variable === 'int') {
                    $this->stdout->writeInt($value);
                } else if ($type_of_variable === 'string') {
                    $formatted_text = $this->formatString($value);
                    $this->stdout->writeString($formatted_text);
                }
                $inst_num++;
            } else if ($opcode === 'INT2CHAR') {
                $var = &$this->getVar($inst->args[0]);
                list($value, $type_of_variable, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);

                if (!$initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable !== 'int') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                }

                if ($value < 0 || $value > 255) {
                    ErrorExit::exit_with_error(58, $this->stderr);
                } else {
                    $var->type_of_variable = 'string';
                    $var->value = chr($value);
                    $var->initialized = true;
                }
                $inst_num++;
            } elseif ($opcode === 'STRI2INT') {
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable1 !== 'string' || $type_of_variable2 !== 'int') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                } elseif ($value2 < 0 || $value2 >= strlen($value1)) {
                    ErrorExit::exit_with_error(58, $this->stderr);
                } else {
                    $var->type_of_variable = 'int';
                    $var->value = ord($value1[$value2]);
                    $var->initialized = true;
                }
                $inst_num++;
            } elseif ($opcode === 'CONCAT') {
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable1 !== 'string' || $type_of_variable2 !== 'string') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                } else {
                    $var->type_of_variable = 'string';
                    $var->value = $value1 . $value2;
                    $var->initialized = true;
                }
                $inst_num++;
            } elseif ($opcode === 'STRLEN') {
                $var = &$this->getVar($inst->args[0]);
                list($value, $type_of_variable, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);

                if (!$initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable !== 'string') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                } else {
                    $var->type_of_variable = 'int';
                    $var->value = strlen($value);
                    $var->initialized = true;
                }
                $inst_num++;
            } else if ($opcode === 'BREAK') {
                $this->stderr->writeString("Instruction number: {$inst_num}\n");
                $inst_num++;
            } else if ($opcode === 'SETCHAR') {
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2 || !$var->initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($var->type_of_variable !== 'string' || $type_of_variable1 !== 'int' || $type_of_variable2 !== 'string') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                    return;
                }

                if ($value1 > strlen($var->value) - 1 || $value2 === '') {
                    ErrorExit::exit_with_error(58, $this->stderr);
                }

                $var->value[$value1] = $value2[0];
                $var->type_of_variable = 'string';
                $var->initialized = true;
                $inst_num++;
            } else if ($opcode === 'DPRINT') {
                list($value1, $type_of_variable1, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[0]);

                if (!$initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable1 === 'nil') {
                    $this->stderr->writeString("");
                } else {
                    $this->stderr->writeString($value1);
                }
                $inst_num++;
            } else if ($opcode === 'GETCHAR') {
                $var = &$this->getVar($inst->args[0]);
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable1 !== 'string' || $type_of_variable2 !== 'int') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                    return;
                }

                if ($value2 > strlen($value1) - 1) {
                    ErrorExit::exit_with_error(58, $this->stderr);
                }

                $var->type_of_variable = 'string';
                $var->value = $value1[$value2];
                $var->initialized = true;
                $inst_num++;
            } else if ($opcode === 'JUMP') {
                $label = $inst->args[0];

                if (!array_key_exists($label->value, $this->labels)) {
                    ErrorExit::exit_with_error(52, $this->stderr);
                    return;
                }

                $inst_num = $this->labels[$label->value];
            } elseif ($opcode === 'TYPE') {
                $var = &$this->getVar($inst->args[0]);
                list($value, $type_of_variable, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);

                if (!$initialized) {
                    $var->type_of_variable = 'string';
                    $var->value = '';
                } else {
                    $var->type_of_variable = 'string';
                    $var->value = $type_of_variable;
                }
                $var->initialized = true;
                $inst_num++;
            } elseif ($opcode === 'EXIT') {
                list($value, $type_of_variable, $initialized) = $this->getValueAndTypeAndInitOfSymbol($inst->args[0]);

                if (!$initialized) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if ($type_of_variable !== 'int') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                } else if ($value < 0 || $value > 9) {
                    ErrorExit::exit_with_error(57, $this->stderr);
                } else {
                    exit($value);
                }
            } else if (in_array($opcode, ['JUMPIFEQ', 'JUMPIFNEQ'])) {
                $label = $inst->args[0];
                list($value1, $type_of_variable1, $initialized1) = $this->getValueAndTypeAndInitOfSymbol($inst->args[1]);
                list($value2, $type_of_variable2, $initialized2) = $this->getValueAndTypeAndInitOfSymbol($inst->args[2]);

                if (!$initialized1 || !$initialized2) {
                    ErrorExit::exit_with_error(56, $this->stderr);

                }

                if (!array_key_exists($label->value, $this->labels)) {
                    ErrorExit::exit_with_error(52, $this->stderr);
                    return;
                }

                if ($type_of_variable1 !== $type_of_variable2 && $type_of_variable1 !== 'nil' && $type_of_variable2 !== 'nil') {
                    ErrorExit::exit_with_error(53, $this->stderr);
                } else {
                    if ($opcode === 'JUMPIFEQ' && $value1 === $value2) {
                        $inst_num = $this->labels[$label->value];
                    } elseif ($opcode === 'JUMPIFNEQ' && $value1 !== $value2) {
                        $inst_num = $this->labels[$label->value];
                    } else {
                        $inst_num++;
                    }
                }
            } else {
                // unknown instruction
                ErrorExit::exit_with_error(32, $this->stderr);
            }

        }
    }
    public function getInput($type)
    {
        if ($this->input) {
            //check for int bool
            if ($type === 'int') {
                return $this->input->readInt();
            } elseif ($type === 'bool') {
                return $this->input->readBool();
            } elseif ($type === 'string') {
                return $this->input->readString();
            }
        } else {
            return trim(fgets(STDIN));
            //I guess check for int bool string
        }
    }
}