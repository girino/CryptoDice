<?php
/* This script updates the database to the current version
 * does this incrementally if needed
 */
php_sapi_name() === 'cli' or die('not allowed on web server');
include('config.php');
include('constants.php');

$current = 2; // current DB version

// first checks if the version table exists
$val = mysql_query('select 1 from `version`;');

$version = 0;
// fist step: table exists
if($val === FALSE)
{
	// create tables
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
  `out_date` int(11) NOT NULL DEFAULT '0',
  `block` varchar(255) NOT NULL,
  `secret` varchar(255) NOT NULL,
  `pot_fee` decimal(15,8) NOT NULL DEFAULT '0',
  `fee` decimal(15,8) NOT NULL DEFAULT '0',
  `actually_paid` decimal(15,8) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=36 ;";
	if(!mysql_query($sql)) {
		print("ERROR creating table: " . $sql . "\n");
	}

	$sql = "CREATE TABLE `version` (
  `id` int(11) NOT NULL DEFAULT '0',
  `version` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 ;";
	if(!mysql_query($sql)) {
		print("ERROR creating table: " . $sql . "\n");
	}

	if(!mysql_query("INSERT INTO `version` (`id`, `version`) values (0, " . $current . ");")) {
		print("ERROR inserting version\n");
	}

	mysql_query('/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;');
	mysql_query('/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;');
	mysql_query('/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;');
}

// checks version
$query = mysql_query('select max(`version`) from `version`;');
$query = mysql_fetch_row($query);
$version = (int)$query[0];
print("current version = " . $version . "\n");

// executes each incremental step
if ($version < 1) {
	// do whatever needs to be done for V 1.
}

if ($version < 2) {
	// updates table;
	$val = mysql_query("ALTER TABLE `transactions` ADD COLUMN (
  `out_date` int(11) NOT NULL DEFAULT '0',
  `block` varchar(255) NOT NULL DEFAULT '',
  `secret` varchar(255) NOT NULL DEFAULT '',
  `pot_fee` decimal(15,8) NOT NULL DEFAULT '0',
  `fee` decimal(15,8) NOT NULL DEFAULT '0',
  `actually_paid` decimal(15,8) NOT NULL DEFAULT '0' );");
	if (!$val) {
		die("ERROR updating table for version 2\n");
	}
	// updates data;

	$val = mysql_query("UPDATE `transactions` SET `state` = 6 where state = 3;");
	if (!$val) {
                die("ERROR updating data for version 2\n");
        }
	// update version info
	$val = mysql_query("UPDATE `version` SET `version` = 2 where `id` = 0;");
	if (!$val) {
                die("ERROR updating version to 2\n");
        }
}

if ($version < 4) {
	// etc
}
		
?>
