<?php
	include('config.php');
	include('constants.php');
	require_once 'jsonRPCClient.php';
	$client = new jsonRPCClient('http://' . $rpc['login'] . ':' . $rpc['password'] . '@' . $rpc['ip'] . ':' . $rpc['port'] . '/') or die('Error: could not connect to RPC server.');

	$lastPayout = time();
	$adresses = array();
	
	dice_round(1);
	
	function dice_round($value) {
		global $config;
		print_r($config);
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

function get_random($txid, $blockid, $secret, $pot_fee) {
	if (is_null($txid)) {
		return 0;
	}

	// get two first bytes
	$hash = hash_hmac('sha256', strtolower($txid), strtolower($blockid));
	$hash = hash_hmac('sha256', $hash, $secret); // double hash
	$hash = hash_hmac('sha256', $hash, $secret); // double hash
	$hex = substr($hash, 32, 4); // not sure if initial zeros are pruned
	$dec = hexdec ( $hex );
	// homogeneous in [0, 2) interval
	$ret = $dec / 0x8000;
	
	// pot fee removal
	if ($pot_fee > 0 && $ret > 1) {
		$ret = 1 + (($ret - 1) * (1.0 - $pot_fee)); // charges only the winnings
	}
	print("hex = " . $hex . ", dec = " . $dec . ", ret = " . $ret . "\n");
	return $ret;
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
				if ($trans['amount'] > $config['max'] || $trans['amount'] < $config['min'])
				{

					$amount = $trans['amount'];
					$topay = $amount * (1-$config['fee']);
					print("will pay back: " . $topay . "\n");
					$address = $config['ownaddress'];
					if ($config['sendback'])
						$address = getAddress($trans);
					
					if (!mysql_query("INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . STATE_SENTBACK_READY . "', '" . $trans['txid'] . "', " . (time()) . ", '" . $trans['blockhash'] . "', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ");")) {
							print("ERROR INSERTING!!!\nSQL: INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . STATE_SENTBACK_READY . "', '" . $trans['txid'] . "', " . (time()) . ", '" . $trans['blockhash'] . "', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ");");
					}
					print($trans['amount'] + " - Payment will be sent back!\n");
				} else {
				
					$amount = $trans['amount'];
					// TODO: get random seed from db
					$topay = $amount * get_random($trans['txid'], $trans['blockhash'], $config['hash_secret'], $config['pot_fee']);
					print("Randomized to: " . $topay . "\n");
					$address = getAddress($trans);
	
					if (!mysql_query("INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . STATE_INITIAL . "', '" . $trans['txid'] . "', " . (time()) . ", '" . $trans['blockhash'] . "', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ");")) {
							print("ERROR INSERTING!!!\nSQL: INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . STATE_INITIAL . "', '" . $trans['txid'] . "', " . (time()) . ", '" . $trans['blockhash'] . "', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ");");
					}
					print("Transaction added! [" . $amount . "]\n");
				}
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
					$value = $row['topay'] - ($row['topay'] * $config['fee']);
					$client->sendtoaddress( $row['address'], round((float)$value , 4) );
					mysql_query("UPDATE `transactions` SET `state` = ". STATE_SENTBACK .", `out` = '" . $txout . "', `out_date` = '" . (time()) . "' WHERE `id` = " . $row['id'] . ";");
					print($row['topay'] . " " . $config['val'] ." paid back to " . $row['address'] . ".\n");
				} else {
				
					$value = $row['topay'] - ($row['topay'] * $config['fee']);
					$txout = $client->sendfrom( $config['ponziacc'], $row['address'], round((float)$value , 4) );
					mysql_query("UPDATE `transactions` SET `state` = ". STATE_PAID .", `out` = '" . $txout . "', `out_date` = '" . (time()) . "' WHERE `id` = " . $row['id'] . ";");
					print($row['topay'] . " " . $config['val'] ." sent to " . $row['address'] . ".\n");
				}
			}
		}

		echo ("Finished...\n");
		//sleep(20);
	}
?>
