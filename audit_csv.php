<?php require_once 'config.php'; ?>
<?php require_once 'constants.php'; ?>
<?php require_once 'calculation_utils.php'; ?>
<?php require_once 'audit.php'; ?>
<?php require_once 'check_db_ver.php';?>
<?php 
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data.csv');

// create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

$query = mysql_query('SELECT * FROM `transactions` LIMIT 1;');
$row = mysql_fetch_assoc($query);
// output the column headings
fputcsv($output, array_keys($row));

$query = mysql_query('SELECT * FROM `transactions` ORDER BY id DESC, state DESC');
while($row = mysql_fetch_assoc($query)) 
	fputcsv($output, $row);
?>
