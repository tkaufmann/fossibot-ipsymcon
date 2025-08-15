<?php
// ABOUTME: Testet die korrigierten FBT_* Funktionen nach Präfix-Korrektur
// ABOUTME: Erwartet FBT_UpdateDeviceStatus statt FBT_FBT_UpdateDeviceStatus

echo "=== FBT CORRECTED FUNCTION TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$deviceId = 12894; // FossibotDevice Instance ID

// Test 1: Function Existence Check
echo "--- Test 1: Function Existence Check ---\n";
$expectedFunctions = [
    'FBT_UpdateDeviceStatus',
    'FBT_SetACOutput',
    'FBT_SetDCOutput', 
    'FBT_SetUSBOutput',
    'FBT_SetMaxChargingCurrent',
    'FBT_SetChargingLimit',
    'FBT_SetDischargeLimit',
    'FBT_SetUpdateInterval',
    'FBT_ClearDeviceCache',
    'FBT_GetDeviceInfo',
    'FBT_SetChargeTimer',
    'FBT_RequestSettings',
    'FBT_ClearTokenCache'
];

$availableFunctions = 0;
foreach ($expectedFunctions as $func) {
    $exists = function_exists($func);
    echo sprintf("%-25s: %s\n", $func, $exists ? "✅ AVAILABLE" : "❌ NOT AVAILABLE");
    if ($exists) $availableFunctions++;
}

echo "\nSummary: $availableFunctions/" . count($expectedFunctions) . " expected functions available\n";

// Test 2: Check for old double prefix functions
echo "\n--- Test 2: Double Prefix Check (should NOT exist) ---\n";
$oldFunctions = [
    'FBT_FBT_UpdateDeviceStatus',
    'FBT_FBT_SetACOutput'
];

$doublePrefix = 0;
foreach ($oldFunctions as $func) {
    $exists = function_exists($func);
    echo sprintf("%-30s: %s\n", $func, $exists ? "❌ STILL EXISTS (BAD)" : "✅ NOT FOUND (GOOD)");
    if ($exists) $doublePrefix++;
}

// Test 3: Function Call Test
if (function_exists('FBT_UpdateDeviceStatus')) {
    echo "\n--- Test 3: Function Call Test ---\n";
    try {
        echo "Calling FBT_UpdateDeviceStatus($deviceId)...\n";
        $result = FBT_UpdateDeviceStatus($deviceId);
        echo "Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n--- Test 3: SKIPPED - FBT_UpdateDeviceStatus not available ---\n";
}

// Test 4: Output Control Test
if (function_exists('FBT_SetACOutput')) {
    echo "\n--- Test 4: AC Output Control Test ---\n";
    try {
        echo "Testing FBT_SetACOutput($deviceId, true)...\n";
        $result1 = FBT_SetACOutput($deviceId, true);
        echo "AC ON: " . ($result1 ? "SUCCESS" : "FAILED") . "\n";
        
        sleep(2);
        
        echo "Testing FBT_SetACOutput($deviceId, false)...\n";
        $result2 = FBT_SetACOutput($deviceId, false);
        echo "AC OFF: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
        
    } catch (Exception $e) {
        echo "AC Output Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n--- Test 4: SKIPPED - FBT_SetACOutput not available ---\n";
}

echo "\n=== FINAL RESULTS ===\n";

if ($availableFunctions >= 10) {
    echo "🎉 ERFOLG: Präfix-Korrektur funktioniert!\n";
    echo "✅ $availableFunctions korrekte FBT_* Funktionen verfügbar\n";
    if ($doublePrefix == 0) {
        echo "✅ Keine doppelten Präfixe mehr gefunden\n";
    } else {
        echo "⚠️ $doublePrefix doppelte Präfixe noch vorhanden\n";
    }
} else {
    echo "❌ PROBLEM: Nur $availableFunctions Funktionen verfügbar\n";
    echo "Module eventuell noch nicht neu geladen?\n";
}

echo "\n=== TEST COMPLETED ===\n";
?>