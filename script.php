<?php
	include('config.php');
	include('constants.php');
	require_once 'jsonRPCClient.php';
	$client = new jsonRPCClient('http://' . $rpc['login'] . ':' . $rpc['password'] . '@' . $rpc['ip'] . ':' . $rpc['port'] . '/') or die('Error: could not connect to RPC server.');

	$lastPayout = time();
	$adresses = array();
	
	function dice_round($value) {
		global $config;
		
		return round((float)$value, $config['precision']);
	}
	
	function charge_fee($value) {
		global $config;
		
		return dice_round($value * (1 - $config['fee']));
	}

	function getAddress($trans)
	{
		global $client;
		$address = "";

		$details = $client->getrawtransaction($trans["txid"], 1);

		$vintxid = $details['vin'][0]['txid'];
		$vinvout = $details['vin'][0]['vout'];

		try {
			$transactionin = $client->getrawtransaction($vintxid, 1);
		}
		catch (Exception $e) {
			die("Error with getting transaction details.\nYou should add 'txindex=1' to your .conf file and then run the daemon with the -reindex parameter.");
		}
		
		if ($vinvout == 1)
			$vinvout = 0;
		else
			$vinvout = 1;
		
		$address = $transactionin['vout'][!$vinvout]['scriptPubKey']['addresses'][0];
		return $address;
	}

function get_random($value, $txid, $blockid) {
	global $config;
	
	if (is_null($txid)) {
		return 0;
	}

	// get two first bytes
	$hash = hash_hmac('sha256', strtolower($txid), strtolower($blockid));
	$hash = hash_hmac('sha256', $hash, $config['hash_secret']); // double hash
	$hash = hash_hmac('sha256', $hash, $config['hash_secret']); // double hash
	$hex = substr($hash, 32, 4); // not sure if initial zeros are pruned
	$dec = hexdec ( $hex );
	// homogeneous in [0, 2) interval
	$ret = $dec / 0x8000;
	
	// pot fee removal
	if ($config['pot_fee'] > 0 && $ret > 1) {
		$ret = 1 + (($ret - 1) * (1.0 - $config['pot_fee'])); // charges only the winnings
	}
	print("hex = " . $hex . ", dec = " . $dec . ", ret = " . $ret . "\n");
	
	return dice_round($ret * $value);
}
	
	//while(true)
	if(true)
	{
		// Parsing and adding new transactions to database
		print("Parsing transactions...\n");
		$transactions = $client->listtransactions($config['ponziacc'], 100);
		$i = 0;
		foreach ($transactions as $trans)
		{
			echo("Parsing " . ++$i . "\n");
			
			if ($trans['category'] != "receive" || $trans["confirmations"] < $config['confirmations'])
				continue;
			
			if ($trans['amount'] < 0)
				continue;
			
			$query = mysql_query('SELECT * FROM `transactions` WHERE `tx` = "'.$trans['txid'].'";');
			if (!mysql_fetch_assoc($query)) // Transaction not found in DB
			{
				$amount = $trans['amount'];
				if ($trans['amount'] > $config['max'] || $trans['amount'] < $config['min'])
				{
					$topay = $amount; // fee charged when actually paying
					print("will pay back: " . $topay . "\n");
					$address = $config['ownaddress'];
					if ($config['sendback'])
						$address = getAddress($trans);
					$state = STATE_SENTBACK_READY;
				} else {
					$topay = get_random($amount, $trans['txid'], $trans['blockhash']);
					print("Randomized to: " . $topay . "\n");
					$address = getAddress($trans);
					$state = STATE_INITIAL;
				}
				if ($trans['blocktime'] <= $config['begin_date']) {
					print ("Ignoring transactions before ". $config['begin_date'] . "\n");
					$state = STATE_ARCHIVED;
				}
				$SQL = "INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . $state . "', '" . $trans['txid'] . "', " . (time()) . ", '" . $trans['blockhash'] . "', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ");";
				if (!mysql_query($SQL)) {
						print("ERROR INSERTING!!!\nSQL: " . $SQL);
				}
				print("Transaction added! [" . $amount . "]\n");
			}
		}
		
		$query = mysql_query("SELECT SUM(amount) FROM `transactions` where `state` < ". STATE_SENTBACK_READY .";");
		$query = mysql_fetch_row($query);
		$money = $query[0];
		
		$query = mysql_query("SELECT SUM(topay) FROM `transactions` WHERE `state` > ". STATE_INITIAL ." and `state` < ". STATE_SENTBACK_READY .";");
		$query = mysql_fetch_row($query);
		$money -= $query[0];
		
		$query = mysql_query("SELECT * FROM `transactions` WHERE `state` = ". STATE_INITIAL ." AND `state` < ". STATE_SENTBACK_READY ." AND `topay` > 0 ORDER BY `id` ASC;");
		while($row = mysql_fetch_assoc($query))
		{
			print("Money: " . $money . "\n");
			if ($money < $row['topay'])
				break;
				
			mysql_query("UPDATE `transactions` SET `state` = ". STATE_READY ." WHERE `id` = " . $row['id'] . ";");
			$money -= $row['topay'];
		}

		// tim
		$query = mysql_query('SELECT MAX(`out_date`) FROM `transactions`;');
		$query = mysql_fetch_row($query);
		$lastPayout = $query[0];
		print("lastpayout = " . $lastPayout . "\n");
		print("elapsed = " . (time() - $lastPayout) . "\n");
		
		// Paying out
		if (time() - $lastPayout > $config['payout-check'])
		{
			$query = mysql_query('SELECT * FROM `transactions` WHERE `state` = '. STATE_READY .' OR `state` = '. STATE_SENTBACK_READY .' ORDER BY `date` ASC;');
			while($row = mysql_fetch_assoc($query))
			{
				// checks if payback
				if ($row['state'] == STATE_SENTBACK_READY) {
					$value = charge_fee($row['topay']);
					$txout = $client->sendtoaddress( $row['address'], $value );
					mysql_query("UPDATE `transactions` SET `state` = ". STATE_SENTBACK .", `out` = '" . $txout . "', `out_date` = '" . (time()) . "' WHERE `id` = " . $row['id'] . ";");
					print($row['topay'] . " " . $config['val'] ." paid back to " . $row['address'] . ".\n");
				} else {
				
					$value = charge_fee($row['topay']);
					$txout = $client->sendfrom( $config['ponziacc'], $row['address'], $value );
					mysql_query("UPDATE `transactions` SET `state` = ". STATE_PAID .", `out` = '" . $txout . "', `out_date` = '" . (time()) . "' WHERE `id` = " . $row['id'] . ";");
					print($row['topay'] . " " . $config['val'] ." sent to " . $row['address'] . ".\n");
				}
			}
		}

		echo ("Finished...\n");
		//sleep(20);
	}
?>
