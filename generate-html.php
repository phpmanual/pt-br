#!/usr/bin/env php
<?php

error_reporting(~E_NOTICE);

// Long runtime
set_time_limit(0);

// A file is criticaly "outdated' if
define("ALERT_REV",   10); // translation is 10 or more revisions behind the en one
define("ALERT_SIZE",   3); // translation is  3 or more kB smaller than the en one
define("ALERT_DATE", -30); // translation is 30 or more days older than the en one

// Revision marks used to flag files
define("REV_UPTODATE", 1); // actual file
define("REV_NOREV",    2); // file with revision comment without revision
define("REV_CRITICAL", 3); // criticaly old / small / outdated
define("REV_OLD",      4); // outdated file
define("REV_NOTAG",    5); // file without revision comment
define("REV_NOTRANS",  6); // file without translation

define("REV_CREDIT",   7); // only used in translators list
define("REV_WIP",      8); // only used in translators list

$maintainers = [];
$crediteds = [];

// Colors used to mark files by status (colors for the above types)
$CSS = array(
  REV_UPTODATE => "act",
  REV_NOREV    => "norev",
  REV_CRITICAL => "crit",
  REV_OLD      => "old",
  REV_NOTAG    => "wip",
  REV_NOTRANS  => "wip",
  REV_CREDIT   => "wip",
  REV_WIP      => "wip",
);

function init_revisions() {
	 global $CSS;
	 return array_fill_keys(array_keys($CSS), 0);
}

function init_files_by_maint($persons) {
  $result = array();
  foreach($persons as $item) {
    $result[$item['nick']] = init_revisions();
  }

 	return $result;
}

$file_sizes_by_mark = $files_by_mark = init_revisions();

// Option for the link to svn.php.net:
define('SVN_OPT', '&amp;view=patch');
define('SVN_OPT_NOWS', '');

// Initializing variables from parameters
$LANG = 'pt_BR';
$MAINT = "";
$SHOW_UPTODATE = FALSE;
if ($argc == 3) {
	if ($argv[2] == '--show-uptodate') {
         $SHOW_UPTODATE = TRUE;
    } else {
         $MAINT = $argv[2];	
	}
} elseif ($argc == 4) {
    $MAINT = $argv[2];
    $SHOW_UPTODATE = ($argv[3] == '--show-uptodate');
}

$DOCDIR = "./doc-pt_BR/";
// =========================================================================
// Functions to get revision info and credits from a file
// =========================================================================

// Grabs the revision tag and stores credits from the file given
function get_tags($file, $val = "en-rev") {

  // Read the first 500 chars. The comment should be at
  // the begining of the file
  $fp = @fopen($file, "r") or die ("Unable to read $file.");
  $line = fread($fp, 500);
  fclose($fp);

  // Check for English SVN revision tag (. is for $ in the preg!),
  // Return if this was needed (it should be there)
  if ($val == "en-rev") {
    preg_match("/<!-- .Revision: (\d+) . -->/", $line, $match);
    return $match[1];
  }

  // Handle credits (only if no maintainer is specified)
  if ($val == "\\S*") {

    global $files_by_maint;

    // Find credits info, let more credits then one,
    // using commas as list separator
    if (preg_match("'<!--\s*CREDITS:\s*(.+)\s*-->'U", $line, $match_credit)) {
      // Explode with commas a separators
      $credits = explode(",", $match_credit[1]);

      // Store all elements
      foreach ($credits as $num => $credit) {
          $files_by_maint[trim($credit)][REV_CREDIT]++;

          // rogeriopradoj
          global $crediteds;
          if (isset($crediteds[trim($credit)])) {
            $crediteds[trim($credit)]++;
          } else {
            $crediteds[trim($credit)] = 1;
          }
      }
    }
  }

  // No match before the preg
  $match = array();

  // Check for the translations "revision tag"
  preg_match ("/<!--\s*EN-Revision:\s*(\d+)\s*Maintainer:\s*("
              . $val . ")\s*Status:\s*(.+)\s*-->/U",
              $line,
              $match
  );

  // The tag with revision number is not found so search
  // for n/a revision comment (comment where revision is not known)
  if (count($match) == 0) {
      preg_match ("'<!--\s*EN-Revision:\s*(n/a)\s*Maintainer:\s*("
                  . $val . ")\s*Status:\s*(.+)\s*-->'U",
                  $line,
                  $match
      );
  }

  // rogeriopradoj
  global $maintainers;
  if (isset($maintainers[$match[2]])) {
    $maintainers[$match[2]]++;
  } else {
    $maintainers[$match[2]] = 1;
  }

  // Return with found revision info (number, maint, status)
  return $match;

} // get_tags() function end


