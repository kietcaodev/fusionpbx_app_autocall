<?php
// Include FusionPBX resources first (loads database, sessions, etc)
require_once dirname(__DIR__, 2) . "/resources/require.php";

// Include the FreeSWITCH ESL library and configuration file
require_once 'freeSwitchEsl.php';
require_once __DIR__ . '/autocall_helper.php';  // Include helper functions

// Include custom config if exists (for IP whitelist and basic auth)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Set the header to specify that the response will be JSON
header('Content-Type: application/json');

// Function to check if the user is authorized
function isAuthorized($username, $password) {
    global $validUsers;  // Use the $validUsers array from config.php

    // Check if the username exists and if the password matches
    return isset($validUsers[$username]) && $validUsers[$username] === $password;
}

// Function to log requests
function logRequest($message) {
    $logFile = '/var/log/freeswitch/autocall.log';  // Log file path
    $timestamp = date('Y-m-d H:i:s');  // Get the current timestamp
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);  // Write log to file
}

// Function to check if the IP is allowed
function isIpAllowed($ip) {
    global $allowedIps;  // Use the $allowedIps array from config.php
    
    // If allowedIps not set, deny all
    if (!isset($allowedIps) || empty($allowedIps)) {
        return false;
    }
    
    // If wildcard *, allow all
    if (in_array('*', $allowedIps)) {
        return true;
    }
    
    // Check if IP in whitelist
    return in_array($ip, $allowedIps);
}

// Get the client's IP address
$clientIp = $_SERVER['REMOTE_ADDR'];

// Check if the client's IP is allowed
if (!isIpAllowed($clientIp)) {
    logRequest("API Forbidden: IP $clientIp not in whitelist");
    echo json_encode([
        'status' => '403',
        'message' => 'Forbidden: Your IP is not allowed.'
    ]);
    http_response_code(403); // Set the response code to 403 Forbidden
    exit; // Stop further execution
}

// Check if the Authorization header is set
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    // If the user is not authorized, return a 401 Unauthorized response
    if (!isAuthorized($username, $password)) {
        logRequest("API Unauthorized: Invalid credentials");
        echo json_encode([
            'status' => '401',
            'message' => 'Unauthorized access. Invalid credentials.'
        ]);
        http_response_code(401); // Set the response code to 401 Unauthorized
        exit; // Stop further execution
    }
} else {
    // If the authorization header is missing
    logRequest("API Unauthorized: Missing credentials");
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please provide valid credentials.'
    ]);
    http_response_code(401); // Set the response code to 401 Unauthorized
    exit; // Stop further execution
}

// Read the raw POST data
$inputData = file_get_contents("php://input");

// Decode the JSON data into an associative array
$data = json_decode($inputData, true);

// Log incoming request
logRequest("API Request from IP $clientIp");

