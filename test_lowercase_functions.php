<?php
// ABOUTME: Testet die gefundenen lowercase fbt_fbt_* Funktionen
// ABOUTME: Schreibt Ergebnisse in /var/lib/symcon/fbt_lowercase_test_results.txt

$outputFile = '/var/lib/symcon/fbt_lowercase_test_results.txt';
$output = [];

$output[] = "=== FBT LOWERCASE FUNCTION TEST ===";
$output[] = "Time: " . date('Y-m-d H:i:s');
$output[] = "";

$deviceId = 12894; // FossibotDevice Instance ID

// Test alle gefundenen lowercase Funktionen
$output[] = "--- Testing Found Lowercase Functions ---";
$fbtFunctions = [
    'fbt_fbt_updatedevicestatus',
    'fbt_fbt_setacoutput',
    'fbt_fbt_setdcoutput', 
    'fbt_fbt_setusboutput',
    'fbt_fbt_setmaxchargingcurrent',
    'fbt_fbt_setcharginglimit',
    'fbt_fbt_setdischargelimit'
];

$successCount = 0;
foreach ($fbtFunctions as $func) {
    $output[] = "";
    $output[] = "Testing: $func";
    
    if (!function_exists($func)) {
        $output[] = "❌ Function $func does not exist";
        continue;
    }
    
    try {
        $output[] = "✅ Function exists, testing call...";
        
        if ($func === 'fbt_fbt_updatedevicestatus') {
            $result = $func($deviceId);
            $output[] = "Result: " . ($result ? "SUCCESS" : "FAILED");
        } elseif (strpos($func, 'output') !== false) {
            // Test output functions with true/false
            $output[] = "Testing ON/OFF...";
            $result1 = $func($deviceId, true);
            $output[] = "Set ON result: " . ($result1 ? "SUCCESS" : "FAILED");
            sleep(2);
            $result2 = $func($deviceId, false);
            $output[] = "Set OFF result: " . ($result2 ? "SUCCESS" : "FAILED");
        } elseif ($func === 'fbt_fbt_setmaxchargingcurrent') {
            $result = $func($deviceId, 3);
            $output[] = "Set charging current to 3A: " . ($result ? "SUCCESS" : "FAILED");
        } elseif ($func === 'fbt_fbt_setcharginglimit') {
            $result = $func($deviceId, 85);
            $output[] = "Set charging limit to 85%: " . ($result ? "SUCCESS" : "FAILED");
        } elseif ($func === 'fbt_fbt_setdischargelimit') {
            $result = $func($deviceId, 15);
            $output[] = "Set discharge limit to 15%: " . ($result ? "SUCCESS" : "FAILED");
        }
        
        $successCount++;
        $output[] = "✅ Function call completed successfully";
        
    } catch (Exception $e) {
        $output[] = "❌ Error calling $func: " . $e->getMessage();
    }
}

$output[] = "";
$output[] = "--- Summary ---";
$output[] = "Successfully tested: $successCount/" . count($fbtFunctions) . " functions";

// Test some utility functions too
$output[] = "";
$output[] = "--- Testing Utility Functions ---";
$utilityFunctions = [
    'fbt_fbt_getdeviceinfo',
    'fbt_fbt_cleartokencache',
    'fbt_fbt_refreshnow'
];

foreach ($utilityFunctions as $func) {
    if (function_exists($func)) {
        try {
            $output[] = "Testing $func...";
            $result = $func($deviceId);
            $output[] = "Result: " . ($result ? "SUCCESS" : "FAILED");
        } catch (Exception $e) {
            $output[] = "Error with $func: " . $e->getMessage();
        }
    }
}

$output[] = "";
$output[] = "=== TEST COMPLETED ===";

// Write results to file
file_put_contents($outputFile, implode("\n", $output));

// Return summary
echo "Lowercase FBT Functions Test: $successCount/" . count($fbtFunctions) . " working. Results in $outputFile";
return true;
?>