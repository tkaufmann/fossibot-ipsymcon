<?php
// ABOUTME: Testet ob FBT_* Funktionen als globale PHP-Funktionen verfügbar sind
// ABOUTME: Schreibt Ergebnisse in /var/lib/symcon/fbt_test_results.txt

$outputFile = '/var/lib/symcon/fbt_test_results.txt';
$output = [];

$output[] = "=== FBT FUNCTION AVAILABILITY TEST ===";
$output[] = "Time: " . date('Y-m-d H:i:s');
$output[] = "";

$deviceId = 12894; // FossibotDevice Instance ID

// Test 1: function_exists Checks
$output[] = "--- Test 1: Function Existence Checks ---";
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
    $output[] = sprintf("%-25s: %s", $func, $exists ? "✅ AVAILABLE" : "❌ NOT AVAILABLE");
    if ($exists) $availableFunctions++;
}

$output[] = "";
$output[] = "Summary: $availableFunctions/" . count($fbtFunctions) . " functions available";

// Test 2: Try calling one function if available
if (function_exists('FBT_UpdateDeviceStatus')) {
    $output[] = "";
    $output[] = "--- Test 2: Function Call Test ---";
    try {
        $output[] = "Calling FBT_UpdateDeviceStatus($deviceId)...";
        $result = FBT_UpdateDeviceStatus($deviceId);
        $output[] = "Result: " . ($result ? "SUCCESS" : "FAILED");
    } catch (Exception $e) {
        $output[] = "Error: " . $e->getMessage();
    }
} else {
    $output[] = "";
    $output[] = "--- Test 2: SKIPPED - No functions available ---";
}

// Test 3: Alternative - Call via Instance Method
$output[] = "";
$output[] = "--- Test 3: Direct Instance Method Call ---";
try {
    $output[] = "Trying IPS_RequestAction($deviceId, 'UpdateDeviceStatus', true)...";
    $result = IPS_RequestAction($deviceId, 'UpdateDeviceStatus', true);
    $output[] = "RequestAction Result: " . ($result ? "SUCCESS" : "FAILED");
} catch (Exception $e) {
    $output[] = "RequestAction Error: " . $e->getMessage();
}

// Test 4: Check Module Class and Methods
$output[] = "";
$output[] = "--- Test 4: Module Class Information ---";
try {
    $instance = IPS_GetInstance($deviceId);
    $output[] = "Module ID: " . $instance['ModuleInfo']['ModuleID'];
    $output[] = "Instance Status: " . $instance['InstanceStatus'] . " (102=active)";
    
    // Try to get class methods if possible
    if (class_exists('FossibotDevice')) {
        $output[] = "FossibotDevice class: FOUND";
        $methods = get_class_methods('FossibotDevice');
        $fbtMethods = array_filter($methods, function($m) { return strpos($m, 'FBT_') === 0; });
        $output[] = "FBT_ methods in class: " . count($fbtMethods);
        foreach ($fbtMethods as $method) {
            $output[] = "  - $method";
        }
    } else {
        $output[] = "FossibotDevice class: NOT FOUND";
    }
    
} catch (Exception $e) {
    $output[] = "Module inspection error: " . $e->getMessage();
}

// Test 5: Check if functions are defined elsewhere
$output[] = "";
$output[] = "--- Test 5: Function Definition Search ---";
$allFunctions = get_defined_functions()['user'];
$fbtLikeFunctions = array_filter($allFunctions, function($f) { 
    return strpos($f, 'fbt_') === 0 || strpos($f, 'FBT_') === 0; 
});
$output[] = "Found " . count($fbtLikeFunctions) . " FBT-like functions:";
foreach ($fbtLikeFunctions as $func) {
    $output[] = "  - $func";
}

$output[] = "";
$output[] = "=== TEST COMPLETED ===";

// Write results to file
file_put_contents($outputFile, implode("\n", $output));

// Also return a summary for the API call
$summary = "FBT Functions Test: $availableFunctions/" . count($fbtFunctions) . " available. Results written to $outputFile";
echo $summary;
return true;
?>