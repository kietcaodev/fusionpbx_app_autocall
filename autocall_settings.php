<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//logging function
	function logList($message) {
		$logFile = '/var/log/freeswitch/autocall.log';
		$timestamp = date('Y-m-d H:i:s');
		$logMessage = "[$timestamp] LIST: $message" . PHP_EOL;
		file_put_contents($logFile, $logMessage, FILE_APPEND);
	}

//check permissions
	if (permission_exists('autocall_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the settings
	$settings = new settings(['database' => $database, 'domain_uuid' => $_SESSION['domain_uuid'] ?? '', 'user_uuid' => $_SESSION['user_uuid'] ?? '']);

//delete records
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete' && permission_exists('autocall_delete')) {
		$autocall_setting_uuid = check_str($_REQUEST['id']);
		
		$database = new database;
		$database->table = "v_autocall_settings";
		$database->where[0]['name'] = 'autocall_setting_uuid';
		$database->where[0]['value'] = $autocall_setting_uuid;
		$database->where[0]['operator'] = '=';
		$database->where[1]['name'] = 'domain_uuid';
		$database->where[1]['value'] = $_SESSION['domain_uuid'];
		$database->where[1]['operator'] = '=';
		$database->delete();
		unset($database);
		
		header('Location: autocall_settings.php');
		exit;
	}

//get the http values and set them as variables
	$order_by = check_str($_GET["order_by"]);
	$order = check_str($_GET["order"]);

//handle search term
	$search = check_str($_GET["search"]);
	if (strlen($search) > 0) {
		$sql_mod = "and (company_name ILIKE '%".$search."%' or company_url ILIKE '%".$search."%' or description ILIKE '%".$search."%') ";
	}
	if (strlen($order_by) < 1) {
		$order_by = "company_name";
		$order = "ASC";
	}

//get total count from the database
	$sql = "select count(*) as num_rows from v_autocall_settings where domain_uuid = '".$_SESSION['domain_uuid']."' ".$sql_mod." ";
	$database = new database;
	$total_settings = $database->select($sql, null, 'column');
	unset($sql);

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$param = "&search=".$search."&order_by=".$order_by."&order=".$order;
	if (!isset($_GET['page'])) { $_GET['page'] = 0; }
	$_GET['page'] = check_str($_GET['page']);
	list($paging_controls_mini, $rows_per_page, $var_3) = paging($total_settings, $param, $rows_per_page, true); //top
	list($paging_controls, $rows_per_page, $var_3) = paging($total_settings, $param, $rows_per_page); //bottom
	$offset = $rows_per_page * $_GET['page'];

//get all the settings from the database
	$sql = "SELECT * FROM v_autocall_settings \n";
	$sql .= "WHERE domain_uuid = '".$_SESSION['domain_uuid']."' \n";
	$sql .= $sql_mod; //add search mod from above
	$sql .= "ORDER BY ".$order_by." ".$order." \n";
	$sql .= "limit $rows_per_page offset $offset ";
	$database = new database;
	$autocall_settings = $database->select($sql, null);
	unset($database);

//additional includes
	require_once "resources/header.php";
	$document['title'] = $text['title-autocall_settings'];

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-autocall_settings']."</b><div class='count'>".number_format($total_settings)."</div></div>\n";
	echo "	<div class='actions'>\n";
	echo "		<form method='get' action=''>\n";
	if (permission_exists('autocall_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'style'=>'margin-right: 15px;','onclick'=>"window.location='autocall_setting_edit.php'"]);
	}
	if (permission_exists('autocall_edit')) {
		echo button::create(['type'=>'button','label'=>'Configuration','icon'=>'fa-cog','style'=>'margin-right: 15px;','onclick'=>"window.location='autocall_config.php'"]);
	}
	echo "			<input type='text' class='txt' style='width: 150px' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-autocall_settings']."\n";
	echo "<br /><br />\n";

	echo "<form name='frm' method='post' action=''>\n";

	echo "<div class='card'>\n";
	echo "<table class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo th_order_by('company_name', $text['label-company_name'], $order_by, $order);
	echo th_order_by('company_url', $text['label-company_url'], $order_by,$order);
	echo th_order_by('domain', $text['label-domain'], $order_by,$order);
	echo th_order_by('enabled', $text['label-enabled'], $order_by,$order);
	echo "<th>".$text['label-description']."</th>";
	echo "<td class='list_control_icons'>";
	if (permission_exists('autocall_edit') && $autocall_settings) {
		echo "<a href='autocall_setting_edit.php' title='".$text['button-add']."'>".$v_link_label_add."</a>";
	}
	echo "</td>\n";
	echo "</tr>\n";

	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";

	if (isset($autocall_settings)) foreach ($autocall_settings as $key => $row) {
		$list_row_url = "autocall_setting_edit.php?id=".urlencode($row['autocall_setting_uuid']);
		echo "<tr href='".$list_row_url."'>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row['company_name'])."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row['company_url'])."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row['domain'])."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row['enabled'])."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row['description'])."</td>\n";
		echo "	<td class='list_control_icons'>";
		if (permission_exists('autocall_edit')) {
			echo "<a href='autocall_setting_edit.php?id=".urlencode($row['autocall_setting_uuid'])."' title='".$text['button-edit']."'>".$v_link_label_edit."</a>";
		}
		if (permission_exists('autocall_delete')) {
			echo "<a href='?action=delete&id=".urlencode($row['autocall_setting_uuid'])."' title='".$text['button-delete']."' onclick=\"return confirm('".$text['confirm-delete']."')\">".$v_link_label_delete."</a>";
		}
		echo "	</td>\n";
		echo "</tr>\n";
		$c = ($c==0) ? 1 : 0;
	}

	echo "</table>";
	echo "</div>\n";
	echo $paging_controls."\n";
	echo "</form>";

//show the footer
	require_once "resources/footer.php";
?>
