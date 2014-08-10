<?php
/*
 * This is an administrative tool.
 * It inserts a cash out operation in order to empty the pot in 
 * order to recover the admins invested money.
 * 
 * 1- Run with "php insertcashout.php VALUE" where value is the ammount to cash out.
 */

	if ($argc > 1) {
		include('config.php');
		include('constants.php');
		
		$value = $argv[1];
		
		$amount = 0;
		$address = $config['ownaddress'];
		$state = STATE_CASH_OUT_INITIAL;
		$SQL = "INSERT INTO `transactions` (`id`, `amount`, `topay`, `address`, `state`, `tx`, `date`, `block`, `secret`, `pot_fee`, `fee`) VALUES (NULL, '" . $amount . "', '" . $topay . "', '" . $address . "', '" . $state . "', '', " . (time()) . ", '', '" . $config['hash_secret'] . "', " . $config['pot_fee'] . ", " . $config['fee'] . ");";
		if (!mysql_query($SQL)) {
			print("ERROR INSERTING!!!\nSQL: " . $SQL);
		}
		print("Transaction added! [" . $amount . "]\n");
	} else {
?>
<H1>PLEASE DO NOT RUN FROM THE WEB</H1>
<?php
	}
?>
