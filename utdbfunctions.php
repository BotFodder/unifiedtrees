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

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug = FALSE;
$forcerun = FALSE;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
/*
	case "-r":
		dpdiscover_recreate_tables();
		break;
*/
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

function ut_build_tree($databases) {

	foreach($databases as $db) {
		$tiername = array();
		$trees = db_fetch_assoc("SELECT * from graph_tree", TRUE, $db['dbconn']);
		foreach($trees as $tree) {
			$tiername[0] = "0";
			if(!isset($fulltree['tree'][$tree['name']]['id'][$tiername[0]])) {
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['tier'] = 0;
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['fullname'] = $tree['name'];
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['name'] = $tree['name'];
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['tree_id'] = $tree['id'];
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['leaf_id'] = FALSE;
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['xID'] = "tree_".$tree['id'];
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['url'] = $db['base_url']."graph_view.php?action=tree&amp;tree_id=".$tree['id'];
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['host_id'] = 0;
			}
			$currenttier = 0;
			$treeitems = db_fetch_assoc("SELECT graph_tree_items.*, host.description
 FROM graph_tree_items
 LEFT JOIN host
 ON (graph_tree_items.host_id=host.id)
 WHERE graph_tree_items.graph_tree_id=".$tree['id']."
 ORDER BY graph_tree_items.order_key", TRUE, $db['dbconn']);
			if (is_array($treeitems)) {
				foreach($treeitems as $treeitem) {
					$tier = tree_tier($treeitem['order_key']);
$url = $db['base_url']."graph_view.php?action=tree&amp;tree_id=".$tree['id']."&amp;leaf_id=".$treeitem['id'];
if(isset($treeitem['title']) && $treeitem['title'] != "" && $treeitem['host_id'] == 0) {
	$stn = $treeitem['title']."|".$tier;
}elseif(isset($treeitem['description']) && $treeitem['host_id'] != 0) {
	$stn = $treeitem['description']."|".$tier;
}else{
//	print "TREE BUG - host_id? title?\n";
}
					if($tier == $currenttier) {
						$tiername[$tier] = $tiername[$tier-1]."|".$stn;
//						print $tiername[$tier]."\n";
					}elseif ($tier > $currenttier) {
//						print "TIER $tier CURRENT $currenttier\n";
						$tiername[$tier] = $tiername[$currenttier]."|".$stn;
//						print $tiername[$tier]."\n";
						$currenttier = $tier;
					}elseif ($tier < $currenttier) {
//						print "TIER $tier CURRENT $currenttier\n";
						$tiername[$tier] = $tiername[$tier-1]."|".$stn;
//						print $tiername[$tier]."\n";
						$currenttier = $tier;
					}
if(!isset($fulltree['tree'][$tree['name']]['id'][$tiername[$tier]])) {
	if($treeitem['host_id'] > 0) {
$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['name'] = $treeitem['description'];
	}else{
$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['name'] = $treeitem['title'];
	}
$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['tier']=$tier;
$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['host_id']=$treeitem['host_id'];
$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['fullname']=$tiername[$tier];
$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['xID']="tree_".$tree['id']."_leaf_".$treeitem['id'];
$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['url']=$url;
}
//					print "TREE: ".$tree['name']." FULL: ".$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['fullname']."
//".$fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['url']."\n";
					if($fulltree['tree'][$tree['name']]['id'][$tiername[$tier]]['host_id'] > 0) {
					}
				}
			}else{
				print "Not an array $treeitems\n";
			}
		}
	}
	return $fulltree;
}

function ut_setup_dbs() {
	global $cnn_id,$config, $database_default, $database_username, $database_port, $database_password, $url_path;

	$answer = array();
	// Configure the local DB:
	$ut_base_url = read_config_option("unifiedtrees_base_url");
	if(isset($ut_base_url) && $ut_base_url != "") {
		$answer[0]['base_url'] = $ut_base_url;
	}else{
		$answer[0]['base_url'] = $url_path;
	}
	$answer[0]['dbconn'] = $cnn_id;

	$remote = db_fetch_assoc("SELECT * FROM plugin_unifiedtrees_sources
 WHERE enable_db='on'"); 
	foreach ($remote as $other) {
		$tmp = array();
		if(!isset($other['db_name']) || $other['db_name'] == '') {
			$other['db_name'] = $database_default;
		}
		if(!isset($other['db_uname']) || $other['db_uname'] == '') {
			$other['db_uname'] = $database_username;
		}
		if(!isset($other['db_pword']) || $other['db_pword'] == '') {
			$other['db_pword'] = $database_password;
		}
		if(!isset($other['db_port']) || $other['db_port'] == 0 || $other['db_port'] == '') {
			if(!isset($database_port) || $database_port == '') {
				$other['db_port'] = "3306";
			}else{
				$other['db_port'] = $database_port;
			}
		}
		if(!isset($other['db_retries']) || $other['db_retries'] == 0 ||
		   $other['db_retries'] == '') {
			$other['db_retries'] = 2;
		}
		if(!isset($other['base_url']) || $other['base_url'] == '') {
			if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
				$other['base_url'] = "https://";
			}else{
				$other['base_url'] = "http://";
			}
			$other['base_url'] .= $other['db_address'].$url_path;
		}
		$dsn = $other['db_type']."://".rawurlencode($other['db_uname']).":".rawurlencode($other['db_pword'])."@".rawurlencode($other['db_address'])."/".rawurlencode($other['db_name'])."?persist";
		if($other['db_ssl'] == 'on') {
			if($other['db_type'] == "mysql") {
				$dsn .= "&clientflags=" . MYSQL_CLIENT_SSL;
			}elseif ($other['db_type'] == "mysqli") {
				$dsn .= "&clientflags=" . MYSQLI_CLIENT_SSL;
			}
		}
		if($other['db_port'] != "3306") {
			$dsn .= "&port=" . $port;
		}
		$attempt = 0;
		while($attempt < $other['db_retries']) {
			$oconn = ADONewConnection($dsn);
			if($oconn) {
				$tmp['base_url'] = $other['base_url'];
				$tmp['dbconn'] = $oconn;
				$answer[] = $tmp;
				break;
			}
			$attempt++;
		}
		$disable_bad = read_config_option('unifiedtrees_disable_bad_connection');
		$admin_email = read_config_option('unifiedtrees_admin_email');
		if(!$oconn && $disable_bad == 'on' &&
filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
			db_execute("UPDATE plugin_unifiedtrees_sources SET enable_db=''
 WHERE id=".$other['id']);
			$message = "Disabled connection for unified trees:
SERVER:  ".$other['db_address']."
DB NAME: ".$other['db_name']."
DB USER: ".$other['db_uname']."
BASEURL: ".$other['base_url']."
";
			mail($admin_email, "UT DISABLED: ".$other['db_address'], $message);
		}
	}
	return $answer;
}
?>
