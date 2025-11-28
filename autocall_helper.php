<?php
/*
	FusionPBX Autocall Helper Functions
	Version: 1.0
*/

/**
 * Log helper function
 */
function logHelper($message) {
    $logFile = '/var/log/freeswitch/autocall.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Get available extension from ERP API
 * 
 * @param string $company_name - Company name (e.g., erp.zozin.vn)
 * @param string $customer_id - Customer ID
 * @return array - ['success' => bool, 'extension' => string, 'error' => string]
 */
function getAvailableExtension($company_name, $customer_id, $domain_uuid = null) {
    
    // Get bearer token from database
    $sql = "SELECT company_url, bearer_token, enabled FROM v_autocall_settings ";
    $sql .= "WHERE company_name = :company_name ";
    
    $parameters = [];
    $parameters['company_name'] = $company_name;
    
    // If domain_uuid provided, filter by it; otherwise get first enabled config
    if ($domain_uuid) {
        $sql .= "AND domain_uuid = :domain_uuid ";
        $parameters['domain_uuid'] = $domain_uuid;
    }
    
    $sql .= "AND enabled = 'true' ";
    $sql .= "LIMIT 1";
    
    $db = new database;
    $setting = $db->select($sql, $parameters, 'row');
    unset($db);
    
    if (!$setting || empty($setting['bearer_token'])) {
        logHelper("Helper: [ERROR] Config not found for: $company_name");
        return [
            'success' => false,
            'error' => 'Bearer token not found for company: ' . $company_name
        ];
    }
    
    logHelper("Helper: Config found for $company_name");
    
    $company_url = $setting['company_url'];
    $bearer_token = $setting['bearer_token'];
    
    // Build API URL
    $api_url = rtrim($company_url, '/') . '/api/crm/campaign/get-available-extension';
    
    // Prepare POST data
    $post_data = [
        'customer_id' => $customer_id
    ];
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $bearer_token,
        'X-Requested-With: XMLHttpRequest'
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    logHelper("Helper: ERP API returned HTTP $http_code");
    
    // Check for cURL errors
    if ($response === false) {
        logHelper("Helper: [ERROR] cURL error: $curl_error");
        return [
            'success' => false,
            'error' => 'cURL error: ' . $curl_error
        ];
    }
    
    // Check HTTP response code
    if ($http_code != 200) {
        logHelper("Helper: [ERROR] HTTP error code: $http_code");
        return [
            'success' => false,
            'error' => 'HTTP error: ' . $http_code
        ];
    }
    
    // Parse JSON response
    $result = json_decode($response, true);
    
    if (!$result) {
        logHelper("Helper: [ERROR] Invalid JSON from ERP");
        return [
            'success' => false,
            'error' => 'Invalid JSON response'
        ];
    }
    
    // Check if success
    if (isset($result['success']) && $result['success'] === true && isset($result['extension'])) {
        logHelper("Helper: Got extension {$result['extension']} for customer $customer_id");
        return [
            'success' => true,
            'extension' => $result['extension']
        ];
    }
    
    // Handle error response
    $error_msg = isset($result['msg']) ? $result['msg'] : 'Unknown error';
    logHelper("Helper: [ERROR] ERP returned error: $error_msg");
    return [
        'success' => false,
        'error' => $error_msg
    ];
}

/**
 * Get FreeSWITCH ESL settings from database
 * 
 * @param string $company_name - Company name
 * @return array - ['esl_host', 'esl_port', 'esl_password', 'domain']
 */
function getESLSettings($company_name, $domain_uuid = null) {
    
    $sql = "SELECT esl_host, esl_port, esl_password, domain FROM v_autocall_settings ";
    $sql .= "WHERE company_name = :company_name ";
    
    $parameters = [];
    $parameters['company_name'] = $company_name;
    
    // If domain_uuid provided, filter by it
    if ($domain_uuid) {
        $sql .= "AND domain_uuid = :domain_uuid ";
        $parameters['domain_uuid'] = $domain_uuid;
    }
    
    $sql .= "AND enabled = 'true' ";
    $sql .= "LIMIT 1";
    
    $db = new database;
    $setting = $db->select($sql, $parameters, 'row');
    unset($db);
    
    if ($setting) {
        return [
            'esl_host' => $setting['esl_host'] ?? '127.0.0.1',
            'esl_port' => $setting['esl_port'] ?? '8021',
            'esl_password' => $setting['esl_password'] ?? 'ClueCon',
            'domain' => $setting['domain'] ?? ''
        ];
    }
    
    // Return defaults if not found
    logHelper("Helper: Using default ESL settings");
    return [
        'esl_host' => '127.0.0.1',
        'esl_port' => '8021',
        'esl_password' => 'ClueCon',
        'domain' => ''
    ];
}

?>
