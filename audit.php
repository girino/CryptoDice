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
		print ("Transaction: " . $row['tx'] . "\n");
		//print_r($row);
		if ($row['version'] == 1) {
			print ("auditing version 1 algorithm\n");
		} elseif ($row['version'] == 2) {
			print ("auditing version 2 algorithm\n");
			// gets original transaction and checks if it is ok
			$coind_failed = FALSE;
			$coind_transaction = NULL;
			try {
				$coind_transaction = $client->gettransaction($row['tx']);
			} catch (Exception $e) {
				$coind_failed = TRUE;
			}
			if ($coind_failed || $coind_transaction['details'][0]['category'] != 'receive') {
				$result = 'NO_RECEIVE_TX';
			} elseif ($coind_transaction['details'][0]['amount'] != $row['amount'])
				$result = 'AMOUNT_DOES_NOT_MATCH_TX';
			}
		} else {
			print ("unknown algorithm version, unable to audit\n");
			$result= 'ALG_UNKNOWN';
		}
		
		if ($result != 'SUCCESS') {
			print ("ERROR: " . $row['tx'] . ": " . $audit_errors[$result]. "\n");
		}
	}
	print("Finished...\n");
?>
