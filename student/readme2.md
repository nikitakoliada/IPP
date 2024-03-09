Implementační dokumentace k 2. úloze do IPP 2023/2024
Jméno a příjmení: Nikita Koliada
Login: xkolia00

# PHP XML interpret

The program is an interpreter for programs written in in XML representation of the IPPcode24 language.

## Usage

```
php interpret.php --source=[source_file] --input=[input_file] [--help]
```

`--source` specifies a path to the XML file containing a sorce program to be interpreted.

`--input` specifies a path to the file containing an input data for the interpret.

If any of source or input were not provided, the interpreter will wait for input or source from the stdin.

## UML diagram of classes

https://mermaid.live/view#pako:eNqNVNuOmzAQ_RXkp6wKUW40CdqXVduVIm0vUtpVVSEhByaJFWMj26zKRvn3DgSIYZNV4QHmzDCXc8YcSSwTIAGJOdX6M6M7RdNQhKKynQe1y1MQ5hgKB68PByaSwKGiqO0XynOwAUHTjr1VPYCqXSRVAipwmDA1uAPzI1fwDWMHd6E4XRpYCW1UHhsmRdODzMqW7c_f5MMiGosqRW_3kSTNcAMMD9pR7-oALZVpMN3ram1ofHDqhjxdWp1yZ-gJhN1Uluv9oCLsZ5FhLzgYEzvXuXDYlM5kNmjemf6SZqZobXP2Wc08U8XohkPDj8HskdxGLzVuT50hy1Ffo77NBDN4U85eAdXeSMmBiiuKW038TvlKGFCZgmZZvEqWSOTpplTHpoddVO3q5HG6Ad7DdlxuKI8aCS2PgTR7H0cWOOsP4XEZXxJWYtYOhHlU62njCTX0Gs5ElhuLOxQ-kW8QUMrmt9wsa691qy0eg3YnS4oCe_1d5-qaxnuID49SPVXEDfrwYznj9QXHas-lmA8iKRcSHysU_vt2XaQbyd_5Rt3yCVDUgD3OquSn3dfLbc3l3A-HnfUJnF8adCia_M6959lEBM4nBVinE4I5OiHnFOdzeqtA7cXsXW-bvjlZ_xdzo0pzE5ekoFLKEvzbVkckJGYPKYQkwNeEqkNIkCiMo7mR60LEJMCBwCV5hvsH9c-5ASFhRqqv9d-7fLgko-KPlG0ImiQ4kr8kmMz94WI8X_qzxdxfLv2l75KCBOP5cOxPZtPRx8nUH03n_uzkktcqw2i4mM0RnaBrOV3649npHx7EDhk

## Implementation

For the php interpret was used OOP aproach - including many files with many classes that were shown on the diagram, the main class is XmlInterpret which basically creates all objects of classes - first getting all instruction than adding all arguments to this instruction ( which also include separating attrivute type and assigning it to argument ), in the received instructions than searching for labels, and the biggest part is interpreting the received instructions to IPPcode24 using specification of the task to determine how excatly to interpret each instruction, definition of variable was used(DEFVAR), some it had to output text(result) to stdout(WRITE), for READ getInput() read either from a file or stdin.

## Error handlig

During the whole implementation significant part of code checks for validity of xml source file - if there were some unexpected or wrong format - the eror was raised by a static method exit_with_error which exits the program with an exit code ( which are determined in the IPP_CORE)