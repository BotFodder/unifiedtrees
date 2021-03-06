<?php
/*---------------------------------------------------------------------------+
 | Copyright (C) 2013 Eric Stewart                                           |
 |                                                                           |
 | This program is free software; you can redistribute it and/or             |
 | modify it under the terms of the GNU General Public License               |
 | as published by the Free Software Foundation; either version 2            |
 | of the License, or (at your option) any later version.                    |
 |                                                                           |
 | This program is distributed in the hope that it will be useful,           |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
 | GNU General Public License for more details.                              |
 +---------------------------------------------------------------------------+
 | Unified Trees: Cacti Plugin to unify trees from multiple Cacti servers    |
 +---------------------------------------------------------------------------+
 | Code designed, written, maintained (as of 2013/2014) by Eric Stewart      |
 +---------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
//if(!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
// 	die("<br><strong>This script is only meant to run at the command line.</strong>");
// }

/* let PHP run just as long as it has to */
ini_set("max_execution_time", "0");

error_reporting('E_ALL');
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include("./include/global.php");
include_once($config["base_path"] . '/plugins/unifiedtrees/utdbfunctions.php');

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug = FALSE;
$forcerun = FALSE;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);
	switch($arg) {
	case "-d":
		$debug = TRUE;
		break;
	case "-h":
		display_help();
		exit;
	case "-f":
		$forcerun = TRUE;
		break;
	case "-v":
		display_help();
		exit;
	case "--version":
		display_help();
		exit;
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

// Should be checked elsewhere, but check here too!
$ut_enabled = read_config_option("unifiedtrees_use_ut");
$ut_server = read_config_option("unifiedtrees_build_freq");

if(($ut_enabled != "on" || !is_numeric($ut_server)) && $forcerun === FALSE) {
	die("Either UT is not 'on', or this instance is not a server: ".$ut_server."\n");
}

// We're just gonna do this every time.
db_execute("DROP TABLE IF EXISTS plugin_unifiedtrees_tree");

ut_setup_tree_table();

$dbs = ut_setup_dbs();
utdb_debug("Size of dbs: ".sizeof($dbs)."\n");

$fulltree = ut_build_tree($dbs);
utdb_debug("Size of tree: ".sizeof($fulltree['tree'])."\n");

ut_save_tree($fulltree);

function display_help () {
	print "BuildTrees for UT v0.5, Copyright 2014 - Eric Stewart\n\n";
	print "usage: findhosts.php [-d] [-h] [--help] [-v] [--version]\n\n";
	print "-f	   - Force the execution of a discovery process\n";
	print "-d	   - Display verbose output during execution\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}
?>
