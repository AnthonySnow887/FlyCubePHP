# Tests

This directory contains various tests and additional utilities.  

## Page load speed test

You can use a script 'test_page_load.sh' to check page load speed.
This script is based on 'curl' and 'wget' utilities.
'wget' is used with flags to fully download all content of a web page, including scripts and images.

### Usage
```bash
FlyCubePHP/tests> sh test_page_load.sh -h

Help:

  --help - show this help (-h)

Input args:
  1 - host (if empty - used 127.0.0.1:8080)
  2 - application URL prefix (may be empty)
  3 - number of query iterations (may be empty; default: 10)

Examples:
  #> sh test_page_load.sh

  #> sh test_page_load.sh 127.0.0.1:8080 my-project

  #> sh test_page_load.sh 127.0.0.1:8080 my-project 5

  #> sh test_page_load.sh 127.0.0.1:8080 "" 5

  #> sh test_page_load.sh "" "" 5

```

### Work example
```bash
FlyCubePHP/tests> sh test_page_load.sh 127.0.0.1:8080 my-project 15
 LOAD: 1.05 sec (for iteration 1)
 LOAD: 1.07 sec (for iteration 2)
 LOAD: 1.05 sec (for iteration 3)
 LOAD: 1.06 sec (for iteration 4)
 LOAD: 1.06 sec (for iteration 5)
 LOAD: 1.07 sec (for iteration 6)
 LOAD: 1.10 sec (for iteration 7)
 LOAD: 1.06 sec (for iteration 8)
 LOAD: 1.06 sec (for iteration 9)
 LOAD: 1.06 sec (for iteration 10)
 LOAD: 1.07 sec (for iteration 11)
 LOAD: 1.08 sec (for iteration 12)
 LOAD: 1.07 sec (for iteration 13)
 LOAD: 1.08 sec (for iteration 14)
 LOAD: 1.08 sec (for iteration 15)
-----------------------------------
TOTAL: 16.02 sec (for 15 iterations)
 MEAN: 1.068 sec
```