// =========================================================================
// Functions to check file status in translated directory, and store info
// =========================================================================

// Checks a file, and gather status info
function get_file_status($file) {

  // The information is contained in these global arrays and vars
  global $DOCDIR, $LANG, $MAINT, $SHOW_UPTODATE, $files_by_mark, $files_by_maint;
  global $file_sizes_by_mark;
  global $missing_files, $missing_tags, $using_rev;

  // Transform english file name to translated file name
  $trans_file = preg_replace("'^".$DOCDIR."en/'", $DOCDIR.$LANG."/", $file);

  // If we cannot find the file, we push it into the missing files list
  if (!@file_exists($trans_file)) {
    $files_by_mark[REV_NOTRANS]++;
    $trans_name = substr($trans_file, strlen($DOCDIR) + strlen($LANG) + 1);
    $size = intval(filesize($file)/1024);
    $missing_files[$trans_name] = array( $size );
    $file_sizes_by_mark[REV_NOTRANS] += $size;
    // compute en-tags just if they're needed in the WIP-Table
    if($using_rev) {
            $missing_files[$trans_name][] = get_tags($file);
    }
    return FALSE;
  }

  // No specific maintainer, check for a revision tag
  if (empty($MAINT)) {
    $trans_tag = get_tags($trans_file, "\\S*");
  }
  // If we need to check for a specific translator
  else {
    // Get translated files tag, with maintainer
    $trans_tag = get_tags($trans_file, $MAINT);

    // If this is a file belonging to another
    // maintainer, than we would not like to
    // deal with it anymore
    if (count($trans_tag) == 0) {
      $trans_tag = get_tags($trans_file, "\\S*");
      // We found a tag for another maintainer
      if (count($trans_tag) > 0) {
          return FALSE;
      }
    }
  }

  // Compute sizes and diffs
  $en_size    = intval(filesize($file) / 1024);
  $trans_size = intval(mb_strlen(file_get_contents($trans_file), 'UTF-8') / 1024);
  $size_diff  = intval($en_size) - intval($trans_size);

  // If we found no revision tag, then collect this
  // file in the missing tags list
  if (count($trans_tag) == 0) {
    $files_by_mark[REV_NOTAG]++;
    $file_sizes_by_mark[REV_NOTAG] += $en_size;
    $missing_tags[] = array(substr($trans_file, strlen($DOCDIR)), $en_size, $trans_size, $size_diff);
    return FALSE;
  }

  // Distribute values in separate vars for further processing
  list(, $this_rev, $this_maint, $this_status) = $trans_tag;

  // Get English file revision
  $en_rev = get_tags($file);

  // If we have a numeric revision number (not n/a), compute rev. diff
  if (is_numeric($this_rev)) {
    $rev_diff   = intval($en_rev) - intval($this_rev);
    $trans_rev  = $this_rev;
    $en_rev     = $en_rev;
  } else {
    // If we have no numeric revision, make all revision
    // columns hold the rev from the translated file
    $rev_diff = $trans_rev = $this_rev;
    $en_rev   = $en_rev;
  }

  // Compute times and diffs
  $en_date    = intval((time() - filemtime($file)) / 86400);
  $trans_date = intval((time() - filemtime($trans_file)) / 86400);
  $date_diff  = $en_date - $trans_date;

  // If the file is up-to-date
  if ($rev_diff === 0 && trim($this_status) === "ready") {
     $status_mark = REV_UPTODATE;
  }
  // Or make decision on file category by revision, date and size
  elseif ($rev_diff >= ALERT_REV || $size_diff >= ALERT_SIZE || $date_diff <= ALERT_DATE) {
    $status_mark = REV_CRITICAL;
  } elseif ($rev_diff === "n/a") {
    $status_mark = REV_NOREV;
  } elseif ($rev_diff === 0) {
    $status_mark = REV_WIP;
  } else {
    $status_mark = REV_OLD;
  }

  // Store files by status, and by maintainer too
  $files_by_mark[$status_mark]++;
  $files_by_maint[$this_maint][$status_mark]++;
  $file_sizes_by_mark[$status_mark] += $en_size;

  if (REV_UPTODATE === $status_mark && !$SHOW_UPTODATE) {
    return FALSE;
  }

  return array(
      "full_name"  => $file,
      "short_name" => basename($trans_file),
      "revision"   => array($en_rev,  $trans_rev,  $rev_diff),
      "size"       => array($en_size, $trans_size, $size_diff),
      "date"       => array($en_date, $trans_date, $date_diff),
      "maintainer" => $this_maint,
      "status"     => $this_status,
      "mark"       => $status_mark
  );

} // get_file_status() function end

