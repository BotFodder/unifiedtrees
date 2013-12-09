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
chdir('../../');

include("./include/auth.php");
include_once("./lib/utility.php");
include_once("./include/global.php");

$host_actions = array(
	1 => "Delete"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		include_once("./include/top_header.php");

		tree_source_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		tree_source();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $config;

	if (isset($_POST["save_component_source"])) {
		$redirect_back = true;

		$save["id"] = $_POST["id"];
		$save["db_type"] = sql_sanitize($_POST["db_type"]);
		$save["db_address"] = sql_sanitize($_POST["db_address"]);
		$save["db_name"] = sql_sanitize($_POST["db_name"]);
		$save["db_uname"] = sql_sanitize($_POST["db_uname"]);
		$save["db_pword"] = sql_sanitize($_POST["db_pword"]);
		$save["db_port"] = form_input_validate($_POST["db_port"], "db_port", "^[0-9]*$", true, 3);
		$save["db_ssl"] = sql_sanitize($_POST["db_ssl"]);
		$save["db_retries"] = form_input_validate($_POST["db_retries"], "db_retries", "^[0-9]*$", true, 3);
		$save["base_url"] = sql_sanitize($_POST["base_url"]);
// NEED: Connectivity test
		if(ut_db_conntest($save) === FALSE) {
			$save["enable_db"] = '';
		}else{
			$save["enable_db"] = sql_sanitize($_POST["enable_db"]);
		}

		if (!is_error_message() && !empty($save["db_address"])) {
			$unifiedtrees_source_id = sql_save($save, "plugin_unifiedtrees_sources");

			if ($unifiedtrees_source_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message() || empty($_POST["id"]) || empty($_POST["db_address"])) {
			header("Location: tree_sources.php?id=" . (empty($unifiedtrees_source_id) ? $_POST["id"] : $unifiedtrees_source_id));
		}else{
			header("Location: tree_sources.php");
		}
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $host_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			db_execute("delete from plugin_unifiedtrees_sources where " . array_to_sql_or($selected_items, "id"));
		}

		header("Location: tree_sources.php");
		exit;
	}

	/* setup some variables */
	$host_list = ""; $host_array = array();

	/* loop through each of the tree sources selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$host_list .= "<li>" . db_fetch_cell("select db_address FROM plugin_unifiedtrees_sources WHERE id=" . $matches[1]) . " - ".db_fetch_cell("select db_name FROM plugin_unifiedtrees_sources WHERE id=" . $matches[1])."</li>";
			$host_array[] = $matches[1];
		}
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $host_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='tree_sources.php' method='post'>\n";

	if (sizeof($host_array)) {
		if ($_POST["drp_action"] == "1") { /* delete */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click 'Continue', the following Unified Trees Source(s) will be deleted.</p>
						<p><ul>$host_list</ul></p>
					</td>
				</tr>\n
				";
		}

		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Tree Sources'>";
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one UT Tree Source.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($host_array) ? serialize($host_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* ---------------------
    Template Functions
   --------------------- */

function tree_source_edit() {
	global $colors;

	$fields_ut_source_edit = array(
		"enable_db" => array(
			"method" => "checkbox",
			"friendly_name" => "Enable Database",
			"description" => "If necessary, a database connection can be disabled (rather than deleted).  This can speed up tree rendering if there are connectivity problems to db databases.  Databases can become automatically disabled if Unified Trees is configured to do so or if the connection fails a connectivity test when saving the connection.",
			"value" => "|arg1:enable_db|",
		),
		"db_address" => array(
			"method" => "textbox",
			"friendly_name" => "Database Address",
			"description" => "IP (preferred) or FQDN hostname of the database server. REQUIRED.  You do not need to specify the local database for this Cacti instance - it is always used.",
			"value" => "|arg1:db_address|",
			"max_length" => "50",
		),
		"db_name" => array(
			"method" => "textbox",
			"friendly_name" => "Database Name",
			"description" => "Database Name on the server.  If blank, the database name configured for the local database is used.",
			"value" => "|arg1:db_name|",
			"max_length" => "50",
		),
		"db_uname" => array(
			"method" => "textbox",
			"friendly_name" => "Username",
			"description" => "User name for the database login credentials.  If blank, the username configured for the local server is used.",
			"value" => "|arg1:db_uname|",
			"max_length" => "20",
		),
		"db_pword" => array(
			"method" => "textbox",
			"friendly_name" => "Password",
			"description" => "Password for the database login credentials.  If blank, the password configured for the local server is used.",
			"value" => "|arg1:db_pword|",
			"max_length" => "20",
		),
		"db_type" => array(
			"method" => "drop_array",
			"friendly_name" => "DB Type",
			"description" => "The database type.  Only mysql and mysqli are supported.  If your MySQL install is newer than 4.1.3, mysqli should be safe.",
			"value" => "|arg1:db_type|",
			"array" => array("mysql" => "mysql","mysqli" => "mysqli"),
		),
		"db_port" => array(
			"method" => "textbox",
			"friendly_name" => "DB Port",
			"description" => "The port the remote database listens to.  If blank, the locally configured port will be used.  If not configured (or 0), the MySQL default (3306) will be used.",
			"value" => "|arg1:db_port|",
			"max_length" => "8",
		),
		"db_ssl" => array(
			"method" => "checkbox",
			"friendly_name" => "Use SSL?",
			"description" => "If you don't know what this is, you should probably leave it unchecked.",
			"value" => "|arg1:db_ssl|",
		),
		"db_retries" => array(
			"method" => "textbox",
			"friendly_name" => "Retries",
			"description" => "Number of retries for the connection attempt.  If blank (or 0), 2 will be used (this differs from the Cacti default).  If your database connections aren't stable, you might want to increase this, but at the expense of the tree taking longer to build/display.  Also be aware that, if configured, Unified Trees will disable a connection that fails to connect after the number of retries has been exhausted.",
			"value" => "|arg1:db_retries|",
			"max_length" => "3",
		),
		"base_url" => array(
			"method" => "textbox",
			"friendly_name" => "Base URL",
			"description" => "The 'base' of the remote Cacti instance's URL, such as 'http://server.yourdomain.com/cacti/' (with trailing slash!).  You are strongly advised not to leave this blank; if it is blank, Unified Trees will attempt to use something akin to the DB server's address as configured above, plus the local server's configured \$url_path.",
			"value" => "|arg1:base_url|",
			"max_length" => "100",
		),
		"id" => array(
			"method" => "hidden_zero",
			"value" => "|arg1:id|"
			),
		"save_component_source" => array(
			"method" => "hidden",
			"value" => "1"
			)
		);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_GET["id"])) {
		$tree_source = db_fetch_row("select * from plugin_unifiedtrees_sources where id=" . $_GET["id"]);
		$header_label = "[edit: " .$tree_source['db_address']."-".$tree_source['db_name']. "]";
	}else{
		$header_label = "[new]";
		$_GET["id"] = 0;
	}

	html_start_box("<strong>Unified Trees Sources</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_ut_source_edit,
(isset($tree_source) ? $tree_source : array()))
		));

	html_end_box();

	form_save_button("tree_sources.php");
}

