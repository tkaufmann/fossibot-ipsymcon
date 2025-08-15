<?php
/**
 * ABOUTME: Mock functions for API-free testing of Fossibot modules
 * ABOUTME: Replaces real API calls and IP-Symcon functions with test stubs
 */

// =============================================================================
// IP-SYMCON MOCK FUNCTIONS (nur falls nicht bereits definiert)
// =============================================================================

if (!function_exists('IPS_LogMessage')) {
    function IPS_LogMessage($sender, $message) {
        echo "[LOG] $sender: $message\n";
    }
}

if (!function_exists('IPS_SetProperty')) {
    function IPS_SetProperty($instanceId, $property, $value) {
        echo "[PROP] Set $property = $value for instance $instanceId\n";
        return true;
    }
}

if (!function_exists('IPS_GetProperty')) {
    function IPS_GetProperty($instanceId, $property) {
        // Mock property values
        $mockProperties = [
            'DeviceID' => 'MOCK_DEVICE_123',
            'UpdateInterval' => 30,
            'Username' => 'test@example.com',
            'Password' => 'testpassword'
        ];
        return $mockProperties[$property] ?? null;
    }
}

if (!function_exists('RegisterVariable')) {
    function RegisterVariable($ident, $name, $profile, $position) {
        echo "[VAR] Register $ident: $name (profile: $profile, pos: $position)\n";
        return true;
    }
}

if (!function_exists('SetValue')) {
    function SetValue($ident, $value) {
        echo "[VAL] Set $ident = $value\n";
        return true;
    }
}

if (!function_exists('GetValue')) {
    function GetValue($ident) {
        // Mock values for testing
        $mockValues = [
            'BatterySOC' => 85,
            'TotalInput' => 250,
            'TotalOutput' => 150,
            'ACOutput' => true,
            'MaxChargingCurrent' => 5
        ];
        return $mockValues[$ident] ?? 0;
    }
}

// =============================================================================
// NETWORK MOCK FUNCTIONS
// =============================================================================

/**
 * Mock curl_exec to avoid real API calls
 */
function mock_curl_exec($ch) {
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    
    if (strpos($url, 'api.next.bspapp.com') !== false) {
        // Mock Sydpower API responses
        return json_encode([
            'data' => [
                'accessToken' => 'mock_access_token_12345',
                'token' => 'mock_jwt_token_67890',
                'access_token' => 'mock_mqtt_token_abcde',
                'rows' => [
                    [
                        'device_id' => 'MOCK:DEVICE:123',
                        'device_name' => 'Mock F2400',
                        'product_name' => 'Fossibot F2400'
                    ]
                ]
            ]
        ]);
    }
    
    return '{"error": "Mock: Unknown API endpoint"}';
}

/**
 * Mock stream_socket_client for WebSocket connections  
 */
function mock_stream_socket_client($remote_socket, &$errno, &$errstr, $timeout = null, $flags = STREAM_CLIENT_CONNECT, $context = null) {
    if (strpos($remote_socket, 'mqtt.sydpower.com') !== false) {
        // Return mock socket resource
        $errno = 0;
        $errstr = '';
        return fopen('php://temp', 'r+'); // Mock socket
    }
    
    $errno = 1;
    $errstr = 'Mock: Connection refused';
    return false;
}

// =============================================================================
// TEST UTILITIES
// =============================================================================

/**
 * Test runner that captures output
 */
function run_test($testName, $testFunction) {
    echo "\n=== Testing: $testName ===\n";
    ob_start();
    
    try {
        $result = $testFunction();
        $output = ob_get_clean();
        echo $output;
        echo "✅ Test passed: $testName\n";
        return $result;
    } catch (Exception $e) {
        $output = ob_get_clean();
        echo $output;
        echo "❌ Test failed: $testName - " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Check if function exists and has correct signature
 */
function check_method_signature($className, $methodName, $expectedParams = null) {
    if (!class_exists($className)) {
        throw new Exception("Class $className not found");
    }
    
    $reflection = new ReflectionClass($className);
    if (!$reflection->hasMethod($methodName)) {
        throw new Exception("Method $methodName not found in $className");
    }
    
    $method = $reflection->getMethod($methodName);
    $params = $method->getParameters();
    
    echo "Method {$className}::{$methodName}(";
    foreach ($params as $i => $param) {
        if ($i > 0) echo ', ';
        echo ($param->getType() ? $param->getType() . ' ' : '') . '$' . $param->getName();
        if ($param->isDefaultValueAvailable()) {
            echo ' = ' . var_export($param->getDefaultValue(), true);
        }
    }
    echo ")";
    
    if ($method->getReturnType()) {
        echo ': ' . $method->getReturnType();
    }
    echo "\n";
    
    return true;
}

echo "Mock functions loaded successfully!\n";