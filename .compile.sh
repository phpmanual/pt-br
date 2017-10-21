#!/usr/bin/env sh
# make the script fail for any failed command
set -e
# make the script display the commands it runs to help debugging failures
set -x

echo ""
echo "svn"
svn checkout https://svn.php.net/repository/phpdoc/modules/doc-pt_BR &>/dev/null
echo ""
echo "Done."

echo ""
echo "generate html"
./generate-html.php > out/index.html
echo ""
echo "Done."
