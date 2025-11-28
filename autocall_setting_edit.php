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

//logging function
	function logEdit($message) {
		$logFile = '/var/log/freeswitch/autocall.log';
		$timestamp = date('Y-m-d H:i:s');
		$logMessage = "[$timestamp] EDIT: $message" . PHP_EOL;
		file_put_contents($logFile, $logMessage, FILE_APPEND);
	}

//check permissions
	if (permission_exists('autocall_add') || permission_exists('autocall_edit')) {
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

//set the action as an add or update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$autocall_setting_uuid = check_str($_REQUEST["id"]);
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (count($_POST) > 0) {
		logEdit("Form submitted - Action: $action, Company: " . check_str($_POST["company_name"]));
		
		$company_name = check_str($_POST["company_name"]);
		$company_url = check_str($_POST["company_url"]);
		$bearer_token = check_str($_POST["bearer_token"]);
		$esl_host = check_str($_POST["esl_host"]);
		$esl_port = check_str($_POST["esl_port"]);
		$esl_password = check_str($_POST["esl_password"]);
		$domain = check_str($_POST["domain"]);
		$enabled = check_str($_POST["enabled"]);
		$description = check_str($_POST["description"]);
	}

//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

		//get the uuid from the POST
		if ($action == "update") {
			$autocall_setting_uuid = check_str($_POST["autocall_setting_uuid"]);
		}

		//check for required fields
		if (strlen($company_name) == 0) { $msg .= $text['message-required'].$text['label-company_name']."<br>\n"; }
		if (strlen($company_url) == 0) { $msg .= $text['message-required'].$text['label-company_url']."<br>\n"; }
		if (strlen($bearer_token) == 0) { $msg .= $text['message-required'].$text['label-bearer_token']."<br>\n"; }
		
		if (strlen($msg) > 0) {
			logEdit("[ERROR] Validation failed: $msg");
		}

		//show the message
		if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
			require_once "resources/header.php";
			require_once "resources/persist_form_var.php";
			echo "<div align='center'>\n";
			echo "<table><tr><td>\n";
			echo $msg."<br />";
			echo "</td></tr></table>\n";
			persistformvar($_POST);
			echo "</div>\n";
			require_once "resources/footer.php";
			return;
		}

		//add or update the database
		logEdit("Checking persistformvar: " . ($_POST["persistformvar"] ?? 'not set'));
		
		if ($_POST["persistformvar"] != "true") {
			logEdit("Building array for database save - Action: $action");
			
			//build array
			$array['autocall_settings'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
			$array['autocall_settings'][0]['company_name'] = $company_name;
			$array['autocall_settings'][0]['company_url'] = $company_url;
			$array['autocall_settings'][0]['bearer_token'] = $bearer_token;
			$array['autocall_settings'][0]['esl_host'] = $esl_host;
			$array['autocall_settings'][0]['esl_port'] = $esl_port;
			$array['autocall_settings'][0]['esl_password'] = $esl_password;
			$array['autocall_settings'][0]['domain'] = $domain;
			$array['autocall_settings'][0]['enabled'] = $enabled;
			$array['autocall_settings'][0]['description'] = $description;

			if ($action == "add" && permission_exists('autocall_add')) {
				$new_uuid = uuid();
				$array['autocall_settings'][0]['autocall_setting_uuid'] = $new_uuid;
				$array['autocall_settings'][0]['insert_date'] = date('Y-m-d H:i:s');
				$array['autocall_settings'][0]['insert_user'] = $_SESSION['user_uuid'];
				
				logEdit("[ADD] Saving: $company_name");
				
				// Try using database save method
				$database = new database;
				$database->app_name = 'autocall';
				$database->app_uuid = 'a1b2c3d4-5678-90ab-cdef-1234567890ab';
				$database->debug = true; // Enable debug mode
				
				try {
					$database->save($array);
					logEdit("[ADD] ORM save() completed");
				} catch (Exception $e) {
					logEdit("[ADD] [ERROR] ORM save() exception: " . $e->getMessage());
				}
				
				// Verify if record was actually saved
				$check_sql = "SELECT COUNT(*) as count FROM v_autocall_settings WHERE autocall_setting_uuid = :uuid";
				$check_params = ['uuid' => $new_uuid];
				$check_result = $database->select($check_sql, $check_params, 'row');
				$record_exists = $check_result['count'] > 0;
				
				logEdit("[ADD] Verification check - Record exists: " . ($record_exists ? 'YES' : 'NO'));
				
				// If ORM failed, try raw SQL insert as fallback
				if (!$record_exists) {
					logEdit("[ADD] ORM failed silently, trying raw SQL insert...");
					
					$sql = "INSERT INTO v_autocall_settings (
						autocall_setting_uuid, domain_uuid, company_name, company_url, bearer_token,
						esl_host, esl_port, esl_password, domain, enabled, description,
						insert_date, insert_user
					) VALUES (
						:autocall_setting_uuid, :domain_uuid, :company_name, :company_url, :bearer_token,
						:esl_host, :esl_port, :esl_password, :domain, :enabled, :description,
						now(), :insert_user
					)";
					
					$params = [
						'autocall_setting_uuid' => $new_uuid,
						'domain_uuid' => $_SESSION['domain_uuid'],
						'company_name' => $company_name,
						'company_url' => $company_url,
						'bearer_token' => $bearer_token,
						'esl_host' => $esl_host,
						'esl_port' => $esl_port,
						'esl_password' => $esl_password,
						'domain' => $domain,
						'enabled' => $enabled,
						'description' => $description,
						'insert_user' => $_SESSION['user_uuid']
					];
					
					try {
						$database->execute($sql, $params);
						logEdit("[ADD] Raw SQL insert SUCCESS");
					} catch (Exception $e) {
						logEdit("[ADD] [ERROR] Raw SQL insert failed: " . $e->getMessage());
					}
				} else {
					logEdit("[ADD] [SUCCESS] Record verified in database");
				}
				
				unset($array);

				$_SESSION["message"] = $text['message-add'];
				logEdit("[ADD] Redirecting to autocall_settings.php");
				header("Location: autocall_settings.php");
				return;
			} else {
				logEdit("[ADD] [ERROR] Permission denied or action not 'add' - Action: $action, Permission: " . (permission_exists('autocall_add') ? 'YES' : 'NO'));
			}

			if ($action == "update" && permission_exists('autocall_edit')) {
				$array['autocall_settings'][0]['autocall_setting_uuid'] = $autocall_setting_uuid;
				$array['autocall_settings'][0]['update_date'] = date('Y-m-d H:i:s');
				$array['autocall_settings'][0]['update_user'] = $_SESSION['user_uuid'];

				logEdit("[UPDATE] Updating: $company_name");
				
				$database = new database;
				$database->app_name = 'autocall';
				$database->app_uuid = 'a1b2c3d4-5678-90ab-cdef-1234567890ab';
				
				try {
					$database->save($array);
					logEdit("[UPDATE] ORM save() completed");
				} catch (Exception $e) {
					logEdit("[UPDATE] [ERROR] ORM exception: " . $e->getMessage());
				}
				
				// If ORM doesn't work, use raw SQL
				$sql = "UPDATE v_autocall_settings SET
					company_name = :company_name,
					company_url = :company_url,
					bearer_token = :bearer_token,
					esl_host = :esl_host,
					esl_port = :esl_port,
					esl_password = :esl_password,
					domain = :domain,
					enabled = :enabled,
					description = :description,
					update_date = now(),
					update_user = :update_user
					WHERE autocall_setting_uuid = :autocall_setting_uuid
					AND domain_uuid = :domain_uuid";
				
				$params = [
					'company_name' => $company_name,
					'company_url' => $company_url,
					'bearer_token' => $bearer_token,
					'esl_host' => $esl_host,
					'esl_port' => $esl_port,
					'esl_password' => $esl_password,
					'domain' => $domain,
					'enabled' => $enabled,
					'description' => $description,
					'update_user' => $_SESSION['user_uuid'],
					'autocall_setting_uuid' => $autocall_setting_uuid,
					'domain_uuid' => $_SESSION['domain_uuid']
				];
				
				try {
					$database->execute($sql, $params);
					logEdit("[UPDATE] [SUCCESS] Raw SQL update completed");
				} catch (Exception $e) {
					logEdit("[UPDATE] [ERROR] Raw SQL update failed: " . $e->getMessage());
				}
				
				unset($array);

				$_SESSION["message"] = $text['message-update'];
				logEdit("[UPDATE] Redirecting to autocall_settings.php");
				header("Location: autocall_settings.php");
				return;
			} else {
				logEdit("[UPDATE] [ERROR] Permission denied or action not 'update' - Action: $action, Permission: " . (permission_exists('autocall_edit') ? 'YES' : 'NO'));
			}
		}
	}

