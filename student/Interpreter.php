<?php

namespace IPP\Student;


use IPP\Student\ErrorExit;
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
            ErrorExit::exit_with_error(32);

        }
        $language = $xml_root->getAttribute('language');
        if (strtolower($language) !== 'ippcode24') {
            ErrorExit::exit_with_error(32);
        }
        $interpeter = new XmlInterpret($this->input, $this->source, $this->stdout, $this->stderr);
        $interpeter->getInstructions($xml_root);
        $interpeter->sortInstructions();
        $interpeter->checkForLabels();
        $interpeter->generate();

        // $val = $this->input->readString()    ;
        // $this->stdout->writeString("stdout");
        // $this->stderr->writeString("stderr");


        // Parse arguments
        return 0;


    }
}