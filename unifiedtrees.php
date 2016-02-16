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
	global $treenode;
	$othertree = read_config_option("unifiedtrees_other_tree");
	$default_tree_id = read_graph_config_option('default_tree_id');
	$our_base = read_config_option("unifiedtrees_base_url");
//	print "foldersTree = gFld(\"\", \"\")
// foldersTree.xID = \"root\"\n";
	print "\n<div id=\"jstree\">\n";
// I'd remove this to avoid multiserver/always treeid issues excempt that in
// my own case I have a "T" that needs to come before any "S".  Oh well.
// We can fix that with some ugly code.
	$treenames = array_keys($fulltree['tree']);
	sort($treenames);
	if(read_config_option("unifiedtrees_sort_trees") == 'on') {
		ksort($fulltree['tree'], SORT_STRING);
	}
	foreach($fulltree['tree'] as $treename => $tree) {
		if(!isset($othertree) || $treename != $othertree) {
			ut_print_leaves($treenames, $treename, $tree);
		}
		++$treeid;
	}
	if(isset($othertree) && isset($fulltree['tree'][$othertree])) {
		ut_print_leaves($treenames, $othertree, $fulltree['tree'][$othertree]);
	}
	print "</div>\n";
	?>
	<script type='text/javascript'>
<?php
	if ((!isset($_SESSION['sess_node_id']) && !isset($_REQUEST['tree_id'])) || isset($_REQUEST['select_first'])) {
		print "var node='tree_" . $default_tree_id . "';\n";
		print "var reset=true;\n";
	}elseif (isset($_REQUEST['nodeid']) && $_REQUEST['nodeid'] != '') {
		print "var node='" . $_REQUEST['nodeid'] . "';\n";
		print "var reset=false;\n";
	}elseif (isset($treenode)) {
		print "var node='".$treenode."';\n";
		print "var reset=true;\n";
	}elseif (isset($_REQUEST['tree_id'])) {
		print "var node='tree_" . $_REQUEST['tree_id'] . "';\n";
		print "var reset=false;\n";
	}elseif (isset($_SESSION['sess_node_id']) && $_SESSION['sess_node_id'] != '') {
		print "var node='" . $_SESSION['sess_node_id'] . "';\n";
		print "var reset=false;\n";
	}else{
		print "var node='';\n";
		print "var reset=true;\n";
	}
	if (isset($_REQUEST['leaf_id'])) {
		print "var leaf='" . $_REQUEST['leaf_id'] . "';\n";
	}else{
		print "var leaf='';\n";
	}
	print "var ourbase='".$our_base."'\n";
?>
	$(function () {
		$('#navigation').css('height', ($(window).height()-80)+'px');
		$(window).resize(function() {
			$('#navigation').css('height', ($(window).height()-80)+'px');
		});

		$("#jstree")
		.on('ready.jstree', function(e, data) {
			if (reset == true) {
				$('#jstree').jstree('clear_state');
			}
			if (node!='') {
				$('#jstree').jstree('set_theme', 'default', '<?php print $config['url_path'];?>include/js/themes/default/style.css');
				$('#jstree').jstree('deselect_all');
				$('#jstree').jstree('select_node', node);
				$.get($('#'+node+'_anchor').attr('href').replace('leaf_id=&', 'leaf_id='+leaf+'&').replace('action=tree', 'action=tree_content')+"&nodeid="+node, function(data) {
					$('#main').html(data);
				});
			}

			$('#navigation').show();
		})
		.on('set_state.jstree', function(e, data) {
			$('#jstree').jstree('deselect_all');
			$('#jstree').jstree('select_node', node);
		})
		.on('activate_node.jstree', function(e, data) {
			if (!data.node.a_attr.href.includes(ourbase)) {
				window.location.href = data.node.a_attr.href;
			}
			if (data.node.id) {
				$.get($('#'+data.node.id+'_anchor').attr('href').replace('action=tree', 'action=tree_content')+"&nodeid="+data.node.id, function(data) {
					$('#main').html(data);
				});
				node = data.node.id;
			} else {
				window.location.href = data.node.a_attr.href;
			}
		})
		.jstree({
			'core' : {
				'animation' : 0
			},
			'themes' : {
				'name' : 'default',
				'responsive' : true,
				'url' : true,
				'dots' : true
			},
			'plugins' : [ 'state', 'wholerow' ]
		});
	});
	</script>
