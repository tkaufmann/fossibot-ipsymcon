<?php
// ABOUTME: Testet die gefundenen lowercase fbt_fbt_* Funktionen
// ABOUTME: Prüft ob IPSModuleStrict die Skript-Kommandos verfügbar gemacht hat

echo "=== FBT LOWERCASE FUNCTION TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$deviceId = 12894; // FossibotDevice Instance ID

// Test 1: Prüfe alle gefundenen lowercase Funktionen
echo "--- Test 1: Function Existence Check ---\n";
$fbtFunctions = [
    'fbt_fbt_updatedevicestatus',
    'fbt_fbt_setacoutput',
    'fbt_fbt_setdcoutput', 
    'fbt_fbt_setusboutput',
    'fbt_fbt_setmaxchargingcurrent',
    'fbt_fbt_setcharginglimit',
    'fbt_fbt_setdischargelimit'
];

$availableFunctions = 0;
foreach ($fbtFunctions as $func) {
    $exists = function_exists($func);
    echo sprintf("%-30s: %s\n", $func, $exists ? "✅ AVAILABLE" : "❌ NOT AVAILABLE");
    if ($exists) $availableFunctions++;
}

echo "\nSummary: $availableFunctions/" . count($fbtFunctions) . " lowercase functions available\n";

// Test 2: Status Update Test
echo "\n--- Test 2: Status Update Test ---\n";
if (function_exists('fbt_fbt_updatedevicestatus')) {
    echo "Testing fbt_fbt_updatedevicestatus($deviceId)...\n";
    try {
        $result = fbt_fbt_updatedevicestatus($deviceId);
        echo "Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "SKIPPED - Function not available\n";
}

// Test 3: AC Output Test 
echo "\n--- Test 3: AC Output Test ---\n";
if (function_exists('fbt_fbt_setacoutput')) {
    echo "Testing fbt_fbt_setacoutput($deviceId, true)...\n";
    try {
        $result = fbt_fbt_setacoutput($deviceId, true);
        echo "AC ON Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        
        sleep(2);
        
        echo "Testing fbt_fbt_setacoutput($deviceId, false)...\n";
        $result2 = fbt_fbt_setacoutput($deviceId, false);
        echo "AC OFF Result: " . ($result2 ? "SUCCESS" : "FAILED") . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "SKIPPED - Function not available\n";
}

// Test 4: Charging Current Test
echo "\n--- Test 4: Charging Current Test ---\n";
if (function_exists('fbt_fbt_setmaxchargingcurrent')) {
    echo "Testing fbt_fbt_setmaxchargingcurrent($deviceId, 3)...\n";
    try {
        $result = fbt_fbt_setmaxchargingcurrent($deviceId, 3);
        echo "Set 3A Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "SKIPPED - Function not available\n";
}

// Test 5: Utility Functions
echo "\n--- Test 5: Utility Functions ---\n";
$utilityFunctions = [
    'fbt_fbt_getdeviceinfo',
    'fbt_fbt_cleartokencache', 
    'fbt_fbt_refreshnow'
];

foreach ($utilityFunctions as $func) {
    if (function_exists($func)) {
        echo "Testing $func($deviceId)...\n";
        try {
            $result = $func($deviceId);
            echo "$func Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        } catch (Exception $e) {
            echo "$func Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "$func: NOT AVAILABLE\n";
    }
}

echo "\n=== TEST COMPLETED ===\n";

if ($availableFunctions > 0) {
    echo "🎉 ERFOLG: IPSModuleStrict hat $availableFunctions FBT-Funktionen verfügbar gemacht!\n";
    echo "Die Funktionen verwenden lowercase fbt_fbt_* Format statt FBT_*\n";
} else {
    echo "⚠️ PROBLEM: Keine FBT-Funktionen verfügbar gefunden\n";
}
?>