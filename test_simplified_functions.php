<?php
// ABOUTME: Testet die vereinfachten FBT_* Funktionen ohne statusUpdate Parameter
// ABOUTME: Erwartet saubere Signaturen: FBT_FunctionName(instanceID, ...directParams)

echo "=== SIMPLIFIED FBT FUNCTION TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$deviceId = 12894; // FossibotDevice Instance ID

// Test 1: Status Update (bleibt gleich)
echo "--- Test 1: Status Update ---\n";
try {
    echo "Calling FBT_UpdateDeviceStatus($deviceId)...\n";
    $result = FBT_UpdateDeviceStatus($deviceId);
    echo "UpdateDeviceStatus: " . ($result ? "SUCCESS" : "FAILED") . "\n";
} catch (Exception $e) {
    echo "UpdateDeviceStatus ERROR: " . $e->getMessage() . "\n";
}

// Test 2: AC Output Control (VEREINFACHT - nur 2 Parameter)
echo "\n--- Test 2: AC Output Control (Simplified) ---\n";
try {
    echo "Calling FBT_SetACOutput($deviceId, true)...\n";
    $result1 = FBT_SetACOutput($deviceId, true);
    echo "AC ON: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(3);
    
    echo "Calling FBT_SetACOutput($deviceId, false)...\n";
    $result2 = FBT_SetACOutput($deviceId, false);
    echo "AC OFF: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "AC Output ERROR: " . $e->getMessage() . "\n";
}

// Test 3: DC und USB Output (VEREINFACHT)
echo "\n--- Test 3: DC and USB Output (Simplified) ---\n";
try {
    echo "Calling FBT_SetDCOutput($deviceId, true)...\n";
    $result1 = FBT_SetDCOutput($deviceId, true);
    echo "DC ON: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetUSBOutput($deviceId, true)...\n";
    $result2 = FBT_SetUSBOutput($deviceId, true);
    echo "USB ON: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetDCOutput($deviceId, false)...\n";
    $result3 = FBT_SetDCOutput($deviceId, false);
    echo "DC OFF: " . ($result3 ? "SUCCESS" : "FAILED") . "\n";
    
    echo "Calling FBT_SetUSBOutput($deviceId, false)...\n";
    $result4 = FBT_SetUSBOutput($deviceId, false);
    echo "USB OFF: " . ($result4 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "DC/USB Output ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Charging Settings (VEREINFACHT - nur 2 Parameter)
echo "\n--- Test 4: Charging Settings (Simplified) ---\n";
try {
    echo "Calling FBT_SetMaxChargingCurrent($deviceId, 3)...\n";
    $result1 = FBT_SetMaxChargingCurrent($deviceId, 3);
    echo "Set Charging Current 3A: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetChargingLimit($deviceId, 85)...\n";
    $result2 = FBT_SetChargingLimit($deviceId, 85);
    echo "Set Charging Limit 85%: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetDischargeLimit($deviceId, 15)...\n";
    $result3 = FBT_SetDischargeLimit($deviceId, 15);
    echo "Set Discharge Limit 15%: " . ($result3 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "Charging Settings ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Reflection Check (neue Signaturen prüfen)
echo "\n--- Test 5: Reflection Check (New Signatures) ---\n";
$testFunctions = [
    'FBT_SetACOutput',
    'FBT_SetDCOutput', 
    'FBT_SetMaxChargingCurrent',
    'FBT_UpdateDeviceStatus'
];

foreach ($testFunctions as $func) {
    if (function_exists($func)) {
        try {
            $reflection = new ReflectionFunction($func);
            echo "$func: " . $reflection->getNumberOfParameters() . " parameters\n";
        } catch (Exception $e) {
            echo "$func: Reflection failed\n";
        }
    } else {
        echo "$func: NOT FOUND\n";
    }
}

echo "\n=== SIMPLIFIED FUNCTIONS TEST COMPLETED ===\n";
echo "🎯 Expected: Cleaner API without redundant statusUpdate parameter\n";
echo "✅ Functions should now have minimal, logical parameter counts\n";
?>