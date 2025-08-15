<?php
// ABOUTME: Testet ob FBT_* Funktionen als globale PHP-Funktionen verfügbar sind
// ABOUTME: Überprüft nach IPSModuleStrict Änderung die Funktion-Verfügbarkeit

echo "=== FBT FUNCTION AVAILABILITY TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$deviceId = 12894; // FossibotDevice Instance ID

// Test 1: function_exists Checks
echo "--- Test 1: Function Existence Checks ---\n";
$fbtFunctions = [
    'FBT_UpdateDeviceStatus',
    'FBT_SetACOutput', 
    'FBT_SetDCOutput',
    'FBT_SetUSBOutput',
    'FBT_SetMaxChargingCurrent',
    'FBT_SetChargingLimit',
    'FBT_SetDischargeLimit'
];

$availableFunctions = 0;
foreach ($fbtFunctions as $func) {
    $exists = function_exists($func);
    echo sprintf("%-25s: %s\n", $func, $exists ? "✅ AVAILABLE" : "❌ NOT AVAILABLE");
    if ($exists) $availableFunctions++;
}

echo "\nSummary: $availableFunctions/" . count($fbtFunctions) . " functions available\n";

// Test 2: Try calling one function if available
if (function_exists('FBT_UpdateDeviceStatus')) {
    echo "\n--- Test 2: Function Call Test ---\n";
    try {
        echo "Calling FBT_UpdateDeviceStatus($deviceId)...\n";
        $result = FBT_UpdateDeviceStatus($deviceId);
        echo "Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n--- Test 2: SKIPPED - No functions available ---\n";
}

// Test 3: Alternative - Call via Instance Method
echo "\n--- Test 3: Direct Instance Method Call ---\n";
try {
    echo "Trying IPS_RequestAction($deviceId, 'UpdateDeviceStatus', true)...\n";
    $result = IPS_RequestAction($deviceId, 'UpdateDeviceStatus', true);
    echo "RequestAction Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
} catch (Exception $e) {
    echo "RequestAction Error: " . $e->getMessage() . "\n";
}

// Test 4: Check Module Class and Methods
echo "\n--- Test 4: Module Class Information ---\n";
try {
    $instance = IPS_GetInstance($deviceId);
    echo "Module ID: " . $instance['ModuleInfo']['ModuleID'] . "\n";
    echo "Instance Status: " . $instance['InstanceStatus'] . " (102=active)\n";
    
    // Try to get class methods if possible
    if (class_exists('FossibotDevice')) {
        echo "FossibotDevice class: FOUND\n";
        $methods = get_class_methods('FossibotDevice');
        $fbtMethods = array_filter($methods, function($m) { return strpos($m, 'FBT_') === 0; });
        echo "FBT_ methods in class: " . count($fbtMethods) . "\n";
        foreach ($fbtMethods as $method) {
            echo "  - $method\n";
        }
    } else {
        echo "FossibotDevice class: NOT FOUND\n";
    }
    
} catch (Exception $e) {
    echo "Module inspection error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETED ===\n";