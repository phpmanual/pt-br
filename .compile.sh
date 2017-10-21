#!/usr/bin/env sh
# make the script fail for any failed command
set -e
# make the script display the commands it runs to help debugging failures
set -x

svn checkout https://svn.php.net/repository/phpdoc/modules/doc-pt_BR doc-pt_BR 1> /dev/null \
    && tree -L 2 \
    && mkdir -p out \
    && ./generate-html.php > out/index.html
