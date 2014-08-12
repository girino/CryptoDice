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
		php_sapi_name() === 'cli' || die("Wrong DB version, please run 'php update_db.php'.\n");
		$show_version_msg = TRUE;
	}
	
	function validate_fee($topay, $actually_paid, $fee, $state) {
		if (in_array($state, array(STATE_SENTBACK, STATE_PAID))) {
			if ($actually_paid != charge_fee_internal($topay, $fee)) {
				return 'WRONG_FEE';
			}
		}
		return 'SUCCESS';
	}
	
	function validate_v2($txid, $blockid, $amount, $topay, $pot_fee, $secret, $state) {
		
		$value = 0;
		if (in_array($state, array(STATE_ARCHIVED, STATE_CASH_OUT, STATE_CASH_OUT_READY, STATE_CASH_OUT_INITIAL))) {
			return 'SUCCESS';
		}
		if (in_array($state, array(STATE_POT_REFILL))) {
			$value = 0;
		}
		if (in_array($state, array(STATE_SENTBACK_READY, STATE_SENTBACK))) {
			$value = $amount;
		}
		if (in_array($state, array(STATE_INITIAL, STATE_READY, STATE_PAID))) {
			$value = get_random_v2($amount, $txid, $blockid, $pot_fee);
		}
		if ($topay != $value) {
			return 'INVALID_VALUE';
		}
		return 'SUCCESS';
		
	}

	
	function validate_tx($txid, $amount, $category, $state) {
		global $client;
		
		
		if ($category == 'send' && 
				in_array($state, array(STATE_INITIAL, STATE_READY, STATE_POT_REFILL,
										STATE_SENTBACK_READY, STATE_ARCHIVED, 
										STATE_CASH_OUT_READY, STATE_CASH_OUT_INITIAL))) {
			return 'SUCCESS';
		}
		if ($category == 'receive' &&
				in_array($state, array(STATE_CASH_OUT, STATE_CASH_OUT_READY, STATE_CASH_OUT_INITIAL))) {
			return 'SUCCESS';
		}

		$coind_failed = FALSE;
		$coind_transaction = NULL;
		try {
			$coind_transaction = $client->gettransaction($txid);
		} catch (Exception $e) {
			$coind_failed = TRUE;
		}
		if ($coind_failed || $coind_transaction['details'][0]['category'] != $category) {
			return ($category == 'receive') ? 'NO_RECEIVE_TX' : 'NO_SEND_TX';
		}
		if ($coind_transaction['details'][0]['amount'] != $amount) {
			return ($category == 'receive') ? 'AMOUNT_DOES_NOT_MATCH_TX' : 'AMOUNT_DOES_NOT_MATCH_OUT';
		}
		return 'SUCCESS';
	}
	
	function audit_single_row($row) {
		$result = 'SUCCESS';
		print ("Transaction: " . $row['tx'] . "\n");
		//print_r($row);
		if ($row['version'] == 1) {
			print ("auditing version 1 algorithm\n");
			$result = 'NOT_SUPPORTED';
		} elseif ($row['version'] == 2) {
			print ("auditing version 2 algorithm\n");
			// gets original transaction and checks if it is ok
			$result = validate_tx($row['tx'], $row['amount'], 'receive', $row['state']);
			// validate get_random
			if ($result == 'SUCCESS') {
				$result = validate_v2($row['tx'], $row['block'], $row['amount'], $row['topay'], $row['pot_fee'], $row['secret'], $row['state']);
			}
			if ($result == 'SUCCESS')
				$result = validate_tx($row['out'], - $row['actually_paid'], 'send', $row['state']);
			// validate fee charging
			if ($result == 'SUCCESS') {
				$result = validate_fee($row['topay'], $row['actually_paid'], $row['fee'], $row['state']);
			}
		} else {
			print ("unknown algorithm version, unable to audit\n");
			$result= 'ALG_UNKNOWN';
		}
		return $result;
	}
	
	
	function audit() {
		global $audit_errors;
		
		// Audits transactions and generates report
		print("Auditing transactions...\n");
		
		$query = mysql_query('SELECT * FROM `transactions` ORDER BY `id` DESC');
		while($row = mysql_fetch_assoc($query))
		{
			
			$result = audit_single_row($row);			
			if ($result != 'SUCCESS') {
				print ("ERROR: " . $row['tx'] . ": " . $audit_errors[$result]. "\n");
				// debug info
				print_r($row);
			}
		}
		print("Finished...\n");
	}
	
	if (php_sapi_name() === 'cli') {
		audit();
	}
?>
