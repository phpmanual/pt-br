#!/bin/bash
set -e # Exit with nonzero exit code if anything fails

svn checkout https://svn.php.net/repository/phpdoc/modules/doc-pt_BR

./generate-html.php > out/index.html

