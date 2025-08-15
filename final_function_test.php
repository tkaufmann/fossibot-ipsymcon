<?php
$deviceId = 12894;
$output = [];

$output[] = "=== FINAL FBT FUNCTION TEST ===";
$output[] = "Time: " . date('Y-m-d H:i:s');

// Test fbt_fbt_updatedevicestatus
if (function_exists('fbt_fbt_updatedevicestatus')) {
    $output[] = "✅ fbt_fbt_updatedevicestatus exists";
    try {
        $result = fbt_fbt_updatedevicestatus($deviceId);
        $output[] = "UpdateDeviceStatus result: " . ($result ? "SUCCESS" : "FAILED");
    } catch (Exception $e) {
        $output[] = "UpdateDeviceStatus error: " . $e->getMessage();
    }
} else {
    $output[] = "❌ fbt_fbt_updatedevicestatus NOT found";
}

// Test fbt_fbt_setacoutput
if (function_exists('fbt_fbt_setacoutput')) {
    $output[] = "✅ fbt_fbt_setacoutput exists";
    try {
        $result = fbt_fbt_setacoutput($deviceId, true);
        $output[] = "SetACOutput(true) result: " . ($result ? "SUCCESS" : "FAILED");
    } catch (Exception $e) {
        $output[] = "SetACOutput error: " . $e->getMessage();
    }
} else {
    $output[] = "❌ fbt_fbt_setacoutput NOT found";
}

$output[] = "=== END TEST ===";

$outputText = implode("\n", $output);
file_put_contents('/var/lib/symcon/final_test_result.txt', $outputText);
echo $outputText;
?>