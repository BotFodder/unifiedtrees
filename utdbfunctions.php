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
include_once($config["library_path"] . "/database.php");
include_once($config["base_path"] . '/lib/tree.php');

// Set up the tree table.
function ut_setup_tree_table() {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(8)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'tier', 'type' => 'int(4)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'treename', 'type' => 'varchar(30)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(30)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'fullname', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'xID', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'url', 'type' => 'varchar(150)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(8)', 'NULL' => false, 'default' => 0);
	$data['primary'] = 'id';
	$data['keys'][] = array();
	$data['type'] = 'memory';
	$data['comment'] = 'Plugin Unified Trees - Holds the built tree.';
	api_plugin_db_table_create('unifiedtrees', 'plugin_unifiedtrees_tree', $data);
}

// Save the tree to the table
function ut_save_tree($fulltree) {
	foreach($fulltree['tree'] as $treename => $tree) {
		foreach($tree['id'] as $leafid => $leaf) {
			$tier = $leaf['tier'];
			$name = $leaf['name'];
			$fullname = $leaf['fullname'];

			$xID = $leaf['xID'];
			$url = $leaf['url'];
			$host_id = $leaf['host_id'];
			db_execute("INSERT INTO plugin_unifiedtrees_tree (tier, treename, name, fullname, xID, url, host_id) VALUES ($tier, '$treename', '$name', '$fullname', '$xID', '$url', $host_id)");
		}
	}
}

// Read the tree in from a table and optionally specify the server to get it from
function ut_read_tree($database = FALSE) {
	global $cnn_id;
	if (!$database) {
		$database = $cnn_id;
	}
	$fulltree = array();
	$leaves = db_fetch_assoc("SELECT * from plugin_unifiedtrees_tree ORDER BY id", TRUE, $database);
	foreach ($leaves as $leaf) {
$fulltree['tree'][$leaf['treename']]['id'][$leaf['fullname']]['fullname'] = $leaf['fullname'];
$fulltree['tree'][$leaf['treename']]['id'][$leaf['fullname']]['tier'] = $leaf['tier'];
$fulltree['tree'][$leaf['treename']]['id'][$leaf['fullname']]['name'] = $leaf['name'];
$fulltree['tree'][$leaf['treename']]['id'][$leaf['fullname']]['xID'] = $leaf['xID'];
$fulltree['tree'][$leaf['treename']]['id'][$leaf['fullname']]['url'] = $leaf['url'];
$fulltree['tree'][$leaf['treename']]['id'][$leaf['fullname']]['host_id'] = $leaf['host_id'];
	}
	return $fulltree;
}

// Build a tree from multiple databases.
function ut_build_tree($databases) {
	foreach($databases as $db) {
		utdb_debug($db['base_url']."\n");
		$tiername = array();
		$trees = db_fetch_assoc("SELECT * from graph_tree", TRUE, $db['dbconn']);
		utdb_debug("Tree size ".sizeof($trees)."\n");
		foreach($trees as $tree) {
			$tiername[0] = "0";
			if(!isset($fulltree['tree'][$tree['name']]['id'][$tiername[0]])) {
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['tier'] = 0;
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['fullname'] = $tiername[0];
$fulltree['tree'][$tree['name']]['id'][$tiername[0]]['name'] = $tree['name'];
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
			utdb_debug("Treeitems: ".sizeof($treeitems)."\n");
			if (is_array($treeitems)) {
				foreach($treeitems as $treeitem) {
					$tier = tree_tier($treeitem['order_key']);
$url = $db['base_url']."graph_view.php?action=tree&amp;tree_id=".$tree['id']."&amp;leaf_id=".$treeitem['id'];
if(isset($treeitem['title']) && $treeitem['title'] != "" && $treeitem['host_id'] == 0) {
	$stn = $treeitem['title']."|".$tier;
}elseif(isset($treeitem['description']) && $treeitem['host_id'] != 0) {
	$stn = $treeitem['description']."|".$tier;
}else{
	utdb_debug("No host_id/title\n");
//	print "TREE BUG - host_id? title?\n";
}
					if($tier == $currenttier) {
						$tiername[$tier] = $tiername[$tier-1]."|".$stn;
					}elseif ($tier > $currenttier) {
						$tiername[$tier] = $tiername[$currenttier]."|".$stn;
						$currenttier = $tier;
					}elseif ($tier < $currenttier) {
						$tiername[$tier] = $tiername[$tier-1]."|".$stn;
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
				}
			}else{
				print "Not an array $treeitems\n";
			}
		}
	}
	return $fulltree;
}

// Servers and Always configurations need this.
function ut_setup_dbs() {
	global $cnn_id, $config, $database_default, $database_username, $database_port, $database_password, $url_path;

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

// For clients, we want the first server that qualifies.
function ut_get_server_db() {
	global $config, $database_default, $database_username, $database_port, $database_password;

	$remote = db_fetch_assoc("SELECT * FROM plugin_unifiedtrees_sources
 WHERE enable_db='on'"); 
	foreach ($remote as $other) {
		unset($why);
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
			// Just retrun the first one that's live.
			if($oconn) {
				$tables = db_fetch_assoc("SHOW TABLES LIKE 'plugin_unifiedtrees_tree'", TRUE, $oconn);
				if(sizeof($tables > 0)) {
					return($oconn);
					break;
				}else{
					$why = "Could not find table plugin_unifiedtrees_tree\n";
				}
			}
			$attempt++;
		}
		$disable_bad = read_config_option('unifiedtrees_disable_bad_connection');
		$admin_email = read_config_option('unifiedtrees_admin_email');
		if($disable_bad == 'on' &&
filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
			db_execute("UPDATE plugin_unifiedtrees_sources SET enable_db=''
 WHERE id=".$other['id']);
			if(!isset($why)) {
				$why = "Could not connect to database\n";
			}
			$message = "Disabled connection for unified trees:
SERVER:  ".$other['db_address']."
DB NAME: ".$other['db_name']."
DB USER: ".$other['db_uname']."
BASEURL: ".$other['base_url']."
$why";
			mail($admin_email, "UT DISABLED: ".$other['db_address'], $message);
		}
	}
	return FALSE;
}

function utdb_debug($msg) {
	global $debug;

	if($debug) print $msg;
}
?>
