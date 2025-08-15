<?php
// ABOUTME: Ermittelt die korrekte Signatur der generierten FBT_* Funktionen
// ABOUTME: Testet verschiedene Parameter-Kombinationen

echo "=== FBT FUNCTION SIGNATURE TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$deviceId = 12894;

// Test 1: Reflection Analysis
echo "--- Test 1: Reflection Analysis ---\n";
if (function_exists('FBT_SetACOutput')) {
    try {
        $reflection = new ReflectionFunction('FBT_SetACOutput');
        echo "FBT_SetACOutput signature:\n";
        echo "Parameter count: " . $reflection->getNumberOfParameters() . "\n";
        echo "Required parameters: " . $reflection->getNumberOfRequiredParameters() . "\n";
        
        foreach ($reflection->getParameters() as $i => $param) {
            echo "  Param $i: " . $param->getName();
            if ($param->hasType()) {
                echo " (type: " . $param->getType() . ")";
            }
            if ($param->isOptional()) {
                echo " [optional]";
                if ($param->isDefaultValueAvailable()) {
                    echo " = " . var_export($param->getDefaultValue(), true);
                }
            }
            echo "\n";
        }
    } catch (Exception $e) {
        echo "Reflection failed: " . $e->getMessage() . "\n";
    }
}

// Test 2: Parameter Testing für SetACOutput
echo "\n--- Test 2: SetACOutput Parameter Testing ---\n";

// Versuch 1: Mit 3 Parametern (null als ersten)
try {
    echo "Trying FBT_SetACOutput(null, $deviceId, true)...\n";
    $result = FBT_SetACOutput(null, $deviceId, true);
    echo "3 params (null, deviceId, value): SUCCESS - Result: " . ($result ? "true" : "false") . "\n";
} catch (Exception $e) {
    echo "3 params (null, deviceId, value): FAILED - " . $e->getMessage() . "\n";
}

// Versuch 2: Mit 3 Parametern (0 als ersten)
try {
    echo "Trying FBT_SetACOutput(0, $deviceId, true)...\n";
    $result = FBT_SetACOutput(0, $deviceId, true);
    echo "3 params (0, deviceId, value): SUCCESS - Result: " . ($result ? "true" : "false") . "\n";
} catch (Exception $e) {
    echo "3 params (0, deviceId, value): FAILED - " . $e->getMessage() . "\n";
}

// Versuch 3: Mit 3 Parametern (deviceId als ersten und zweiten)
try {
    echo "Trying FBT_SetACOutput($deviceId, $deviceId, true)...\n";
    $result = FBT_SetACOutput($deviceId, $deviceId, true);
    echo "3 params (deviceId, deviceId, value): SUCCESS - Result: " . ($result ? "true" : "false") . "\n";
} catch (Exception $e) {
    echo "3 params (deviceId, deviceId, value): FAILED - " . $e->getMessage() . "\n";
}

// Test 3: Parameter Testing für UpdateDeviceStatus
echo "\n--- Test 3: UpdateDeviceStatus Parameter Testing ---\n";

if (function_exists('FBT_UpdateDeviceStatus')) {
    try {
        $reflection = new ReflectionFunction('FBT_UpdateDeviceStatus');
        echo "FBT_UpdateDeviceStatus parameter count: " . $reflection->getNumberOfParameters() . "\n";
    } catch (Exception $e) {
        echo "Reflection failed: " . $e->getMessage() . "\n";
    }
    
    // Test verschiedene Parameter-Kombinationen
    try {
        echo "Trying FBT_UpdateDeviceStatus(null, $deviceId)...\n";
        $result = FBT_UpdateDeviceStatus(null, $deviceId);
        echo "2 params (null, deviceId): SUCCESS - Result: " . ($result ? "true" : "false") . "\n";
    } catch (Exception $e) {
        echo "2 params (null, deviceId): FAILED - " . $e->getMessage() . "\n";
    }
}

// Test 4: Andere Funktionen analysieren
echo "\n--- Test 4: Other Function Signatures ---\n";
$testFunctions = ['FBT_SetChargingLimit', 'FBT_GetDeviceInfo'];

foreach ($testFunctions as $func) {
    if (function_exists($func)) {
        try {
            $reflection = new ReflectionFunction($func);
            echo "$func: " . $reflection->getNumberOfParameters() . " parameters\n";
        } catch (Exception $e) {
            echo "$func: Reflection failed\n";
        }
    }
}

echo "\n=== TEST COMPLETED ===\n";
echo "Check reflection output to understand correct function signatures\n";
?>