//pre-populate the form
		if (count($_GET) > 0 && $_POST["persistformvar"] != "true") {
		$autocall_setting_uuid = check_str($_GET["id"]);
		
		$sql = "select * from v_autocall_settings ";
		$sql .= "where domain_uuid = '".$_SESSION['domain_uuid']."' ";
		$sql .= "and autocall_setting_uuid = '$autocall_setting_uuid' ";
		
		$database = new database;
		$row = $database->select($sql, null, 'row');
		
		if (is_array($row) && sizeof($row) != 0) {
			$company_name = $row["company_name"];
			$company_url = $row["company_url"];
			$bearer_token = $row["bearer_token"];
			$esl_host = $row["esl_host"];
			$esl_port = $row["esl_port"];
			$esl_password = $row["esl_password"];
			$domain = $row["domain"];
			$enabled = $row["enabled"];
			$description = $row["description"];
		}
		unset($sql, $database, $row);
	}

//set defaults
	if (strlen($esl_host) == 0) { $esl_host = "127.0.0.1"; }
	if (strlen($esl_port) == 0) { $esl_port = "8021"; }
	if (strlen($esl_password) == 0) { $esl_password = "ClueCon"; }
	if (strlen($enabled) == 0) { $enabled = "true"; }

//additional includes
	require_once "resources/header.php";
	$document['title'] = $text['title-autocall_settings'];

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['header-autocall_settings']." (Add)</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['header-autocall_settings']." (Edit)</b>";
	}
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','onclick'=>"window.location='autocall_settings.php'"]);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-company_name']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='company_name' maxlength='255' value=\"".escape($company_name)."\">\n";
	echo "<br />\n";
	echo "Enter company name (e.g., erp.zozin.vn, locnuoc365.xyz, zomzem.xyz)\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-company_url']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='company_url' maxlength='255' value=\"".escape($company_url)."\">\n";
	echo "<br />\n";
	echo "Full URL to ERP API endpoint (e.g., https://erp.zozin.vn)\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-bearer_token']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<textarea class='formfld' style='width: 90%; height: 80px;' name='bearer_token'>".escape($bearer_token)."</textarea>\n";
	echo "<br />\n";
	echo "Bearer token for ERP API authentication\n";
	echo "</td>\n";
	echo "</tr>\n";

	// Hidden fields for ESL settings (using defaults)
	echo "<input type='hidden' name='esl_host' value=\"".escape($esl_host)."\">\n";
	echo "<input type='hidden' name='esl_port' value=\"".escape($esl_port)."\">\n";
	echo "<input type='hidden' name='esl_password' value=\"".escape($esl_password)."\">\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-domain']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='domain' maxlength='255' value=\"".escape($domain)."\">\n";
	echo "<br />\n";
	echo "FreeSWITCH domain (e.g., tongdai.zozin.vn)\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-enabled']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='enabled'>\n";
	echo "		<option value='true' ".($enabled == 'true' ? 'selected' : '').">True</option>\n";
	echo "		<option value='false' ".($enabled == 'false' ? 'selected' : '').">False</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo "Enable or disable this configuration\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<textarea class='formfld' style='width: 90%;' name='description' rows='4'>".escape($description)."</textarea>\n";
	echo "<br />\n";
	echo "Enter a description (optional)\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	if ($action == "update") {
		echo "<input type='hidden' name='autocall_setting_uuid' value='".escape($autocall_setting_uuid)."'>\n";
	}
	echo "<input type='hidden' name='domain_uuid' value='".escape($_SESSION['domain_uuid'])."'>\n";

	echo "</form>";

//show the footer
	require_once "resources/footer.php";
?>