function tree_source() {
	global $colors, $host_actions;

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("sort_column", "sess_tree_sources_column", "db_address");
	load_current_session_value("sort_direction", "sess_tree_sources_sort_direction", "ASC");

	display_output_messages();

	html_start_box("<strong>Unified Trees Sources</strong>", "100%", $colors["header"], "3", "center", "tree_sources.php?action=edit");

	$display_text = array(
		"enable_db" => array("Enabled?", "ASC"),
		"db_address" => array("Database Address", "ASC"),
		"db_name" => array("Database", "ASC"),
		"db_uname" => array("Database User Name", "nosort"),
		"base_url" => array("Base URL", "ASC"),
	);

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$dts = db_fetch_assoc("SELECT *
		FROM plugin_unifiedtrees_sources
		ORDER BY " . $_REQUEST['sort_column'] . " " . $_REQUEST['sort_direction']);

	$i = 0;
	if (sizeof($dts)) {
		foreach ($dts as $dt) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, "line" . $dt["id"]); $i++;
			if($dt["enable_db"] == 'on') {
				$dbenabled = "<font color=green>Yes</font>";
			}else{
				$dbenabled = "<font color=red>No</font>";
			}
			form_selectable_cell($dbenabled, $dt["id"]);
			form_selectable_cell('<a class="linkEditMain" href="tree_sources.php?action=edit&id=' . $dt["id"] . '">' . $dt['db_address'] . '</a>', $dt["id"]);
			form_selectable_cell($dt["db_name"], $dt["id"]);
			form_selectable_cell($dt["db_uname"], $dt["id"]);
			form_selectable_cell($dt["base_url"], $dt["id"]);
			
			form_checkbox_cell($dt["base_url"], $dt["id"]);
			form_end_row();
		}
	}else{
		print "<tr><td><em>No Sources</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($host_actions);

	print "</form>\n";
}

function ut_db_conntest($db) {
	global $database_default, $database_username, $database_password, $database_port;
	if(!isset($db['db_name']) || $db['db_name'] == '') {
		$db['db_name'] = $database_default;
	}
	if(!isset($db['db_uname']) || $db['db_uname'] == '') {
		$db['db_uname'] = $database_username;
	}
	print "CHECK: $database_password\n";
	if(!isset($db['db_pword']) || $db['db_pword'] == '') {
		$db['db_pword'] = $database_password;
	}
	if(!isset($db['db_port']) || $db['db_port'] == 0 || $db['db_port'] == '') {
		if(!isset($database_port) || $database_port == '') {
			$db['db_port'] = "3306";
		}else{
			$db['db_port'] = $database_port;
		}
	}
	if(!isset($db['db_retries']) || $db['db_retries'] == 0 ||
	   $db['db_retries'] == '') {
		$db['db_retries'] = 2;
	}
	$dsn = $db['db_type']."://".rawurlencode($db['db_uname']).":".rawurlencode($db['db_pword'])."@".rawurlencode($db['db_address'])."/".rawurlencode($db['db_name'])."?persist";
	if($db['db_ssl'] == 'on') {
		if($db['db_type'] == "mysql") {
			$dsn .= "&clientflags=" . MYSQL_CLIENT_SSL;
		}elseif ($db['db_type'] == "mysqli") {
			$dsn .= "&clientflags=" . MYSQLI_CLIENT_SSL;
		}
	}
	if($db['db_port'] != "3306") {
		$dsn .= "&port=" . $port;
	}
	$attempt = 0;
	while($attempt < $db['db_retries']) {
		$testconn = ADONewConnection($dsn);
		if($testconn) {
			return TRUE;
		}
		$attempt++;
	}
	return FALSE;
}
?>
