<?php
require_once 'config.php';
require_once 'constants.php';

$dbversion = 0;
// first checks if the version table exists
if(mysql_query('select 1 from `version`;') !== FALSE) {
	$query = mysql_query('select max(`version`) from `version`;');
	$row = mysql_fetch_row($query);
	$dbversion = (int)$row[0];
}
if ($dbversion != DB_VERSION) {
	php_sapi_name() === 'cli' || die("Wrong DB version, please run 'php update_db.php'.\n");
	$show_version_msg = TRUE;
}
?>