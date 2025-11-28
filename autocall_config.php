<?php
/*
	FusionPBX
	Autocall API Configuration Management
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('autocall_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//config file path
	$config_file = __DIR__ . '/config.php';

//handle form submission
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {
		
		//get form data
		$allowed_ips = $_POST["allowed_ips"] ?? '';
		$api_users = $_POST["api_users"] ?? [];
		
		//process IPs (one per line)
		$ip_array = array_filter(array_map('trim', explode("\n", $allowed_ips)));
		
		//build config file content
		$config_content = "<?php\n";
		$config_content .= "/*\n";
		$config_content .= " * Autocall API Configuration\n";
		$config_content .= " * Auto-generated on " . date('Y-m-d H:i:s') . "\n";
		$config_content .= " */\n\n";
		
		//IP whitelist
		$config_content .= "// IP whitelist - Allowed IPs to access the API\n";
		$config_content .= "\$allowedIps = [\n";
		foreach ($ip_array as $ip) {
			$config_content .= "    '" . addslashes($ip) . "',\n";
		}
		$config_content .= "];\n\n";
		
		//Basic Auth credentials
		$config_content .= "// Basic Auth credentials - username => password\n";
		$config_content .= "\$validUsers = [\n";
		foreach ($api_users as $user) {
			if (!empty($user['username']) && !empty($user['password'])) {
				$config_content .= "    '" . addslashes($user['username']) . "' => '" . addslashes($user['password']) . "',\n";
			}
		}
		$config_content .= "];\n\n";
		$config_content .= "?>\n";
		
		//write to file
		if (file_put_contents($config_file, $config_content)) {
			$_SESSION["message"] = "Configuration saved successfully";
		} else {
			$_SESSION["message"] = "Failed to save configuration. Check file permissions.";
		}
		
		header("Location: autocall_config.php");
		return;
	}

//load existing config
	$allowed_ips_text = '';
	$api_users = [];
	
	if (file_exists($config_file)) {
		include($config_file);
		
		//convert IPs array to text
		if (isset($allowedIps) && is_array($allowedIps)) {
			$allowed_ips_text = implode("\n", $allowedIps);
		}
		
		//convert users array to editable format
		if (isset($validUsers) && is_array($validUsers)) {
			foreach ($validUsers as $username => $password) {
				$api_users[] = ['username' => $username, 'password' => $password];
			}
		}
	}

//additional includes
	require_once "resources/header.php";
	$document['title'] = "Autocall API Configuration";

?>

<script>
function add_user_row() {
	var table = document.getElementById('api_users_table').getElementsByTagName('tbody')[0];
	var row = table.insertRow(-1);
	var cell1 = row.insertCell(0);
	var cell2 = row.insertCell(1);
	var cell3 = row.insertCell(2);
	
	cell1.innerHTML = '<input class="formfld" type="text" name="api_users[' + table.rows.length + '][username]" placeholder="username">';
	cell2.innerHTML = '<input class="formfld" type="text" name="api_users[' + table.rows.length + '][password]" placeholder="password">';
	cell3.innerHTML = '<button type="button" class="btn btn-default" onclick="this.parentElement.parentElement.remove();">Delete</button>';
}
</script>

<form method='post' name='frm' id='frm'>

<div class='action_bar' id='action_bar'>
	<div class='heading'><b>Autocall API Configuration</b></div>
	<div class='actions'>
		<?php echo button::create(['type'=>'button','label'=>'Back','icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','onclick'=>"window.location='autocall_settings.php'"]); ?>
		<?php echo button::create(['type'=>'submit','label'=>'Save','icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save']); ?>
	</div>
	<div style='clear: both;'></div>
</div>

<table width='100%' border='0' cellpadding='0' cellspacing='0'>

<tr>
	<td colspan='2' style='padding: 10px 0;'>
		<b>Security Configuration</b><br>
		<span style='color: #888;'>Configure IP whitelist and Basic Authentication for the Autocall API</span>
	</td>
</tr>

<tr>
	<td width='30%' class='vncell' valign='top' align='left' nowrap='nowrap'>
		IP Whitelist
	</td>
	<td width='70%' class='vtable' align='left'>
		<textarea class='formfld' style='width: 90%; height: 150px;' name='allowed_ips'><?php echo escape($allowed_ips_text); ?></textarea>
		<br />
		Enter allowed IP addresses, <b>one per line</b> (do not use commas). Use <b>*</b> to allow all IPs (NOT RECOMMENDED for production).
		<br />
		Example:<br>
		<code style='background: #f5f5f5; padding: 5px; display: block;'>192.168.1.100<br>103.104.123.126<br>::1<br>*</code>
	</td>
</tr>

<tr>
	<td class='vncell' valign='top' align='left' nowrap='nowrap'>
		API Users
	</td>
	<td class='vtable' align='left'>
		<table id='api_users_table' class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>
			<thead>
				<tr>
					<th width='40%'>Username</th>
					<th width='40%'>Password</th>
					<th width='20%'>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$row_index = 0;
				if (!empty($api_users)) {
					foreach ($api_users as $user) {
						echo "<tr>\n";
						echo "	<td><input class='formfld' type='text' name='api_users[$row_index][username]' value='".escape($user['username'])."'></td>\n";
						echo "	<td><input class='formfld' type='text' name='api_users[$row_index][password]' value='".escape($user['password'])."'></td>\n";
						echo "	<td><button type='button' class='btn btn-default' onclick='this.parentElement.parentElement.remove();'>Delete</button></td>\n";
						echo "</tr>\n";
						$row_index++;
					}
				}
				?>
			</tbody>
		</table>
		<br />
		<button type='button' class='btn btn-default' onclick='add_user_row();'>Add User</button>
		<br /><br />
		Basic Authentication credentials for API access. Username and password required in HTTP headers.
	</td>
</tr>

<tr>
	<td colspan='2' style='padding: 20px 0 10px 0;'>
		<b>Current Configuration File</b>
	</td>
</tr>

<tr>
	<td class='vncell' valign='top'>
		File Path
	</td>
	<td class='vtable'>
		<code><?php echo $config_file; ?></code>
		<br />
		<?php if (file_exists($config_file)): ?>
			<span style='color: green;'>✓ File exists</span>
			<?php if (is_writable($config_file)): ?>
				<span style='color: green;'>✓ Writable</span>
			<?php else: ?>
				<span style='color: red;'>✗ Not writable - Run: chmod 664 <?php echo $config_file; ?></span>
			<?php endif; ?>
		<?php else: ?>
			<span style='color: orange;'>⚠ File does not exist yet (will be created on save)</span>
		<?php endif; ?>
	</td>
</tr>

<tr>
	<td class='vncell' valign='top'>
		Test API
	</td>
	<td class='vtable'>
		<code style='background: #f5f5f5; padding: 10px; display: block; white-space: pre-wrap;'>curl -X POST https://<?php echo $_SERVER['HTTP_HOST']; ?>/app/autocall/autocall.php \
  -u "username:password" \
  -H "Content-Type: application/json" \
  -d '{"campaign_id":"123","callee":"0987654321","destination":"1001","timeout":30}'</code>
	</td>
</tr>

</table>

</form>

<?php
//show the footer
	require_once "resources/footer.php";
?>
