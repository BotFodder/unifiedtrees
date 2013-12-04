<?php
/*---------------------------------------------------------------------------+
 | Copyright (C) 2013 Eric Stewart					   |
 |									   |
 | This program is free software; you can redistribute it and/or	     |
 | modify it under the terms of the GNU General Public License	       |
 | as published by the Free Software Foundation; either version 2	    |
 | of the License, or (at your option) any later version.		    |
 |									   |
 | This program is distributed in the hope that it will be useful,	   |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of	    |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the	     |
 | GNU General Public License for more details.			      |
 +---------------------------------------------------------------------------+
 | Unified Trees: Cacti Plugin to unify trees from multiple Cacti servers    |
 +---------------------------------------------------------------------------+
 | Code designed, written, maintained (as of 2013/2014) by Eric Stewart      |
 +---------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* let PHP run just as long as it has to */
ini_set("max_execution_time", "0");

error_reporting('E_ALL');
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include("./include/global.php");
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

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug = FALSE;
$forcerun = FALSE;

print $dir."\n";

/*
foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
	case "-r":
		dpdiscover_recreate_tables();
		break;
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
*/
$fulltree = array();
$tiername = array();
$trees = db_fetch_assoc("SELECT * from graph_tree");
$idx = 0;
foreach($trees as $tree) {
	$fulltree['id'][$tree['name']."|0"]['tier'] = 0;
	$fulltree['id'][$tree['name']."|0"]['fullname'] = $tree['name'];
	$fulltree['id'][$tree['name']."|0"]['name'] = $tree['name'];
	$fulltree['id'][$tree['name']."|0"]['url'] = "graph_view.php?action=tree&amp;tree_id=".$tree['id'];
	$tiername[0] = $tree['name']."|0";
	$currenttier = 0;
	$localtree = db_fetch_assoc("SELECT graph_tree_items.*, host.description
 FROM graph_tree_items
 LEFT JOIN host
 ON (graph_tree_items.host_id=host.id)
 WHERE graph_tree_items.graph_tree_id=".$tree['id']."
 ORDER BY graph_tree_items.order_key");
	if (is_array($localtree)) {
		foreach($localtree as $treeitem) {
			$url = "graph_view.php?action=tree&amp;tree_id=".$tree['id']."&amp;leaf_id=".$treeitem['id'];
			$tier = tree_tier($treeitem['order_key']);
			if(isset($treeitem['title']) && $treeitem['title'] != "" &&
			   $treeitem['host_id'] == 0) {
				$stn = $treeitem['title']."|".$tier;
			}elseif(isset($treeitem['description']) && $treeitem['host_id'] != 0) {
				$stn = $treeitem['description']."|".$tier;
			}
			if($tier == $currenttier) {
				$tiername[$tier] = $tiername[$tier-1]."|".$stn;
				print $tiername[$tier]."\n";
			}elseif ($tier > $currenttier) {
				print "TIER $tier CURRENT $currenttier\n";
				$tiername[$tier] = $tiername[$currenttier]."|".$stn;
				print $tiername[$tier]."\n";
				$currenttier = $tier;
			}elseif ($tier < $currenttier) {
				print "TIER $tier CURRENT $currenttier\n";
				$tiername[$tier] = $tiername[$tier-1]."|".$stn;
				print $tiername[$tier]."\n";
				$currenttier = $tier;
			}
			$tierstring = tree_tier_string($treeitem['order_key']);
			while(strlen($tierstring) % 3 != 0) {
				$tierstring .= "0";
			}
			print "TREE: ".$tree['name']." $tier $tierstring\n";
			if(strlen($tierstring) > 3) {
				$parentstring = substr($tierstring,0,strlen($tierstring)-3);
				print "$parentstring\n";
			}
		}
	}else{
		print "Not an array $localtree\n";
	}
}
?>
