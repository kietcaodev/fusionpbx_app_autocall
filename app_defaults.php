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

	Contributor(s):
*/

if ($domains_processed == 1) {

	//fix enabled column type from boolean to text if needed (for PostgreSQL)
	if ($db_type == "pgsql") {
		$sql = "SELECT data_type FROM information_schema.columns ";
		$sql .= "WHERE table_name = 'v_autocall_settings' AND column_name = 'enabled'";
		$result = $database->select($sql, null, 'row');
		
		if ($result && $result['data_type'] == 'boolean') {
			// Convert boolean to text
			$sql = "ALTER TABLE v_autocall_settings ALTER COLUMN enabled TYPE text";
			$database->execute($sql, null);
		}
	}

	//set enabled to true if it is null or empty
	$sql = "select * from v_autocall_settings ";
	$sql .= "where enabled is null or enabled = '' ";
	$autocall_settings = $database->select($sql, null, 'all');
	if (is_array($autocall_settings) && @sizeof($autocall_settings) != 0) {
		foreach($autocall_settings as $row) {
			$sql = "update v_autocall_settings ";
			$sql .= "set enabled = 'true' ";
			$sql .= "where autocall_setting_uuid = :autocall_setting_uuid ";
			$parameters['autocall_setting_uuid'] = $row['autocall_setting_uuid'];
			$database->execute($sql, $parameters);
			unset($parameters);
		}
	}

}

?>