// Check if required parameters are provided in the JSON body
if (isset($data['campaign_id']) && isset($data['callee']) && isset($data['timeout'])) {
    $campaign_id = $data['campaign_id'];
    $callee = $data['callee'];
    $destination = isset($data['destination']) ? $data['destination'] : '';
    $customer_id = isset($data['customer_id']) ? $data['customer_id'] : '';
    $company_name = isset($data['company_name']) ? $data['company_name'] : '';
    $timeout = $data['timeout'];

    // Sanitize and validate inputs
    $campaign_id = preg_replace('/[^0-9]/', '', $campaign_id);
    $callee = preg_replace('/[^0-9]/', '', $callee);
    $timeout = intval($timeout);
    
    // Get domain_uuid from session or set to null for multi-domain support
    $domain_uuid = isset($_SESSION['domain_uuid']) ? $_SESSION['domain_uuid'] : null;
    
    // If destination is empty, we need to get it from ERP API
    if (empty($destination)) {
        if (empty($customer_id) || empty($company_name)) {
            logRequest("API Error: Missing customer_id or company_name");
            echo json_encode([
                'status' => '400',
                'message' => 'customer_id and company_name are required when destination is empty.'
            ]);
            http_response_code(400);
            exit;
        }
        
        // Get available extension from ERP
        $erp_result = getAvailableExtension($company_name, $customer_id, $domain_uuid);
        
        if (!$erp_result['success']) {
            logRequest("API Error: ERP lookup failed - " . $erp_result['error']);
            echo json_encode([
                'status' => '500',
                'message' => 'Failed to get available extension: ' . $erp_result['error']
            ]);
            http_response_code(500);
            exit;
        }
        
        $destination = $erp_result['extension'];
        logRequest("API Success: Got extension $destination");
    }

    // Function to make a click-to-call action
    function AutoCall($campaign_id, $callee, $destination, $timeout, $company_name = '', $customer_id = '') {
        // Create an instance of the FreeSWITCH ESL class
        $freeswitch = new Freeswitchesl();
		
		// Generate a custom job UUID (using uniqid as an example)
		function generateUUID() {
			$data = random_bytes(16); // Generate 16 random bytes
			
			// Set the version (4) and variant (10xx) bits in the UUID
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4 UUID
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 10xx
			
			// Format the bytes into a UUID string
			return sprintf(
				'%08s-%04s-%04s-%04s-%12s',
				bin2hex(substr($data, 0, 4)),
				bin2hex(substr($data, 4, 2)),
				bin2hex(substr($data, 6, 2)),
				bin2hex(substr($data, 8, 2)),
				bin2hex(substr($data, 10, 6))
			);
		}

		$origination_uuid = generateUUID();
		
		// Get domain_uuid from session or use null
		$domain_uuid = isset($_SESSION['domain_uuid']) ? $_SESSION['domain_uuid'] : null;
		
		// Get ESL settings from database if company_name is provided
		if (!empty($company_name)) {
			$esl_settings = getESLSettings($company_name, $domain_uuid);
			$esl_host = $esl_settings['esl_host'];
			$esl_port = $esl_settings['esl_port'];
			$esl_password = $esl_settings['esl_password'];
			$domain = $esl_settings['domain'];
		} else {
			// Use defaults
			$esl_host = "127.0.0.1";
			$esl_port = "8021";
			$esl_password = "ClueCon";
			$domain = 'tongdai.zozin.vn';
		}
		
        // Connect to FreeSWITCH ESL interface
        $connect = $freeswitch->connect($esl_host, $esl_port, $esl_password);

        if ($connect) {
            // Construct the originate command to start the call
			$originateCommand_autocall = "bgapi originate {origination_uuid={$origination_uuid},ignore_early_media=true,call_timeout={$timeout},origination_caller_id_name={$callee},origination_caller_id_number={$callee},effective_caller_id_name={$callee},effective_caller_id_number={$callee},domain={$domain},domain_name={$domain},accountcode='{$domain}',toll_allow=''}loopback/{$callee}/{$domain} ${destination} XML {$domain} {$callee} {$callee}"; 
			
			logRequest("API Call: campaign=$campaign_id, callee=$callee, dest=$destination");

            // Send the originate command to FreeSWITCH
            $freeswitch->api($originateCommand_autocall);
			
            // Trim the uuid value to remove unwanted newlines or spaces
            $uuid = $origination_uuid;  // This removes leading and trailing whitespace and newlines

            // If the response starts with +OK (e.g., +OK 9a2c3e42-7702-4d76-9dd5-86d2e0d0c583)
            if (strpos($uuid, '+OK ') === 0) {
                $uuid = substr($uuid, 4);  // Remove the '+OK ' prefix
            }

            // Check if the call initiation was successful
            if ($uuid && strpos($uuid, '-ERR') === false) {
                $response = [
                    'status' => '200',
                    'message' => "Call initiated successfully.",
                    'uuid' => $uuid,
                    'destination' => $destination
                ];
                logRequest("API Success: Call initiated, UUID=$uuid");
                echo json_encode($response);
            } else {
                $response = [
                    'status' => '486',
                    'message' => "Failed to initiate the call.",
                    'uuid' => $uuid
                ];
                logRequest("API Error: Call failed - $uuid");
                echo json_encode($response);
            }

            // Disconnect from FreeSWITCH
            $freeswitch->disconnect();
        } else {
            logRequest("API Error: ESL connection failed");
            $response = [
                'status' => '500',
                'message' => "Failed to connect to ESL."
            ];
            echo json_encode($response);
            http_response_code(500);
        }
    }

    // Call the function to initiate the call with values from the JSON body
    AutoCall($campaign_id, $callee, $destination, $timeout, $company_name, $customer_id);
} else {
    logRequest("API Error: Missing required parameters");
    echo json_encode([
        'status' => '400',
        'message' => 'Required parameters are missing: campaign_id, callee, and timeout are mandatory.'
    ]);
    http_response_code(400); // Set the response code to 400 Bad Request
}
?>
