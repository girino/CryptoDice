<?php 
	include('constants.php');
	include('config.php');
	require_once 'audit.php';
	$json = array();
	
	if ($_GET['what'] == "all" || $_GET['what'] == "")
		$query = mysql_query('SELECT * FROM `transactions` ORDER BY id DESC, state DESC LIMIT 20;');
	else
		$query = mysql_query('SELECT * FROM `transactions` WHERE `tx` = "' . mysql_real_escape_string($_GET['what']) . '" LIMIT 1;');

	$transactions = array();
	while($row = mysql_fetch_assoc($query)) 
	{
		$row['timestamp'] = $row['date'];
		$row['date'] = date("d/m/Y H:i:s", $row['date']);
		$row['audit'] = audit_single_row($row);
		$transactions[] = $row;
	}
	
	$json['transactions'] = $transactions;

	echo json_encode($json);
?>
