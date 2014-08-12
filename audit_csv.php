<?php require_once 'config.php'; ?>
<?php require_once 'constants.php'; ?>
<?php require_once 'calculation_utils.php'; ?>
<?php require_once 'audit.php'; ?>
<?php 
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data.csv');

// create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

$dbversion = 0;
// first checks if the version table exists
if(mysql_query('select 1 from `version`;') !== FALSE) {
	$query = mysql_query('select max(`version`) from `version`;');
	$row = mysql_fetch_row($query);
	$dbversion = (int)$row[0];
}
if ($dbversion != CURRENT_VERSION) {
	die( "Wrong DB version\n");
}

$query = mysql_query('SELECT * FROM `transactions` LIMIT 1;');
$row = mysql_fetch_assoc($query);
// output the column headings
fputcsv($output, array_keys($row));

$query = mysql_query('SELECT * FROM `transactions` ORDER BY id DESC, state DESC');
while($row = mysql_fetch_assoc($query)) 
	fputcsv($output, $row);
?>
