<?php
	require_once 'config.php';
	require_once 'constants.php';
	require_once 'jsonRPCClient.php';

	function dice_round($value) {
		return round((float)$value, COIN_DECIMAL_PLACES);
	}
	
	function charge_fee($value) {
		global $config;
		
		return charge_fee_internal($value, $config['fee']);
	}

	function charge_fee_internal($value, $fee) {
		return dice_round($value * (1 - $fee));
	}
	
	function get_random_v1($value, $txid, $secret) {
		
	}
	
	function get_random_v2($value, $txid, $blockid, $pot_fee) {
		if (is_null($txid)) {
			return 0;
		}
		
		// get two first bytes
		$hash = hash_hmac('sha256', hex2bin($txid), hex2bin($blockid));
		// not using the secret makes it more auditable.
		// 	$hash = hash_hmac('sha256', $hash, $config['hash_secret']);
		$hex = substr($hash, 32, 4); // not sure if initial zeros are pruned
		$dec = hexdec ( $hex );
		// homogeneous in [0, 2) interval
		$ret = $dec / 0x8000;
		
		// pot fee removal
		if ($pot_fee > 0 && $ret > 1) {
			$ret = 1 + (($ret - 1) * (1.0 - $pot_fee)); // charges only the winnings
		}
		print("hex = " . $hex . ", dec = " . $dec . ", ret = " . $ret . "\n");
		
		return dice_round($ret * $value);
	}
	
function get_random($value, $txid, $blockid) {
	global $config;
	
	return get_random_v2($value, $txid, $blockid, $config['pot_fee']);
}
?>