// =========================================================================
// A function to check directory status in translated directory
// =========================================================================

// Check the status of files in a diretory of phpdoc XML files
// The English directory is passed to this function to check
function get_dir_status($dir) {

  global $DOCDIR;

  // Collect files and diretcories in these arrays
  $directories = array();
  $files       = array();

  // Open the directory
  $handle = @opendir($dir);

  // Walk through all names in the directory
  while ($file = @readdir($handle)) {

    if (
    (!is_dir($dir.'/' .$file) && !in_array(substr($file, -3), array('xml','ent')) && substr($file, -13) != 'PHPEditBackup' )
    || strpos($file, 'entities.') === 0
    || $dir == $DOCDIR.'en/chmonly/' || $dir == $DOCDIR.'en/internals/' || $dir == $DOCDIR.'en/internals2/'
    || $file == 'contributors.ent' || $file == 'contributors.xml'
    || ($dir == $DOCDIR.'en/appendices/' && ($file == 'reserved.constants.xml' || $file == 'extensions.xml'))
    || $file == 'README'
    || $file == 'DO_NOT_TRANSLATE'
    || $file == 'rsusi.txt'
    || $file == 'missing-ids.xml'
    || $file == 'license.xml'
    || $file == 'versions.xml'
    ) {
      continue;
    }

    if ($file != '.' && $file != '..' && $file != '.svn' && $dir != '/functions') {
      if (is_dir($dir.'/' .$file)) {
          $directories[] = $file;
      } elseif (is_file($dir.'/' .$file)) {
          $files[] = $file;
      }
    }

  }

  // Close the directory
  @closedir($handle);

  // Sort files and directories
  sort($directories);
  sort($files);

  // Go through files first
  $dir_status = array();
  foreach ($files as $file) {
    // If the file status is OK, append the status info
    if ($file_status = get_file_status($dir.$file)) {
        $dir_status[] = $file_status;
    }
  }

  // Then go through subdirectories, merging all the info
  // coming from subdirs to one array
  foreach ($directories as $file) {
    $dir_status = array_merge(
        $dir_status,
        get_dir_status($dir.$file.'/')
    );
  }

  // Return with collected file info in
  // this dir and subdirectories [if any]
  return $dir_status;

} // get_dir_status() function end


// Check for files removed in the EN tree, but still living in the translation
function get_old_files($dir) {

  global $DOCDIR, $LANG;

  // Collect files and diretcories in these arrays
  $directories = array();
  $files       = array();

  $special_files = array(
    // french
    'LISEZ_MOI.txt',
    'TRADUCTIONS.txt',
    'Translators',
    'translation.xml'
    // todo: add all missing languages
  );

  // Open the directory
  $handle = @opendir($dir);

  // Walk through all names in the directory
  while ($file = @readdir($handle)) {

    // If we found a file with one or two point as a name,
    // a SVN directory, or an editor backup file skip the file
    if (preg_match("/^\.{1,2}/", $file)
        || $file == '.svn'
        || substr($file, -1) == '~' // Emacs backup file
        || substr($file, -4) == '.new'
       ) {
      continue;
    }
    // skip this files
    if (in_array($file, $special_files)) {
      continue;
    }

    // Collect files and directories
    if (is_dir($dir.$file)) {
      $directories[] = $file;
    } else {
      $files[] = $file;
    }
  }

  // Close the directory
  @closedir($handle);

  // Sort files and directories
  sort($directories);
  sort($files);

  // Go through files first
  $old_files_status = array();
  foreach ($files as $file) {

    $en_dir = preg_replace("'^".$DOCDIR.$LANG."/'", $DOCDIR."en/", $dir);

    if (!@file_exists($en_dir.$file) ) {
      $old_files_status[$dir.$file] = array(0=>intval(filesize($dir.$file)/1024));
    }

  }

  // Then go through subdirectories, merging all the info
  // coming from subdirs to one array
  foreach ($directories as $file) {
    $old_files_status = array_merge(
        $old_files_status,
        get_old_files($dir.$file.'/')
    );
  }

  return $old_files_status;

} // get_old_files() function end


