<?php
	
	//application details
		$apps[$x]['name'] = "Autocall";
		$apps[$x]['uuid'] = "a1b2c3d4-5678-90ab-cdef-1234567890ab";
		$apps[$x]['category'] = "System";
		$apps[$x]['subcategory'] = "";
		$apps[$x]['version'] = "1.0";
		$apps[$x]['license'] = "Mozilla Public License 1.1";
		$apps[$x]['url'] = "http://www.fusionpbx.com";
		$apps[$x]['description']['en-us'] = "Autocall API with ERP integration for automatic call campaigns";
		
		
	//permission details
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = "autocall_view";
		$apps[$x]['permissions'][$y]['menu']['uuid'] = "f1e2d3c4-b5a6-9788-0fed-cba987654321";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;		
		$apps[$x]['permissions'][$y]['name'] = "autocall_edit";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "autocall_add";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "autocall_delete";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;

	//schema details
		$y = 0;
		$apps[$x]['db'][$y]['table']['name'] = "v_autocall_settings";
		$apps[$x]['db'][$y]['table']['parent'] = "";
		
		$z = 0;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "domain_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "foreign";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['table'] = "v_domains";
		$apps[$x]['db'][$y]['fields'][$z]['key']['reference']['field'] = "domain_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Domain UUID";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "autocall_setting_uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = "primary";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Primary Key";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "company_name";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Company name identifier";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "company_url";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Company ERP URL";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "bearer_token";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Bearer token for ERP API";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "esl_host";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "FreeSWITCH ESL host";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "esl_port";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "FreeSWITCH ESL port";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "esl_password";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "FreeSWITCH ESL password";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "domain";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "FreeSWITCH domain";
		
	$z++;
	$apps[$x]['db'][$y]['fields'][$z]['name'] = "enabled";
	$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
	$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Enable or disable this setting";
	
	$z++;
	$apps[$x]['db'][$y]['fields'][$z]['name'] = "description";
		$apps[$x]['db'][$y]['fields'][$z]['type'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Description";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "insert_date";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "timestamptz";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "date";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "timestamp";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Insert date";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "insert_user";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Insert user";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "update_date";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "timestamptz";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "date";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "timestamp";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Update date";
		
		$z++;
		$apps[$x]['db'][$y]['fields'][$z]['name'] = "update_user";
		$apps[$x]['db'][$y]['fields'][$z]['type']['pgsql'] = "uuid";
		$apps[$x]['db'][$y]['fields'][$z]['type']['sqlite'] = "text";
		$apps[$x]['db'][$y]['fields'][$z]['type']['mysql'] = "char(36)";
		$apps[$x]['db'][$y]['fields'][$z]['description']['en-us'] = "Update user";

?>