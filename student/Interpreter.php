<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;
use IPP\Student\XmlInterpret;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {   
        // TODO: Start your code here
        // Check \IPP\Core\AbstractInterpreter for predefined I/O objects:
        $dom = $this->source->getDOMDocument();
        $xml_root = $dom->documentElement;
        if ($xml_root->nodeName !== 'program') {
            // also check name for ippcode 24
            exit_with_error(32);
        }
        $interpeter = new XmlInterpret($this->input,$this->source,$this->stdout,$this->stderr);
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