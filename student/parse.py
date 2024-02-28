from enum import Enum
import getopt
import sys
import re
from xml.etree.ElementTree import Element, SubElement, tostring
from xml.dom.minidom import parseString


class ErrorCodes(Enum):
    ERR_PARAM = 10
    ERR_INPUT = 11
    ERR_OUTPUT = 12
    ERR_HEADER = 21
    ERR_SRC = 22 
    ERR_SYNTAX = 23
    ERR_INTER = 99 
    SUCCESS = 0 

class OperandTypes(Enum):
    VAR = "var"
    SYMB = "symb"
    TYPE = "type"
    LABEL = "label"


class ErrorExit:
    @staticmethod
    def error_exit(err_code, message):
        sys.stderr.write(f"Error: {message} ({err_code.value}) \n")
        sys.exit(err_code.value)

class Validators:
    @staticmethod
    def is_header(line):
        return line is not None and line.lower() == ".ippcode24"

    @staticmethod
    def is_var(var):
        if "@" not in var:
            return False
        frame,_, name = var.partition("@")

        if frame not in ["TF", "GF", "LF"]:
            return False
        
        if not Validators.is_label(name):
            return False

        return True

    @staticmethod
    def is_symb(symb):
        if "@" not in symb:
            return False
        type,_, literal = symb.partition("@")
        if not Validators.is_type(type):
            return Validators.is_var(symb)

        if type == "bool":
            return re.match(r"^(true|false)$", literal) is not None
        elif type == "nil":
            return re.match(r"^nil$", literal) is not None
        elif type == "int":
            #for default number hex and oct numbers
            if re.match(r"^([-+]?\d+)$", literal) is not None or re.match(r"^([-+]?(0[oO]?[0-7]+))$", literal) is not None or re.match(r"^([-+]?(0[xX][0-9a-fA-F]+))$", literal) is not None:
                return True
            else:
                return False
        elif type == "string":
            pattern = r"\\(\d{3})"
            matches = re.findall(pattern, literal)
            if "\\" in literal:
                parts = literal.split('\\')
                if parts[-1] and not re.match(r"^\d{3}", parts[-1]):
                    return False 
                for match in matches:
                    for i, part in enumerate(parts):
                        if part.startswith(match):
                            parts[i] = parts[i][len(match):]  
                            break
                if len(parts) > len(matches) + 1:
                    return False
        
                return True
            else:
                return True

    @staticmethod
    def is_type(type):
        return re.match(r"^(int|bool|nil|string)$", type) is not None

    @staticmethod
    def is_label(label):
        return re.match(r"^[a-zA-Z_\-$&%*!?][\w\-$&%*!?]*$", label) is not None

#class easier for definng instructions
class InstructionWord:
    def __init__(self, *operand_types):
        self.operands = list(operand_types)

    def get_operands(self):
        return self.operands

    def has_no_operands(self):
        return not bool(self.operands)
    

# all needed instructions from the spec
INSTRUCTION_WORDS = {
    "move": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB),
    "createframe": InstructionWord(),
    "pushframe": InstructionWord(),
    "popframe": InstructionWord(),
    "defvar": InstructionWord(OperandTypes.VAR),
    "call": InstructionWord(OperandTypes.LABEL),
    "return": InstructionWord(),

    "pushs": InstructionWord(OperandTypes.SYMB),
    "pops": InstructionWord(OperandTypes.VAR),
    "add": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "sub": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "mul": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "idiv": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "lt": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "gt": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "eq": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "and": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "or": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "not": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB),
    "int2char": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB),
    "stri2int": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),

    "read": InstructionWord(OperandTypes.VAR, OperandTypes.TYPE),
    "write": InstructionWord(OperandTypes.SYMB),

    "concat": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "strlen": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB),
    "getchar": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),
    "setchar": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB, OperandTypes.SYMB),

    "type": InstructionWord(OperandTypes.VAR, OperandTypes.SYMB),

    "label": InstructionWord(OperandTypes.LABEL),
    "jump": InstructionWord(OperandTypes.LABEL),
    "jumpifeq": InstructionWord(OperandTypes.LABEL, OperandTypes.SYMB, OperandTypes.SYMB),
    "jumpifneq": InstructionWord(OperandTypes.LABEL, OperandTypes.SYMB, OperandTypes.SYMB),
    "exit": InstructionWord(OperandTypes.SYMB),

    "dprint": InstructionWord(OperandTypes.SYMB),
    "break": InstructionWord(),
}

#reads all inputs    
class Reader:
    def __init__(self):
        self.input = []

    def remove_comments(self, line):
        cut_line = line.find('#')
        if cut_line == -1:
            return line.strip()
        
        return line[:cut_line].strip()
    
    def remove_ending(self, line):
        return line.rstrip('\n')


    def remove_spaces(self, line):              
        if re.match(r"^\S+", line):             
            return line         
        return None      

    # deletes /n comments and empty spaces
    def format_line(self, line):
        if type(line) is not str:
            return False
        line = self.remove_ending(line)
        line = self.remove_comments(line)
        line = self.remove_spaces(line)
        return line

    def get_input(self):                                
        while True:
            try:
                line = self.format_line(input())
            except EOFError:
                break
            if line is False:
                continue
            elif line is None:
                continue
            elif line == 'quit':
                break
            else:
                self.input.append(line)

        return self.input
    
