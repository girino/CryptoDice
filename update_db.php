<?php
/* This script updates the database to the current version
 * does this incrementally if needed
 */
php_sapi_name() === 'cli' or die('not allowed on web server');
include('config.php');
include('constants.php');

$current = 2; // current DB version
$version = 0;

// first checks if the version table exists
$version_table_exists = mysql_query('select 1 from `version`;');
$transactions_table_exists = mysql_query('select 1 from `transactions`;');

if ($transactions_table_exists !== FALSE) {
	$version = 1;
}

if($version_table_exists !== FALSE) {
	$query = mysql_query('select max(`version`) from `version`;');
	$query = mysql_fetch_row($query);
	$version = (int)$query[0];
}
print("current version = " . $version . "\n");

// step by step.
if ($version == 0) {
	print("updating from version 0 to 1...\n");
	// creates transactions original version
	print("Creating transactions table...\n");
	mysql_query('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";');
	mysql_query('SET time_zone = "+00:00";');
	mysql_query('/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;');
	mysql_query('/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;');
	mysql_query('/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;');
	mysql_query('/*!40101 SET NAMES utf8 */;');
	
	// creates all tables
	$sql = "CREATE TABLE `transactions` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `amount` decimal(15,8) NOT NULL,
			  `topay` decimal(15,8) NOT NULL,
			  `address` varchar(64) NOT NULL,
			  `state` int(11) NOT NULL DEFAULT '0',
			  `tx` varchar(255) NOT NULL,
			  `out` varchar(255) NOT NULL,
			  `date` int(11) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=36 ;";
	if(!mysql_query($sql)) {
		die("ERROR creating transaction table: " . $sql . "\n");
	}
	mysql_query('/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;');
	mysql_query('/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;');
	mysql_query('/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;');
	print("Done.\n");
	
	// updates version
	$version = 1;
	print("updated to version 1.\n");
}

// now update to version 2
if ($version == 1) {
	print("updating from version 1 to 2...\n");
	// creates version table;
	print("Creating version table...\n");
	$sql = "CREATE TABLE `version` ( " .
  			  "`id` int(11) NOT NULL DEFAULT '0', " .
  			  "`version` int(11) NOT NULL DEFAULT '0', " .
  			  "PRIMARY KEY (`id`) " .
			") ENGINE=InnoDB  DEFAULT CHARSET=latin1 ;";
	if(!mysql_query($sql)) {
		die("ERROR creating version table: " . $sql . "\n");
	}
	print("Done.\n");
	
	// updates transaction table
	print("Altering transactions table...\n");
	$sql = "ALTER TABLE `transactions` ADD COLUMN ( " .
		   "  `out_date` int(11) NOT NULL DEFAULT '0', " .
		   "  `block` varchar(255) NOT NULL DEFAULT '', " .
		   "  `secret` varchar(255) NOT NULL DEFAULT '', " .
		   "  `pot_fee` decimal(15,8) NOT NULL DEFAULT '0', " .
		   "  `fee` decimal(15,8) NOT NULL DEFAULT '0', " .
		   "  `actually_paid` decimal(15,8) NOT NULL DEFAULT '0', " .
		   "  `version` int(11) NOT NULL DEFAULT '0' );";
	$val = mysql_query($sql);
	if (!$val) {
		die("ERROR adding columns to transactions table: " . $sql . "\n");
	}
	print("Done.\n");
	
	// updates data
	print("Updating transactions table...\n");
	// state 3 is now state 6
	$sql = "UPDATE `transactions` SET `state` = 6 where state = 3;";
	if (!mysql_query($sql)) {
		die("ERROR updating transactions table: " . $sql . "\n");
	}
	// version of old algorithm is 1
	$sql = "UPDATE `transactions` SET `version` = 1;";
	if (!mysql_query($sql)) {
		die("ERROR updating transactions table: " . $sql . "\n");
	}
	// now, this is tricky.. i cant figure out the right params that were used, so i guess 
	// the guy never changed his current setings and use them. This should never occur since
	// i'm the only one using the software now.
	$sql = "UPDATE `transactions` SET `secret`        = '" . $config['hash_secret'] . "', 
									  `pot_fee`       = "  . $config['pot_fee'] . ",
									  `fee`           = "  . $config['fee'] . ",
									  `actually_paid` =	`topay` * (1.0 - " . $config['fee'] . ");";
	if (!mysql_query($sql)) {
		die("ERROR updating transactions table: " . $sql . "\n");
	}
	print("Done.\n");
	
	// update version info
	print("Insert into version table...\n");
	$sql = "insert into `version` (`id`,  `version`) values (0 , 2);";
	if (!mysql_query($sql)) {
		die("ERROR updating version table: " . $sql . "\n");
	}
	print("Done.\n");
	
	$version = 2;
	print("updated to version 2.\n");
}

if ($version == 2) {
	// etc
}

		
?>
