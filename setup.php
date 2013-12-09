<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013 Eric Stewart                                         |
 |									   |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 |									   |
 | Some code is derived from Cacti Group maintained code.  Their code is   |
 | covered by the copyright below.                                         |
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_unifiedtrees_install () {
	api_plugin_register_hook('unifiedtrees', 'config_arrays', 'ut_config_arrays', 'setup.php');
	api_plugin_register_hook('unifiedtrees', 'draw_navigation_text', 'ut_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('unifiedtrees', 'config_settings', 'ut_config_settings', 'setup.php');
//	api_plugin_register_hook('dpdiscover', 'poller_bottom', 'dpdiscover_poller_bottom', 'setup.php');

	api_plugin_register_realm('unifiedtrees', 'tree_sources.php', 'Set Source Trees', 1);

	ut_setup_table();
}

function plugin_unifiedtrees_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_unifiedtrees_check_config () {
	// Here we will check to ensure everything is configured
	unifiedtrees_check_upgrade ();
	return true;
}

function plugin_unifiedtrees_upgrade () {
	// Here we will upgrade to the newest version
	unifiedtrees_check_upgrade();
	return false;
}

function unifiedtrees_version () {
	return plugin_unifiedtrees_version();
}

function unifiedtrees_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'tree_sources.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = plugin_unifiedtrees_version ();
	$current = $version['version'];
	$old = read_config_option('plugin_unifiedtrees_version');
	if ($current != $old) {
/*
		$dpdiscover_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM plugin_dpdiscover_hosts"), "Field", "Field");
		if (!in_array("snmp_version", $dpdiscover_columns)) {
			db_execute("ALTER TABLE plugin_dpdiscover_hosts ADD COLUMN snmp_version tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER community");
		}
*/
		// Set the new version
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
}

function plugin_unifiedtrees_version () {
	return array(
		'name'     => 'unifiedtrees',
			'version'  => '0.1',
			'longname' => 'Unified Trees',
			'author'   => 'Eric Stewart',
			'homepage' => 'http://runningoffatthemouth.com/?p=1089',
			'email'    => 'eric@ericdives.com',
			'url'      => 'http://runningoffatthemouth.com/?p=1089'
		);
}
/*
function dpdiscover_utilities_action ($action) {
	if ($action == 'dpdiscover_clear') {
		mysql_query('DELETE FROM plugin_dpdiscover_hosts');

		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	}
	return $action;
}
function ut_utilities_list () {
	global $colors;

	html_header(array(""), 2);
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=dpdiscover_clear'>Clear DPDiscover Results</a>
		</td>
		<td class="textArea">
			This will clear the results from the discovery polling.
		</td>
	</tr>
	<?php
}
*/
function ut_config_settings () {
	global $tabs, $settings;
	$tabs["visual"] = "Visual";

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$temp = array(
		"unifiedtrees_header" => array(
			"friendly_name" => "Unified Trees",
			"method" => "spacer",
			),
		'unifiedtrees_use_ut' => array(
			'friendly_name' => "Use Unified Trees",
			'description' => "Checking this means you've done everything you think you need to do to have Unified Trees fully installed; you are aware of the <a href='http://runningoffatthemouth.com/?p=1098#caveats'>limitations and caveats</a> of the plugin (especially the authentication issues), and are ready to see what it looks like.",
			'method' => 'checkbox',
			),
		'unifiedtrees_sort_trees' => array(
			'friendly_name' => "Sort Trees",
			'description' => "The root of every tree is treated separately than everything below it.  By default, these are not sorted; if you have only one tree defined on all servers (and they're all named the same thing), this setting doesn't matter.  If a 'new' tree is found on another server, it will be listed below trees defined on the server you're actually viewing.  Check this box to have the trees sorted alphabetically before display.",
			'method' => 'checkbox',
			),
		'unifiedtrees_sort_leaves' => array(
			'friendly_name' => "Sort Leaves",
			'description' => "This will sort (alphabetically) all the branches/leaves of a tree once they've been pulled from all databases.",
			'method' => 'checkbox',
			'default' => 'on',
			),
		'unifiedtrees_disable_bad_connection' => array(
			'friendly_name' => "Disable Problem Servers",
			'description' => "Given a valid email below, this will cause Unified Trees to disable tree source databases that it fails to successfully connect to.",
			'method' => 'checkbox',
			'default' => 'on',
			),
		'unifiedtrees_admin_email' => array(
			'friendly_name' => "Admin Email",
			'description' => "An email address to notify if a particular server connection is disabled because UT could not connect to it.",
			'method' => "textbox",
			'max_length' => 255,
			"default" => "",
			),
	);
	if (isset($settings["visual"]))
		$settings["visual"] = array_merge($settings["visual"], $temp);
	else
		$settings["visual"]=$temp;
}
/*
function dpdiscover_show_tab () {
	global $config, $dpdiscover_tab;
	include_once($config["library_path"] . "/database.php");
	include_once($config["base_path"] . "/plugins/dpdiscover/config.php");
	if (api_user_realm_auth('dpdiscover.php')) {
		if (!substr_count($_SERVER["REQUEST_URI"], "dpdiscover.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/dpdiscover/dpdiscover.php"><img src="' . $config['url_path'] . 'plugins/dpdiscover/images/tab_discover.gif" alt="dpdiscover" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/dpdiscover/dpdiscover.php"><img src="' . $config['url_path'] . 'plugins/dpdiscover/images/tab_discover_down.gif" alt="dpdiscover" align="absmiddle" border="0"></a>';
		}
	}
}
*/

function ut_config_arrays () {
	global $menu, $config;

//	include_once($config["base_path"] . "/plugins/unifiedtrees/config.php");

//	$menu["Templates"]['plugins/dpdiscover/dpdiscover_template.php'] = "DPDiscover Templates";

	$temp = $menu["Utilities"]['logout.php'];
	unset($menu["Utilities"]['logout.php']);
	$menu["Utilities"]['plugins/unifiedtrees/tree_sources.php'] = "Unified Trees - Sources";
	$menu["Utilities"]['logout.php'] = $temp;

}
function ut_draw_navigation_text ($nav) {
//	$nav["dpdiscover.php:"] = array("title" => "DPDiscover", "mapping" => "", "url" => "dpdiscover.php", "level" => "0");
	$nav["tree_sources.php:"] = array("title" => "Unified Trees - Sources", "mapping" => "index.php:", "url" => "tree_sources.php", "level" => "1");
	$nav["tree_sources.php:edit"] = array("title" => "UT - Sources - Edit", "mapping" => "index.php:", "url" => "tree_sources.php", "level" => "1");
	$nav["tree_sources.php:actions"] = array("title" => "Unified Trees - Sources", "mapping" => "index.php:", "url" => "tree_sources.php", "level" => "1");
//	$nav["utilities.php:dpdiscover_clear"] = array("title" => "Clear Discover Results", "mapping" => "index.php:,utilities.php:", "url" => "dpdiscover.php", "level" => "1");
	return $nav;
}

function ut_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(8)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'enable_db', 'type' => 'varchar(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'db_type', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 'mysqli');
	$data['columns'][] = array('name' => 'db_address', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'db_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => 'cacti');
	$data['columns'][] = array('name' => 'db_uname', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'db_pword', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'db_port', 'type' => 'int(8)', 'NULL' => true);
	$data['columns'][] = array('name' => 'db_ssl', 'type' => 'varchar(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'db_retries', 'type' => 'int(2)', 'NULL' => false, 'default' => 2);
	$data['columns'][] = array('name' => 'base_url', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'id';
	$data['keys'][] = array();
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Plugin Unified Trees - Database connections to use as sources of other trees.';
	api_plugin_db_table_create('unifiedtrees', 'plugin_unifiedtrees_sources', $data);
}

?>