<?php
}

// Yeah .. well I want this functionality and it's my plugin, so bite me.
function ut_print_leaves($treenames, $treename, $tree) {
	global $treenode;
	$our_base = read_config_option("unifiedtrees_base_url");
	$treeid = array_search($treename, $treenames);
// Tempted to remove this option.  Wierd shit can happen if you don't sort the
// leaves of a tree ... like the tree base not appearing properly.
// Even ugly code won't work here since leaf structures can get deep and
// complex.
	if(read_config_option("unifiedtrees_sort_leaves") == 'on') {
		ksort($tree['id'], SORT_STRING);
	}
	$lid = 1;
	print "<ul>\n";
	$currtier = 0;
	foreach($tree['id'] as $leafid => $leaf) {
//		print "ou".$leaf['tier']." = insFld(";
// I may want to fix one issue with not properly sorting the tree by moving
// the tier 0 case out of this loop, since tier 0 should be part of the root
// definition of a tree.  Maybe in 0.6 when I feel like screwing with it.
// Thing is, some other weird shit happens too, so sorting is still strongly
// recommended.
// In > 0.8.8b, they started using "node" where xID was used.
		if($currtier < $leaf['tier']) {
			ut_print_spaces($currtier);
			print "<ul>\n";
		}elseif ($currtier > $leaf['tier']) {
			for($i = $currtier; $i > $leaf['tier']; $i--) {
				ut_print_spaces($i);
				print "</ul>\n";
				ut_print_spaces($i);
				print "</li>\n";
			}
		}
		$currtier = $leaf['tier'];
		if($leaf['tier'] == 0) {
//			print "foldersTree";
			$xid = "tree_".$treeid;
		}else{
//			print "ou".($leaf['tier']-1);
			$xid = "node".$treeid."_".$lid;
			++$lid;
		}
//		print ", gFld(\"";
		$leaf['node'] = $xid;
		if(isset($_REQUEST['tree_id']) && (!isset($treenode) || $treenode == "")) {
$urltest = $our_base."graph_view.php?action=tree&amp;tree_id=".$_REQUEST['tree_id'];
if(isset($_REQUEST['leaf_id'])) {
	$urltest .= "&amp;leaf_id=".$_REQUEST['leaf_id'];
}
if($leaf['url'] == $urltest && (!isset($treenode) || $treenode == "")) {
	$treenode = $xid;
}
		}
		if(strpos($leaf['url'], $our_base) !== FALSE) {
			$leaf['url'] .= "&amp;nodeid=$xid";
		}
		ut_print_spaces($leaf['tier']);
		print "<li id='$xid'";
		if($leaf['host_id'] > 0) {
//			print "Host: ";
			print " data-jstree='{\"icon\" : \"/images/server.png\" }'";
		}
		print "><a class='treepick' href=\"".$leaf['url']."&amp;host_group_data=\">";
		if($leaf['host_id'] > 0) {
			print "Host: ";
		}
		print $leaf['name']."</a>\n";
		ut_print_spaces($leaf['tier']);
		if($leaf['host_id'] > 0) {
			print "</li>\n";
		}
// $leaf['name']."\", \"".$leaf['url']."\"))
// ou".$leaf['tier'].".xID = \"".$xid."\"\n";
	}
	for ($i = $currtier; $i > 0; $i--) {
		ut_print_spaces($i-1);
		print "</ul>\n";
		ut_print_spaces($i-1);
		print "</li>\n";
	}
	print "</ul>\n";
}

function ut_print_spaces($tier) {
	for ($i = 1; $i <= $tier; $i++) {
		print "    ";
	}
}

?>