class Operand:
    def __init__(self, operand, type_enum):
        if type(type_enum) == OperandTypes:
            self.type = type_enum.value
        else:
            self.type = type_enum

        if self.type == OperandTypes.SYMB.value:
            if Validators.is_var(operand):
                self.type, self.value = "var", operand
            elif Validators.is_symb(operand):
                self.type, _ , self.value = operand.partition('@')
        else:
            self.value = operand

class Instruction:
    general_order = 1

    def __init__(self, destructured_line):
        self.name = destructured_line.pop(0).lower()
        self.operands = []

        if self.name not in INSTRUCTION_WORDS:
            ErrorExit.error_exit(ErrorCodes.ERR_SRC, "no such instruction exist")

        instruction_rule = INSTRUCTION_WORDS[self.name]

        if len(destructured_line) != len(instruction_rule.get_operands()):
            ErrorExit.error_exit(ErrorCodes.ERR_SYNTAX, "Invalid number of operands")

        for key, operand in enumerate(instruction_rule.get_operands()):
            if operand == OperandTypes.VAR and not Validators.is_var(destructured_line[key]):
                ErrorExit.error_exit(ErrorCodes.ERR_SYNTAX, "Invalid variable")
            elif operand == OperandTypes.SYMB and not Validators.is_symb(destructured_line[key]):
                ErrorExit.error_exit(ErrorCodes.ERR_SYNTAX, "Invalid constant")
            elif operand == OperandTypes.TYPE and not Validators.is_type(destructured_line[key]):
                ErrorExit.error_exit(ErrorCodes.ERR_SYNTAX, "Invalid type")
            elif operand == OperandTypes.LABEL and not Validators.is_label(destructured_line[key]):
                ErrorExit.error_exit(ErrorCodes.ERR_SYNTAX, "Invalid label")

            self.operands.append(Operand(destructured_line[key], operand))

        self.order = Instruction.general_order
        Instruction.general_order += 1

    def get_operands(self):
        return self.operands
    def get_order(self):
        return self.order
    def get_name(self):
        return self.name
    
class Analyser:
    def __init__(self, input_lines):
        self.input = input_lines
        self.instructions = []

    def get_instructions(self):
        if len(self.input) == 0:
            ErrorExit.error_exit(ErrorCodes.ERR_HEADER, "invalid header")
        if not Validators.is_header(self.input[0]):
            ErrorExit.error_exit(ErrorCodes.ERR_HEADER, "invalid header")

        # Remove the header line
        self.input.pop(0)  
        #check if only one header
        if self.input:
            if Validators.is_header(self.input[0]):
                ErrorExit.error_exit(ErrorCodes.ERR_SYNTAX, "too many headers")

        for line in self.input:
            self.instructions.append(Instruction(line.split()))

        return self.instructions


class GenXML:
    def __init__(self, instructions):
        self.output = Element("program")
        self.output.set("language", "IPPcode24")
        self.instructions = instructions


    def gen_intruction(self, instruction):
        instruction_element = SubElement(self.output, "instruction")
        instruction_element.set("order", str(instruction.get_order()))
        instruction_element.set("opcode", instruction.get_name().upper()) 

        for key, operand in enumerate(instruction.get_operands(), start=1):
            operand_template = SubElement(instruction_element, f"arg{key}")
            if(operand.type in ['symb']):
                operand.type = 'var'
            operand_template.set("type", operand.type.lower())
            operand_template.text = operand.value

    def prettify(self, elem):
        rough_string = tostring(elem, 'utf-8')
        reparsed = parseString(rough_string)
        return reparsed.toprettyxml(indent="  ", encoding="UTF-8").decode('utf-8')
    
    def gen(self):
        for instruction in self.instructions:
            self.gen_intruction(instruction)
        
        return self.prettify(self.output)


def main():
    shortopts = "h"
    longopts = ["help"]

    try:
        options, _ = getopt.getopt(sys.argv[1:], shortopts, longopts)
    except getopt.GetoptError as err:
        ErrorExit.error_exit(ErrorCodes.ERR_PARAM, str(err))
    for o, a in options:
        if o in ("-h", "--help"):
            if len(options) != 1:
                ErrorExit.error_exit(ErrorCodes.ERR_PARAM, "'--help' doesn't have any other arguments or flags")

            print("""
            Usage: python parser.py [options] < [file]

            Otions:
            --help or -h\tprints help info
            """)
            exit(0)

    input_reader = Reader()
    instructions = input_reader.get_input()

    input_analyser = Analyser(instructions)
    instructions = input_analyser.get_instructions() 

    doc_generator = GenXML(instructions)
    xml_doc = doc_generator.gen()

    print(xml_doc)

    exit(0)

if __name__ == "__main__":
    main()
