<?php
// ABOUTME: Einfacher Test für lowercase fbt_fbt_* Funktionen

$deviceId = 12894;
$testFile = '/var/lib/symcon/simple_test_result.txt';

$result = "=== SIMPLE LOWERCASE TEST ===\n";
$result .= "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Status Update
if (function_exists('fbt_fbt_updatedevicestatus')) {
    $result .= "Function fbt_fbt_updatedevicestatus: EXISTS\n";
    try {
        $updateResult = fbt_fbt_updatedevicestatus($deviceId);
        $result .= "Update result: " . ($updateResult ? "SUCCESS" : "FAILED") . "\n";
    } catch (Exception $e) {
        $result .= "Update error: " . $e->getMessage() . "\n";
    }
} else {
    $result .= "Function fbt_fbt_updatedevicestatus: NOT FOUND\n";
}

// Test 2: AC Output
if (function_exists('fbt_fbt_setacoutput')) {
    $result .= "\nFunction fbt_fbt_setacoutput: EXISTS\n";
    try {
        $acResult = fbt_fbt_setacoutput($deviceId, true);
        $result .= "AC ON result: " . ($acResult ? "SUCCESS" : "FAILED") . "\n";
    } catch (Exception $e) {
        $result .= "AC error: " . $e->getMessage() . "\n";
    }
} else {
    $result .= "\nFunction fbt_fbt_setacoutput: NOT FOUND\n";
}

$result .= "\n=== TEST COMPLETE ===\n";

file_put_contents($testFile, $result);
echo "Test completed. Results in $testFile";
?>