<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
+----------------------------------------------------------------------+
| PHP Documentation Site Source Code                                   |
+----------------------------------------------------------------------+
| Copyright (c) 1997-2005 The PHP Group                                |
+----------------------------------------------------------------------+
| This source file is subject to version 3.0 of the PHP license,       |
| that is bundled with this package in the file LICENSE, and is        |
| available at through the world-wide-web at                           |
| http://www.php.net/license/3_0.txt.                                  |
| If you did not receive a copy of the PHP license and are unable to   |
| obtain it through the world-wide-web, please send a note to          |
| license@php.net so we can mail you a copy immediately.               |
+----------------------------------------------------------------------+
| Authors: Georg Richter <georg@php.net>                               |
|          Gabor Hojsty <goba@php.net>                                 |
| Docweb port: Nuno Lopes <nlopess@php.net>                            |
|              Mehdi Achour <didou@php.net>                            |
|              Sean Coates <sean@php.net>                              |
+----------------------------------------------------------------------+
$Id$
*/

set_time_limit(0);
$inCli = true;
require_once '../include/init.inc.php';
require_once '../include/lib_url_entities.inc.php';

switch (isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : false) {
    case 'phpdoc':
        $filename = CVS_DIR . '/phpdoc-all/entities/global.ent';
        $entType = 'php';
        break;

    case 'peardoc':
        $filename = CVS_DIR . '/peardoc/global.ent';
        $entType = 'pear';
        break;

    case 'smarty':
        $filename = CVS_DIR . '/smarty/docs/entities/global.ent';
        $entType = 'smarty';
        break;
    
    default:
        echo "Usage: {$_SERVER['argv'][0]} phpdoc|peardoc|smarty\n";
        die();
}


// Schemes currently supported
$schemes = array('http');
if (function_exists('ftp_connect')) {
    $schemes[] = 'ftp';
}

if (extension_loaded('openssl')) {
    $schemes[] = 'https';
}

$dbFile = SQLITE_DIR . "checkent_{$entType}.sqlite";
if (is_file($dbFile) && !unlink($dbFile)) {
    echo "Error removing old database.\n";
    die();
}
if (!($sqlite = sqlite_open($dbFile, 0666))) {
    echo "Error creating database.\n";
}
$sqlCreateMeta = "
    CREATE
    TABLE
        meta_info
        (
            start_time DATETIME,
            end_time DATETIME,
            schemes VARCHAR(100)
        );
";
$sqlCreateChecked = "
    CREATE
    TABLE
        checked_urls
        (
            url_num INT,
            entity VARCHAR(255),
            url VARCHAR(255),
            check_result INT,
            return_val VARCHAR(255)
        );
";
sqlite_query($sqlite, $sqlCreateMeta);
sqlite_query($sqlite, $sqlCreateChecked);

if (!$file = @file_get_contents($filename)) {
    // ouput the html
    echo "<?php include_once '../include/init.inc.php';
           echo site_header('docweb.common.header.checkent'); 
           echo '<h1>No entities found</h1>';
           echo site_footer(); ?>";
    exit;
}

$array = explode('<!-- Obsoletes -->', $file);

// Find entity names and URLs
$schemes_preg = '(?:' . join('|', $schemes) . ')';
preg_match_all("@<!ENTITY\s+(\S+)\s+([\"'])({$schemes_preg}://[^\\2]+)\\2\s*>@U", $array[0], $entities_found);

// These are the useful parts
$entity_names = $entities_found[1];
$entity_urls  = $entities_found[3];

$errors = array();
$numb = 0;

$sql = "
    INSERT
    INTO
        meta_info (start_time, end_time, schemes)
    VALUES
        (". time() .", NULL, '". sqlite_escape_string(implode(',', $schemes)) ."')
";
sqlite_query($sqlite, $sql);

echo "Found: ". count($entity_urls) ."URLs\n"; 

// Walk through entities found
foreach ($entity_urls as $num => $entity_url) {

    ++$numb;
    echo "Checking: $entity_url\n";
    $err = check_url($num, $entity_url);
    $errors[$err[0]][] = $err[1];

    $return_val = isset($err[1][1]) ? $err[1][1] : '';
    $sql = "
        INSERT
        INTO
            checked_urls (url_num, entity, url, check_result, return_val)
        VALUES
            (
                $num,
                '". sqlite_escape_string($entity_names[$num]) ."',
                '". sqlite_escape_string($entity_url) ."',
                {$err[0]},
                '". sqlite_escape_string($return_val) ."'
            )
    ";
    sqlite_query($sqlite, $sql);
}
$sql = "
    UPDATE
        meta_info
    SET
        end_time = ". time() ."
";
sqlite_query($sqlite, $sql);

?>
