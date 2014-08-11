<?php
	require_once 'config.php';
	require_once 'constants.php';
	require_once 'jsonRPCClient.php';
	require_once 'calculation_utils.php';
	$client = new jsonRPCClient('http://' . $rpc['login'] . ':' . $rpc['password'] . '@' . $rpc['ip'] . ':' . $rpc['port'] . '/') or die('Error: could not connect to RPC server.');

	$dbversion = 0;
	// first checks if the version table exists
	if(mysql_query('select 1 from `version`;') !== FALSE) {
		$query = mysql_query('select max(`version`) from `version`;');
		$row = mysql_fetch_row($query);
		$dbversion = (int)$row[0];
	}
	if ($dbversion != CURRENT_VERSION) {
		die("Wrong DB version, please run 'php update_db.php'.\n");
	}
	
	// Audits transactions and generates report
	print("Auditing transactions...\n");
	
	$query = mysql_query('SELECT * FROM `transactions` ORDER BY `id` DESC');
	while($row = mysql_fetch_assoc($query))
	{
		
		$result = 'SUCCESS';
		print ("Transaction: " . $row['tx']);
		//print_r($row);
		if ($row['version'] == 1) {
			print ("auditing version 1 algorithm");
		} elseif ($row['version'] == 2) {
			print ("auditing version 1 algorithm");
		} else {
			print ("unknown algorithm version, unable to audit");
			$result= 'ALG_UNKNOWN';
		}
		
		if ($result != 'SUCCESS') {
			print ("ERROR: " . $row['tx'] . ": " . $audit_errors[$result]);
		}
	}
	print("Finished...\n");
?>
