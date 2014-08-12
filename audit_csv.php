<?php require_once 'config.php'; ?>
<?php require_once 'constants.php'; ?>
<?php require_once 'calculation_utils.php'; ?>
<?php require_once 'audit.php'; ?>
<!DOCTYPE html>
<?php 
$dbversion = 0;
// first checks if the version table exists
if(mysql_query('select 1 from `version`;') !== FALSE) {
	$query = mysql_query('select max(`version`) from `version`;');
	$row = mysql_fetch_row($query);
	$dbversion = (int)$row[0];
}
if ($dbversion != CURRENT_VERSION) {
	$show_version_msg = TRUE;
}
$query = mysql_query('SELECT * FROM `transactions` LIMIT 1;');
$row = mysql_fetch_assoc($query);
//$keys = array('tx','block','out','address','amount','topay','actually_paid','date','out_date','state','secret','pot_fee','fee','version');
$keys = array_keys($row);
foreach ($keys as $key) {
	echo $key . ",";
}
echo "\n";
$query = mysql_query('SELECT * FROM `transactions` ORDER BY id DESC, state DESC LIMIT 20;');
while($row = mysql_fetch_assoc($query))
{
	foreach ($keys as $key) {
		echo $row[$key] . ",";
	}
	echo "\n";
}
?>
