<?php
// EINFACHER FOSSIBOT TEST
echo "=== FOSSIBOT SCRIPT COMMAND TEST ===\n";
echo "Start Time: " . date('Y-m-d H:i:s') . "\n";

$deviceId = 12894; // FossibotDevice Instance ID

// Test 1: Prüfe ob Instanz existiert
echo "\n--- Test 1: Instance Check ---\n";
if (IPS_InstanceExists($deviceId)) {
    echo "✅ Device instance $deviceId exists\n";
    $status = IPS_GetInstance($deviceId)['InstanceStatus'];
    echo "Status: $status (102=active)\n";
} else {
    echo "❌ Device instance $deviceId does not exist\n";
    exit(1);
}

// Test 2: Prüfe Connection Status
echo "\n--- Test 2: Connection Status ---\n";
try {
    $connectionStatusId = IPS_GetObjectIDByIdent('ConnectionStatus', $deviceId);
    $connectionStatus = GetValue($connectionStatusId);
    echo "Connection Status: $connectionStatus\n";
} catch (Exception $e) {
    echo "❌ Error getting connection status: " . $e->getMessage() . "\n";
}

// Test 3: Status Update
echo "\n--- Test 3: Status Update ---\n";
try {
    $result = FBT_UpdateDeviceStatus($deviceId);
    echo "Status Update Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
} catch (Exception $e) {
    echo "❌ Error in status update: " . $e->getMessage() . "\n";
}

// Test 4: Einfacher AC Output Test
echo "\n--- Test 4: AC Output Test ---\n";
try {
    echo "Current AC Status: ";
    $acOutputId = IPS_GetObjectIDByIdent('ACOutput', $deviceId);
    $currentAC = GetValue($acOutputId);
    echo ($currentAC ? "ON" : "OFF") . "\n";
    
    echo "Testing AC Output toggle...\n";
    $toggleResult = FBT_SetACOutput($deviceId, !$currentAC);
    echo "Toggle Result: " . ($toggleResult ? "SUCCESS" : "FAILED") . "\n";
    
    // Warte kurz und prüfe Status
    sleep(3);
    $newAC = GetValue($acOutputId);
    echo "New AC Status: " . ($newAC ? "ON" : "OFF") . "\n";
    
    // Zurück zum ursprünglichen Status
    $restoreResult = FBT_SetACOutput($deviceId, $currentAC);
    echo "Restore Result: " . ($restoreResult ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "❌ Error in AC output test: " . $e->getMessage() . "\n";
}

// Test 5: Charging Current Test
echo "\n--- Test 5: Charging Current Test ---\n";
try {
    $chargingCurrentId = IPS_GetObjectIDByIdent('MaxChargingCurrent', $deviceId);
    $currentChargingCurrent = GetValue($chargingCurrentId);
    echo "Current Charging Current: {$currentChargingCurrent}A\n";
    
    $newCurrent = ($currentChargingCurrent == 3) ? 5 : 3;
    echo "Setting charging current to {$newCurrent}A...\n";
    
    $result = FBT_SetMaxChargingCurrent($deviceId, $newCurrent);
    echo "Set Current Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    
    // Warte auf Update
    sleep(5);
    $updatedCurrent = GetValue($chargingCurrentId);
    echo "Updated Charging Current: {$updatedCurrent}A\n";
    
} catch (Exception $e) {
    echo "❌ Error in charging current test: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETED ===\n";
echo "End Time: " . date('Y-m-d H:i:s') . "\n";