#!/bin/bash
set -e # Exit with nonzero exit code if anything fails

echo ""
echo "svn checkout https://svn.php.net/repository/phpdoc/modules/doc-pt_BR &>/dev/null"
svn checkout https://svn.php.net/repository/phpdoc/modules/doc-pt_BR &>/dev/null
echo ""
echo "Done."

echo ""
echo "./generate-html.php > out/index.html"
./generate-html.php > out/index.html
echo ""
echo "Done."
