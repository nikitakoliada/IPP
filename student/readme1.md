Implementační dokumentace k 1. úloze do IPP 2023/2024
Jméno a příjmení: Nikita Koliada
Login: xkolia00

# Python Parser

 The `parse.py` file with --help option (to print Usage) takes an input file breakes it into smaller pieces, validates its syntax  and generates a XML file or raises an error

# Classes that were used in the development:

## Instruction

This class represents an instruction.
- `__init__(self, line)`: Initializes an `Instruction` instance with the given line. 

## Operand
This class represents an instruction.
- `__init__(self, operand, type_enum)`: Initializes an `Operand` instance and parts it in type and value. 
- 
## ErrorExit

This class is used to handle errors and exit the program.

- `error_exit(error_code, message)`: Exits the program with the given error code and message.

## InstructionWord / INSTRUCTION_WORDS
All possible instructions from the specicification

## Validators

This class is used to validate input lines. 

### Methods

- `is_var(line)`: Checks if the given line is a valid variable.
- `is_symb(line)`: Checks if the given line is a valid symbol.
- `is_type(line)`: Checks if the given line is a valid type.
- `is_label(line)`: Checks if the given line is a valid label.

## GenXML

This class is used to generate xml output code with function like `gen_intruction`, `prettify`

## Analyser

This class is used to analyze input and to check headers and to add instructions to the list of instructions that than will be used for generation of XML

## Reader
This class is used to read input and to get rid of comments, spaces, endings

