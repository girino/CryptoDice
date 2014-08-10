<?php
	include('config.php');
	include('constants.php');
	
	$amount = 0;
	$topay = 500; // edit to withdraw the right ammount
	$address = $config['ownaddress'];
	$state = STATE_CASH_OUT_INITIAL;
	$SQL = "INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . $state . "', '', " . (time()) . ", '', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ");";
	if (!mysql_query($SQL)) {
		print("ERROR INSERTING!!!\nSQL: " . $SQL);
	}
	print("Transaction added! [" . $amount . "]\n");
?>