// =========================================================================
// Functions to read in the translation.xml file and process contents
// =========================================================================

// Get a multidimensional array with tag attributes
function parse_attr_string ($tags_attrs) {

  $tag_attrs_processed = array();

  // Go through the tag attributes
  foreach($tags_attrs as $attrib_list) {

    // Get attr name and values
    preg_match_all("!(.+)=\\s*([\"'])\\s*(.+)\\2!U", $attrib_list, $attribs);

    // Assign all attributes to one associative array
    $attrib_array = array();
    foreach ($attribs[1] as $num => $attrname) {
      $attrib_array[trim($attrname)] = trim($attribs[3][$num]);
    }

    // Collect in order of tags received
    $tag_attrs_processed[] = $attrib_array;

  }

  // Retrun with collected attributes
  return $tag_attrs_processed;

} // parse_attr_string() end

// Parse the translation.xml file for
// translation related meta information
function parse_translation($DOCDIR, $LANG, $MAINT) {

  global $files_by_mark;

  // Path to find translation.xml file, set default values,
  // in case we can't find the translation file
  $translation_xml = $DOCDIR.$LANG."/translation.xml";
  $output_charset  = 'iso-8859-1';
  $translation     = array(
      "intro"    => "",
      "persons"  => array(),
      "files"    => array(),
      "allfiles" => array(),
  );

  // Check for file availability, return with default
  // values, if we cannot find the file
  if (!@file_exists($translation_xml)) {
    return array($output_charset, $translation);
  }

  // Else go on, and load in the file, replacing all
  // space type chars with one space
  $txml = join("", file($translation_xml));
  $txml = preg_replace("/\\s+/", " ", $txml);

  // Get intro text (different for a persons info and
  // for a whole group info page)
  if (empty($MAINT)) {
    preg_match("!<intro>(.+)</intro>!s", $txml, $match);
    $translation["intro"] = trim($match[1]);
  } else {
    $translation["intro"] = "Personal Statistics for ".$MAINT;
  }

  // Get encoding for the output, from the translation.xml
  // file encoding (should be the same as the used encoding
  // in HTML)
  preg_match("!<\?xml(.+)\?>!U", $txml, $match);
  $xmlinfo = parse_attr_string($match);
  $output_charset = $xmlinfo[1]["encoding"];

  // Get persons list preg pattern, only check for a specific
  // maintainer, if the users asked for it
  if (empty($MAINT)) {
    $pattern = "!<person(.+)/\\s?>!U";
  } else {
    $pattern = "!<person([^<]+nick=\"".$MAINT."\".+)/\\s?>!U";
  }

  // Find all persons matching the pattern
  preg_match_all($pattern, $txml, $matches);
  $translation['persons'] = parse_attr_string($matches[1]);

  // Get list of work in progress files
  if (empty($MAINT)) {

    // Get all wip files
    preg_match_all("!<file(.+)/\\s?>!U", $txml, $matches);
    $translation['files'] = parse_attr_string($matches[1]);

    // Provide info about number of WIP files
    $files_by_mark[REV_WIP] += count($translation['files']);

  } else {

    // Only check for a specific maintainer, if we were asked to
    preg_match_all("!<file([^<]+person=\"".$MAINT."\".+)/\\s?>!U", $txml, $matches);
    $translation['files'] = parse_attr_string($matches[1]);

    // Other maintainers wip files need to be cleared from
    // available files list in the future, so store that info too.
    preg_match_all("!<file(.+)/\\s?>!U", $txml, $matches);
    $translation['allfiles'] = parse_attr_string($matches[1]);

    // Provide info about number of WIP files
    $files_by_mark[REV_WIP] += count($translation['allfiles']);

  }

  // Return with collected info in two vars
  return array($output_charset, $translation);

} // parse_translation() function end()

// =========================================================================
// Start of the program execution
// =========================================================================

// Check for directory validity
if (!@is_dir($DOCDIR . $LANG)) {
  die("The $LANG language code is not valid");
}

