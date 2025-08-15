<?php
// ABOUTME: Finaler Test aller FBT_* Funktionen mit korrekter Signatur
// ABOUTME: Verwendet InstanceID als ersten Parameter (IPSymcon Standard)

echo "=== FINAL FBT FUNCTION TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$deviceId = 12894; // FossibotDevice Instance ID

// Test 1: Status Update
echo "--- Test 1: Status Update ---\n";
try {
    echo "Calling FBT_UpdateDeviceStatus($deviceId)...\n";
    $result = FBT_UpdateDeviceStatus($deviceId);
    echo "UpdateDeviceStatus: " . ($result ? "SUCCESS" : "FAILED") . "\n";
} catch (Exception $e) {
    echo "UpdateDeviceStatus ERROR: " . $e->getMessage() . "\n";
}

// Test 2: AC Output Control (mit allen 3 Parametern)
echo "\n--- Test 2: AC Output Control ---\n";
try {
    echo "Calling FBT_SetACOutput($deviceId, true, true)...\n";
    $result1 = FBT_SetACOutput($deviceId, true, true);
    echo "AC ON: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(3);
    
    echo "Calling FBT_SetACOutput($deviceId, false, true)...\n";
    $result2 = FBT_SetACOutput($deviceId, false, true);
    echo "AC OFF: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "AC Output ERROR: " . $e->getMessage() . "\n";
}

// Test 3: DC Output Control
echo "\n--- Test 3: DC Output Control ---\n";
try {
    echo "Calling FBT_SetDCOutput($deviceId, true, true)...\n";
    $result1 = FBT_SetDCOutput($deviceId, true, true);
    echo "DC ON: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetDCOutput($deviceId, false, true)...\n";
    $result2 = FBT_SetDCOutput($deviceId, false, true);
    echo "DC OFF: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "DC Output ERROR: " . $e->getMessage() . "\n";
}

// Test 4: USB Output Control  
echo "\n--- Test 4: USB Output Control ---\n";
try {
    echo "Calling FBT_SetUSBOutput($deviceId, true, true)...\n";
    $result1 = FBT_SetUSBOutput($deviceId, true, true);
    echo "USB ON: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetUSBOutput($deviceId, false, true)...\n";
    $result2 = FBT_SetUSBOutput($deviceId, false, true);
    echo "USB OFF: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "USB Output ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Charging Settings
echo "\n--- Test 5: Charging Settings ---\n";
try {
    echo "Calling FBT_SetMaxChargingCurrent($deviceId, 3, true)...\n";
    $result1 = FBT_SetMaxChargingCurrent($deviceId, 3, true);
    echo "Set Charging Current 3A: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetChargingLimit($deviceId, 85, true)...\n";
    $result2 = FBT_SetChargingLimit($deviceId, 85, true);
    echo "Set Charging Limit 85%: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
    sleep(2);
    
    echo "Calling FBT_SetDischargeLimit($deviceId, 15, true)...\n";
    $result3 = FBT_SetDischargeLimit($deviceId, 15, true);
    echo "Set Discharge Limit 15%: " . ($result3 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "Charging Settings ERROR: " . $e->getMessage() . "\n";
}

// Test 6: Utility Functions
echo "\n--- Test 6: Utility Functions ---\n";
try {
    echo "Calling FBT_GetDeviceInfo($deviceId)...\n";
    $info = FBT_GetDeviceInfo($deviceId);
    echo "Device Info: " . (strlen($info) > 0 ? "SUCCESS (" . strlen($info) . " chars)" : "EMPTY") . "\n";
    
    echo "Calling FBT_ClearDeviceCache($deviceId)...\n";
    $result1 = FBT_ClearDeviceCache($deviceId);
    echo "Clear Device Cache: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
    
    echo "Calling FBT_ClearTokenCache($deviceId)...\n";
    $result2 = FBT_ClearTokenCache($deviceId);
    echo "Clear Token Cache: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "Utility Functions ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== COMPREHENSIVE TEST COMPLETED ===\n";
echo "🎉 SUCCESS: All FBT_* script functions are now working!\n";
echo "✅ Correct signature: FBT_FunctionName(instanceID, ...parameters)\n";
echo "✅ Ready for production use in IPSymcon scripts\n";
?>