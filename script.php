<?php
	require_once 'config.php';
	require_once 'constants.php';
	require_once 'jsonRPCClient.php';
	require_once 'calculation_utils.php';
	require_once 'check_db_ver.php';
	
	$client = new jsonRPCClient('http://' . $rpc['login'] . ':' . $rpc['password'] . '@' . $rpc['ip'] . ':' . $rpc['port'] . '/') or die('Error: could not connect to RPC server.');
	
	$adresses = array();

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
			// if the address is nod the right one, this is pot refilling.
			if ($trans['address'] != $config['address']) {
				$topay = 0;
				$address = getAddress($trans);
				$state = STATE_POT_REFILL;
			} 
			elseif ($trans['amount'] > $config['max'] || $trans['amount'] < $config['min'])
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
			$SQL = "INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`, `version`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . $state . "', '" . $trans['txid'] . "', " . (time()) . ", '" . $trans['blockhash'] . "', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ", " . ALGO_VERSION . ");";
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

	$query = mysql_query("SELECT * FROM `transactions` WHERE `state` = ". STATE_INITIAL ." AND `topay` > 0 ORDER BY `id` ASC;");
	while($row = mysql_fetch_assoc($query))
	{
		print("Money: " . $money . "\n");
		if ($money < $row['topay'])
			break;
			
		mysql_query("UPDATE `transactions` SET `state` = ". STATE_READY ." WHERE `id` = " . $row['id'] . ";");
		$money -= $row['topay'];
	}

	// cash outs come last
	$query = mysql_query("SELECT * FROM `transactions` WHERE `state` = ". STATE_CASH_OUT_INITIAL ." AND `topay` > 0 ORDER BY `id` ASC;");
	while($row = mysql_fetch_assoc($query))
	{
		print("Money: " . $money . "\n");
		if ($money < $row['topay'])
			break;
			
		mysql_query("UPDATE `transactions` SET `state` = ". STATE_CASH_OUT_READY ." WHERE `id` = " . $row['id'] . ";");
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
		$query = mysql_query('SELECT * FROM `transactions` WHERE `state` in ('. STATE_READY .', '. STATE_SENTBACK_READY .', ' . STATE_CASH_OUT_READY . ') ORDER BY `date` ASC;');
		while($row = mysql_fetch_assoc($query))
		{
			// checks if payback
			if ($row['state'] == STATE_SENTBACK_READY) {
				$value = charge_fee($row['topay']);
				$txout = $client->sendtoaddress( $row['address'], $value );
				$state = STATE_SENTBACK;
			} elseif ($row['state'] == STATE_CASH_OUT_READY) {
				$value = dice_round($row['topay']); // no fee for cash out
				$txout = $client->sendtoaddress( $row['address'], $value );
				$state = STATE_CASH_OUT;
			} else {
				$value = charge_fee($row['topay']);
				$txout = $client->sendfrom( $config['ponziacc'], $row['address'], $value );
				$state = STATE_PAID;
			}
			mysql_query("UPDATE `transactions` SET `state` = ". $state .", `out` = '" . $txout . "', `out_date` = " . (time()) . ", `actually_paid` = " . $value . " WHERE `id` = " . $row['id'] . ";");
			print($value . " " . $config['val'] ." sent to " . $row['address'] . ".\n");
		}
	}

	echo ("Finished...\n");
?>
