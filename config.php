<?php
	$mysql = array(
		'user' => 'root',
		'password' => '',
		'host' => 'localhost',
		'database' => 'dice'
	);

	mysql_connect($mysql['host'], $mysql['user'], $mysql['password']) or die("Cannot connect to database.");
	mysql_select_db($mysql['database']);
	
	$rpc = array(
		'login' => 'RPC_USER',
		'password' => 'RPC_PASS',
		'ip' => '127.0.0.1',
		'port' => '21057'
	);
	
	$config = array(
		'name' => 'DilmaDice',																	// Name of your ponzi
		'title' => 'Mega Sena que se cuide!',																// Description
		'full-name' => 'Loteria Dilmacoin',												// Full name of your ponzi
		'val' => 'HUE',																				// Cryptocurrency abbreviation
		'precision' => 4,
		'confirmations' => 3,																	// Minimum number of confirmations to add transaction
		'min' => 1.0,																				// Minimum pay in
		'max' => 100,																				// Maximum pay in
		'pot_fee' => 0.04,																			// How much money to send - default: 0.1 - 110%
		'fee' => 0.01,																				// Fee taken from pay in amount
		'payout-check' => 120,																// Time between payouts
		'ownaddress' => 'DGirino9uDPavPkNejRHmhjdAwujaQhm7e', // Your address
		'sendback' => true,																	// What to do with txs that are over maximum or under minimum | true - send back, false - send to your address
		'ponziacc' => 'hue',																	// Name of daemon account
		'address' => 'DDiceLFx5TY5Puq2exuk8AywHbkJPN62nB',			// Ponzi address
		'privkey' => '',																			// Needed in setup, private key of your address
		'blockchain-addr' => 'http://dilmaexplorer.girino.org/address/',
		'blockchain-tx' => 'http://dilmaexplorer.girino.org/tx/',
	// for randomness
		'hash_secret' => 'YOUR_SECRET',
	// ignore transactions before this date
		'begin_date' => '0'
	);
?>
