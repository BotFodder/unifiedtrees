<?php
/*---------------------------------------------------------------------------+
 | Copyright (C) 2013 Eric Stewart                                           |
 |                                                                           |
 | This program is free software; you can redistribute it and/or	     |
 | modify it under the terms of the GNU General Public License               |
 | as published by the Free Software Foundation; either version 2            |
 | of the License, or (at your option) any later version.                    |
 |                                                                           |
 | This program is distributed in the hope that it will be useful,           |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the	     |
 | GNU General Public License for more details.                              |
 +---------------------------------------------------------------------------+
 | Unified Trees: Cacti Plugin to unify trees from multiple Cacti servers    |
 +---------------------------------------------------------------------------+
 | Code designed, written, maintained (as of 2013/2014) by Eric Stewart      |
 +---------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
/*
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}
*/
/* let PHP run just as long as it has to */
ini_set("max_execution_time", "0");

error_reporting('E_ALL');
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include_once("./include/global.php");
include_once($config["base_path"] . '/lib/ping.php');
include_once($config["base_path"] . '/lib/utility.php');
include_once($config["base_path"] . '/lib/api_data_source.php');
include_once($config["base_path"] . '/lib/api_graph.php');
include_once($config["base_path"] . '/lib/snmp.php');
include_once($config["base_path"] . '/lib/data_query.php');
include_once($config["base_path"] . '/lib/api_device.php');

include_once($config["base_path"] . '/lib/sort.php');
include_once($config["base_path"] . '/lib/html_form_template.php');
include_once($config["base_path"] . '/lib/template.php');

include_once($config["base_path"] . '/lib/api_tree.php');
include_once($config["base_path"] . '/lib/tree.php');
include_once($config["base_path"] . '/lib/html_tree.php');
include_once($config["base_path"] . '/plugins/unifiedtrees/utdbfunctions.php');

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug = FALSE;
$forcerun = FALSE;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
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

$ut_mode = read_config_option("unifiedtrees_build_freq");
if ($ut_mode == "always") {
	ut_build_always();
	// Hey!  We're a server.  Just grab the local tree.
}elseif (is_numeric($ut_mode)) {
	$tables = db_fetch_assoc("SHOW TABLES LIKE 'plugin_unifiedtrees_tree'");
	if(sizeof($tables) == 0) {
		// Oh shit.  That shouldn't be happening.  Well, let's build the tree.
		include_once($config["base_path"] . "/plugins/unifiedtrees/build_tree.php");
		db_execute("REPLACE INTO settings (name, value) VALUES ('unifiedtrees_last_build','".time()."')");
		$fulltree = ut_read_tree();
		if(sizeof($fulltree) == 0) {
			ut_print_local_tree();
		}else{
			ut_print_tree($fulltree);
		}
	}else{
		$fulltree = ut_read_tree();
		if(sizeof($fulltree) == 0) {
			ut_print_local_tree();
		}else{
			ut_print_tree($fulltree);
		}
	}
}elseif ($ut_mode == "client") {
	// Connect the first server that can respond and get the tree from there.
	$sdb = ut_get_server_db();
	if($sdb === FALSE) {
		ut_print_local_tree();
	}else{
		$fulltree = ut_read_tree($sdb);
		if(sizeof($fulltree) == 0) {
			ut_print_local_tree();
		}else{
			ut_print_tree($fulltree);
		}
	}
}

// Not 100% necewssary but I like having it here.
exit;

function ut_build_always() {
	$dbs = ut_setup_dbs();
	$fulltree = ut_build_tree($dbs);
	ut_print_tree($fulltree);
}

function ut_print_local_tree() {
// Print the local tree.  This code copied from html_tree.php.
	/* get current time */
	list($micro,$seconds) = explode(" ", microtime());
	$current_time = $seconds + $micro;
	$expand_hosts = read_graph_config_option("expand_hosts");

	if (!isset($_SESSION['dhtml_tree'])) {
		$dhtml_tree = create_dhtml_tree();
		$_SESSION['dhtml_tree'] = $dhtml_tree;
	}else{
		$dhtml_tree = $_SESSION['dhtml_tree'];
		if (($dhtml_tree[0] + read_graph_config_option("page_refresh") < $current_time) || ($expand_hosts != $dhtml_tree[1])) {
			$dhtml_tree = create_dhtml_tree();
			$_SESSION['dhtml_tree'] = $dhtml_tree;
		}else{
			$dhtml_tree = $_SESSION['dhtml_tree'];
		}
	}

	$total_tree_items = sizeof($dhtml_tree) - 1;

	for ($i = 2; $i <= $total_tree_items; $i++) {
		print $dhtml_tree[$i];
	}
}

function ut_print_tree($fulltree) {
	print "foldersTree = gFld(\"\", \"\")
foldersTree.xID = \"root\"\n";
	if(read_config_option("unifiedtrees_sort_trees") == 'on') {
		ksort($fulltree['tree'], SORT_STRING);
	}
	foreach($fulltree['tree'] as $treename => $tree) {
// Tempted to remove this option.  Wierd shit can happen if you don't sort the
// leaves of a tree ... like the tree base not appearing properly.
		if(read_config_option("unifiedtrees_sort_leaves") == 'on') {
			ksort($tree['id'], SORT_STRING);
		}
		foreach($tree['id'] as $leafid => $leaf) {
			print "ou".$leaf['tier']." = insFld(";
// I may want to fix one issue with not properly sorting the tree by moving
// the tier 0 case out of this loop, since tier 0 should be part of the root
// definition of a tree.  Maybe in 0.6 when I feel like screwing with it.
// Thing is, some other weird shit happens too, so sorting is still strongly
// recommended.
			if($leaf['tier'] == 0) {
				print "foldersTree";
			}else{
				print "ou".($leaf['tier']-1);
			}
			print ", gFld(\"";
			if($leaf['host_id'] > 0) {
				print "Host: ";
			}
			print $leaf['name']."\", \"".$leaf['url']."\"))
ou".$leaf['tier'].".xID = \"".$leaf['xID']."\"\n";
		}
	}
}
?>
