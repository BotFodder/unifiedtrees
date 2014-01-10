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
	api_plugin_register_hook('unifiedtrees', 'poller_bottom', 'ut_poller_bottom', 'setup.php');
	api_plugin_register_hook('unifiedtrees', 'utilities_action', 'ut_utilities_action', 'setup.php');
	api_plugin_register_hook('unifiedtrees', 'utilities_list', 'ut_utilities_list', 'setup.php');

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
	$files = array('plugins.php', 'tree_sources.php', 'settings.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = plugin_unifiedtrees_version ();
	$current = $version['version'];
	$old = read_config_option('plugin_unifiedtrees_version');
	if ($current != $old) {
		// Set the new version
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
	// These are new, and if we've been upgraded from 0.1, may not exist.
	api_plugin_register_hook('unifiedtrees', 'poller_bottom', 'ut_poller_bottom', 'setup.php');
	api_plugin_register_hook('unifiedtrees', 'utilities_action', 'ut_utilities_action', 'setup.php');
	api_plugin_register_hook('unifiedtrees', 'utilities_list', 'ut_utilities_list', 'setup.php');
	if(api_plugin_is_enabled('unifiedtrees')) {
		api_plugin_enable_hooks('unifiedtrees');
	}
	db_execute("REPLACE INTO settings (name, value) VALUES ('plugin_unifiedtrees_version', '".$version['version']."')");
}

function plugin_unifiedtrees_version () {
	return array(
		'name'     => 'unifiedtrees',
			'version'  => '0.5',
			'longname' => 'Unified Trees',
			'author'   => 'Eric Stewart',
			'homepage' => 'http://runningoffatthemouth.com/?p=1089',
			'email'    => 'eric@ericdives.com',
			'url'      => 'http://runningoffatthemouth.com/?p=1089'
		);
}

function ut_utilities_action ($action) {
	if ($action == 'ut_clear') {
		db_execute('DROP TABLE plugin_unifiedtrees_tree');

		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	}
	return $action;
}

function ut_utilities_list () {
	global $colors;

	html_header(array("Unified Tree"), 2);
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=ut_clear'>Clear Unified Tree</a>
		</td>
		<td class="textArea">
			This will drop the Unified Trees tree table on this master, which should trigger a rebuilding of the tree.
		</td>
	</tr>
	<?php
}

function ut_config_settings () {
	global $tabs, $settings, $ut_tree_build_freq,$url_path;
	$tabs["visual"] = "Visual";

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	unifiedtrees_check_upgrade();
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		$ut_base_url = "https://";
	}else{
		$ut_base_url = "http://";
	}
	if (isset($_SERVER['HTTP_HOST'])) {
		$ut_base_url .= $_SERVER['HTTP_HOST'].$url_path;
	}else{
		$ut_base_url .= "fixme!".$url_path;
	}
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
		'unifiedtrees_build_freq' => array(
			'friendly_name' => "Build Frequency",
			'description' => "This controls how UT operates.  'Always' preserves the original '0.1' operation: all listed 'enabled' databases are swept for tree information and a tree is built and displayed - every time the graph tree is loaded (for large installs, this can take a noticable amount of time). 'Client' - the first database server to respond in the list of 'enabled' databases (as long as there is one, otherwise the local tree is used) is queried for a prebuilt tree, and that is displayed.  All other values: this Cacti instance becomes a 'Server', and builds a tree at the indicated frequency.  The tree is stored in a memory based table and can be used by other Cacti installs that are set as 'Client's.",
			'method' => 'drop_array',
			'default' => 'always',
			"array" => $ut_tree_build_freq,
			),
		'unifiedtrees_base_url' => array(
			'friendly_name' => "Base URL for UT",
			'description' => "If this UT/Cacti instance is in 'Server' mode, during the tree build process the server may not be able to figure out a decent 'base url' - the address of Cacti.  You should be able to just accept the default listed here and 'Save' it.",
			'method' => "textbox",
			'max_length' => 255,
			'default' => $ut_base_url,
			),
		'unifiedtrees_sort_trees' => array(
			'friendly_name' => "Sort Trees",
			'description' => "The root of every tree is treated separately than everything below it.  By default, these are not sorted; if you have only one tree defined on all servers (and they're all named the same thing), this setting doesn't matter.  If a 'new' tree is found on another server, it will be listed below trees defined on the server you're actually viewing.  Check this box to have the trees sorted alphabetically before display.",
			'method' => 'checkbox',
			),
		'unifiedtrees_sort_leaves' => array(
			'friendly_name' => "Sort Leaves",
			'description' => "This will sort (alphabetically) all the branches/leaves of a tree once they've been pulled from all databases.  There should be no objection to this; because of how the different databases are processed, failure to sort the leaves may result in trees not displaying properly (including some information not appearing at all).",
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

function ut_config_arrays () {
	global $menu, $config, $ut_tree_build_freq;

	$temp = $menu["Utilities"]['logout.php'];
	unset($menu["Utilities"]['logout.php']);
	$menu["Utilities"]['plugins/unifiedtrees/tree_sources.php'] = "Unified Trees - Sources";
	$menu["Utilities"]['logout.php'] = $temp;

/* Welcome to the new world.  This is an array that defines what mode UT can
   be set to:
	"Always" - operate in the 0.1 fashion.
	"Client" - grab the first entry in the list of DBs, pull the built tree,
		if available, and use it.
	Integer - Build the tree every however many seconds.  There will be a
		ut_last_build value in the settings to indicate the last time
		the tree was built.  If it was more than Integer minutes ago,
		then we build the tree again.
*/
	$ut_tree_build_freq = array(
		"always" => "Always",
		"client" => "Client",
		"60" => "Every 1 Hour",
		"120" => "Every 2 Hours",
		"240" => "Every 4 Hours",
		"360" => "Every 6 Hours",
		"480" => "Every 8 Hours",
		"720" => "Every 12 Hours",
		"1440" => "Every Day"
	);
}

function ut_draw_navigation_text ($nav) {
	$nav["tree_sources.php:"] = array("title" => "Unified Trees - Sources", "mapping" => "index.php:", "url" => "tree_sources.php", "level" => "1");
	$nav["tree_sources.php:edit"] = array("title" => "UT - Sources - Edit", "mapping" => "index.php:", "url" => "tree_sources.php", "level" => "1");
	$nav["tree_sources.php:actions"] = array("title" => "Unified Trees - Sources", "mapping" => "index.php:", "url" => "tree_sources.php", "level" => "1");
	$nav["utilities.php:ut_clear"] = array("title" => "Clear Unified Tree", "mapping" => "index.php:,utilities.php:", "url" => "tree_sources.php", "level" => "1");
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

function ut_poller_bottom() {
	global $config;

	include_once($config["library_path"] . "/database.php");
	$frequency = read_config_option("unifiedtrees_build_freq");
	if(!is_numeric($frequency)) {
		return;
	}

	$last_build = read_config_option("unifiedtrees_last_build");
	$tables = db_fetch_assoc("SHOW TABLES LIKE 'plugin_unifiedtrees_tree'");
	if($last_build != '' && time() - $last_build < ($frequency * 60) && sizeof($tables) > 0) {
		return;
	}
	$command_string = trim(read_config_option("path_php_binary"));

	// Okay, let's fudge it.
	if(trim($command_string) == '')
		$command_string = "php";

	cacti_log("UnifiedTrees building tree. Time: ".time()." Last build: $last_build Frequency: $frequency Table exists: ".sizeof($tables));

	$extra_args = ' -q ' . $config['base_path'] . '/plugins/unifiedtrees/build_tree.php';

	exec_background($command_string, $extra_args);

	if($last_build == '') {
		$sql = "INSERT INTO settings VALUES ('unifiedtrees_last_build','".time()."')";
	}else{
		$sql = "UPDATE settings SET value = '".time()."' WHERE name='unifiedtrees_last_build'";
	}
	db_execute($sql);
}

?>