// Parse translation.xml file for more information
list($charset, $translation) = parse_translation($DOCDIR, $LANG, $MAINT);

// Get all files status
$files_status = get_dir_status($DOCDIR."en/");

$translators = array_unique(
  array_merge(
    array_keys($maintainers),
    array_keys($crediteds)
  )
);
$translatorsListedInXml    = array_column($translation["persons"], 'nick');
$translatorsNotListedInXml = array_diff($translators, $translatorsListedInXml);

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Credits and maintainers - PHPDOC pt_BR</title>

        <link
            href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"
            rel="stylesheet">
        <link
            href="https://netdna.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css"
            rel="stylesheet">
        <link
            rel="stylesheet"
            href="https://mottie.github.io/tablesorter/css/theme.bootstrap.css">

        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries
        -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]> <script
        src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script> <script
        src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
        <style>
        body {
          min-height: 2000px;
          padding-top: 70px;
        }
        </style>
    </head>
    <body>
        <div id="container">
            <!-- Fixed navbar -->
            <nav class="navbar navbar-default navbar-fixed-top">
              <div class="container">
                <div class="navbar-header">
                  <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                  </button>
                  <a class="navbar-brand" href="#">Credits and maintainers - PHPDOC pt_BR</a>
                </div>
                <div id="navbar" class="navbar-collapse collapse">
                  <ul class="nav navbar-nav">
                    <li><a href="#maintainers">Maintainers</a></li>
                    <li><a href="#crediteds">Crediteds</a></li>
                    <li><a href="#all-translators">All Translators</a></li>
                    <li><a href="#listed-translators">Listed Translators</a></li>
                    <li><a href="#unlisted-translators">Unlisted Translators</a></li>
                  </ul>
                </div><!--/.nav-collapse -->
              </div>
            </nav>
            <div class="table-responsive">
                <table id="maintainers" style="width: auto !important;"
                    class="well table table-hover table-condensed table-striped table-bordered">
                    <caption>Maintainers</caption>
                    <thead>
                        <tr>
                            <th>Maintainer</th>
                            <th>Files</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($maintainers as $maintainer => $files): ?>
                        <tr>
                            <td><?= $maintainer ?></td>
                            <td><?= $files ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive">
                <table id="crediteds" style="width: auto !important;"
                    class="well table table-hover table-condensed table-striped table-bordered">
                    <caption>Crediteds</caption>
                    <thead>
                        <tr>
                            <th>Credited</th>
                            <th>Files</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($crediteds as $credited => $files): ?>
                        <tr>
                            <td><?= $credited ?></td>
                            <td><?= $files ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive">
                <table id="all-translators" style="width: auto !important;"
                    class="well table table-hover table-condensed table-striped table-bordered">
                    <caption>All Translators (Mainteners + Crediteds)</caption>
                    <thead>
                        <tr>
                            <th>Translator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($translators as $translator): ?>
                        <tr>
                            <td><?= $translator ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive">
                <table id="listed-translators" style="width: auto !important;"
                    class="well table table-hover table-condensed table-striped table-bordered">
                    <caption>Listed Translators (doc-pt_BR/pt_BR/translation.xml)</caption>
                    <thead>
                        <tr>
                            <th>Translator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($translatorsListedInXml as $translator): ?>
                        <tr>
                            <td><?= $translator ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive">
                <table id="unlisted-translators" style="width: auto !important;"
                    class="well table table-hover table-condensed table-striped table-bordered">
                    <caption>Unlisted Translators (doc-pt_BR/pt_BR/translation.xml)</caption>
                    <thead>
                        <tr>
                            <th>Translator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($translatorsNotListedInXml as $translator): ?>
                        <tr>
                            <td><?= $translator ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Latest compiled and minified JS -->
        <script src="https://code.jquery.com/jquery.js"></script>
        <script src="https://netdna.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
        <script
            src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.22.5/js/jquery.tablesorter.min.js"></script>
        <script
            src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.22.5/js/jquery.tablesorter.widgets.min.js"></script>
        <script>
            $(function () {
                $("table").tablesorter({
                    theme: "bootstrap",
                    headerTemplate: '{content} {icon}',
                    widgets: ["uitheme"],
                    sortList: [
                        [0, 0]
                    ]
                })
            });
        </script>
    </body>
</html>