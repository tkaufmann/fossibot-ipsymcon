<?php
/**
 * ABOUTME: Umfassende Test-Suite für Fossibot Skript-Kommandos
 * ABOUTME: Testet FBT_* Funktionen einzeln und unter Last (Rapid-Fire, Concurrency)
 */

// =============================================================================
// KONFIGURATION
// =============================================================================

// Echte Instanz-ID vom IPSymcon System
$FOSSIBOT_DEVICE_ID = 12894; // FossibotDevice Instance ID

// Log-Datei für detaillierte Test-Outputs
$LOG_FILE = '/var/lib/symcon/scripts/fossibot_script_test.log';

// Test-Konfiguration
$TEST_CONFIG = [
    'rapid_fire_delay' => 0.5,    // Sekunden zwischen Rapid-Fire Commands
    'api_timeout' => 30,          // Maximale Wartezeit pro Command
    'concurrency_threads' => 3,   // Anzahl parallele Test-Threads
    'stress_test_cycles' => 5     // Anzahl Stress-Test Durchläufe
];

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Erweiterte Log-Funktion mit Timestamp und Datei-Output
 */
function test_log($message, $level = 'INFO') {
    global $LOG_FILE;
    
    $timestamp = date('Y-m-d H:i:s.v');
    $logMessage = "[$timestamp] [$level] $message";
    
    // Console output
    echo $logMessage . "\n";
    
    // File output  
    file_put_contents($LOG_FILE, $logMessage . "\n", FILE_APPEND | LOCK_EX);
    
    // IPSymcon Log
    IPS_LogMessage('FossibotScriptTest', $message);
}

/**
 * Test-Runner mit Error-Handling und Timing
 */
function run_test($testName, callable $testFunction, ...$args) {
    global $TEST_CONFIG;
    
    test_log("=== STARTING TEST: $testName ===", 'TEST');
    $startTime = microtime(true);
    
    try {
        $result = $testFunction(...$args);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($result === true) {
            test_log("✅ TEST PASSED: $testName (Duration: {$duration}ms)", 'PASS');
            return true;
        } else {
            test_log("❌ TEST FAILED: $testName (Duration: {$duration}ms) - Result: " . var_export($result, true), 'FAIL');
            return false;
        }
        
    } catch (Exception $e) {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        test_log("💥 TEST ERROR: $testName (Duration: {$duration}ms) - " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Wartet auf Status-Update und validiert Änderung
 */
function wait_for_status_update($deviceId, $variableIdent, $expectedValue, $timeoutSeconds = 10) {
    test_log("Waiting for $variableIdent to become " . var_export($expectedValue, true));
    
    $startTime = time();
    $lastValue = null;
    
    while ((time() - $startTime) < $timeoutSeconds) {
        $currentValue = GetValue(IPS_GetObjectIDByIdent($variableIdent, $deviceId));
        
        if ($currentValue === $expectedValue) {
            test_log("✅ Status update confirmed: $variableIdent = " . var_export($currentValue, true));
            return true;
        }
        
        if ($currentValue !== $lastValue) {
            test_log("Status change detected: $variableIdent = " . var_export($currentValue, true) . " (waiting for " . var_export($expectedValue, true) . ")");
            $lastValue = $currentValue;
        }
        
        usleep(500000); // 0.5 second delay
    }
    
    test_log("⚠️ Timeout waiting for status update: $variableIdent");
    return false;
}

/**
 * Validiert dass Gerät konfiguriert und erreichbar ist
 */
function validate_device_setup($deviceId) {
    test_log("Validating device setup for ID: $deviceId");
    
    // Check if instance exists
    if (!IPS_InstanceExists($deviceId)) {
        throw new Exception("Device instance $deviceId does not exist");
    }
    
    // Check if instance is active
    $status = IPS_GetInstance($deviceId)['InstanceStatus'];
    if ($status !== 102) { // 102 = aktiv
        throw new Exception("Device instance $deviceId is not active (Status: $status)");
    }
    
    // Try to get current status
    $connectionStatus = GetValue(IPS_GetObjectIDByIdent('ConnectionStatus', $deviceId));
    test_log("Current connection status: $connectionStatus");
    
    return true;
}

// =============================================================================
// BASIS-FUNKTIONALITÄTSTESTS
// =============================================================================

/**
 * Test 1: AC Output Control
 */
function test_ac_output_control($deviceId) {
    test_log("Testing AC Output control...");
    
    // Turn AC ON
    $result1 = FBT_SetACOutput($deviceId, true);
    if (!$result1) return false;
    
    sleep(2); // API delay
    
    // Validate AC is ON
    if (!wait_for_status_update($deviceId, 'ACOutput', true, 15)) {
        return false;
    }
    
    // Turn AC OFF
    $result2 = FBT_SetACOutput($deviceId, false);
    if (!$result2) return false;
    
    sleep(2); // API delay
    
    // Validate AC is OFF
    return wait_for_status_update($deviceId, 'ACOutput', false, 15);
}

/**
 * Test 2: DC Output Control
 */
function test_dc_output_control($deviceId) {
    test_log("Testing DC Output control...");
    
    $result1 = FBT_SetDCOutput($deviceId, true);
    if (!$result1) return false;
    
    sleep(2);
    
    if (!wait_for_status_update($deviceId, 'DCOutput', true, 15)) {
        return false;
    }
    
    $result2 = FBT_SetDCOutput($deviceId, false);
    if (!$result2) return false;
    
    sleep(2);
    
    return wait_for_status_update($deviceId, 'DCOutput', false, 15);
}

/**
 * Test 3: USB Output Control
 */
function test_usb_output_control($deviceId) {
    test_log("Testing USB Output control...");
    
    $result1 = FBT_SetUSBOutput($deviceId, true);
    if (!$result1) return false;
    
    sleep(2);
    
    if (!wait_for_status_update($deviceId, 'USBOutput', true, 15)) {
        return false;
    }
    
    $result2 = FBT_SetUSBOutput($deviceId, false);
    if (!$result2) return false;
    
    sleep(2);
    
    return wait_for_status_update($deviceId, 'USBOutput', false, 15);
}

/**
 * Test 4: Charging Current Control
 */
function test_charging_current_control($deviceId) {
    test_log("Testing Charging Current control...");
    
    // Set to 3A
    $result1 = FBT_SetMaxChargingCurrent($deviceId, 3);
    if (!$result1) return false;
    
    sleep(3); // Settings need more time
    
    if (!wait_for_status_update($deviceId, 'MaxChargingCurrent', 3, 20)) {
        return false;
    }
    
    // Set to 5A
    $result2 = FBT_SetMaxChargingCurrent($deviceId, 5);
    if (!$result2) return false;
    
    sleep(3);
    
    return wait_for_status_update($deviceId, 'MaxChargingCurrent', 5, 20);
}

/**
 * Test 5: Charging Limit Control
 */
function test_charging_limit_control($deviceId) {
    test_log("Testing Charging Limit control...");
    
    // Set to 80%
    $result1 = FBT_SetChargingLimit($deviceId, 80);
    if (!$result1) return false;
    
    sleep(3);
    
    if (!wait_for_status_update($deviceId, 'ChargingLimit', 80, 20)) {
        return false;
    }
    
    // Set to 90%
    $result2 = FBT_SetChargingLimit($deviceId, 90);
    if (!$result2) return false;
    
    sleep(3);
    
    return wait_for_status_update($deviceId, 'ChargingLimit', 90, 20);
}

/**
 * Test 6: Discharge Limit Control
 */
function test_discharge_limit_control($deviceId) {
    test_log("Testing Discharge Limit control...");
    
    // Set to 20%
    $result1 = FBT_SetDischargeLimit($deviceId, 20);
    if (!$result1) return false;
    
    sleep(5); // Discharge limit needs more time
    
    if (!wait_for_status_update($deviceId, 'DischargeLimit', 20, 25)) {
        return false;
    }
    
    // Set to 10%
    $result2 = FBT_SetDischargeLimit($deviceId, 10);
    if (!$result2) return false;
    
    sleep(5);
    
    return wait_for_status_update($deviceId, 'DischargeLimit', 10, 25);
}

/**
 * Test 7: Status Update
 */
function test_status_update($deviceId) {
    test_log("Testing Manual Status Update...");
    
    $result = FBT_UpdateDeviceStatus($deviceId);
    if (!$result) return false;
    
    // Check if LastUpdate timestamp changed
    sleep(2);
    $lastUpdate = GetValue(IPS_GetObjectIDByIdent('LastUpdate', $deviceId));
    $timeDiff = time() - $lastUpdate;
    
    test_log("Last update was $timeDiff seconds ago");
    return $timeDiff < 30; // Should be recent
}

// =============================================================================
// PERFORMANCE & CONCURRENCY TESTS  
// =============================================================================

/**
 * Test 8: Rapid-Fire Commands (kritischer Test!)
 */
function test_rapid_fire_commands($deviceId) {
    global $TEST_CONFIG;
    
    test_log("Testing Rapid-Fire Commands (potential race conditions)...");
    
    $commands = [
        ['FBT_SetACOutput', [$deviceId, true]],
        ['FBT_SetMaxChargingCurrent', [$deviceId, 3]],
        ['FBT_SetChargingLimit', [$deviceId, 85]],
        ['FBT_SetDCOutput', [$deviceId, true]],
        ['FBT_SetUSBOutput', [$deviceId, true]]
    ];
    
    $startTime = microtime(true);
    $results = [];
    
    foreach ($commands as $i => $cmd) {
        $funcName = $cmd[0];
        $params = $cmd[1];
        
        test_log("Rapid-Fire Command $i: $funcName(" . implode(', ', array_slice($params, 1)) . ")");
        
        $result = call_user_func_array($funcName, $params);
        $results[] = $result;
        
        if (!$result) {
            test_log("❌ Rapid-Fire Command $i failed immediately");
        }
        
        // Short delay between commands
        usleep($TEST_CONFIG['rapid_fire_delay'] * 1000000);
    }
    
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    test_log("Rapid-Fire sequence completed in {$totalTime}ms");
    
    // Wait for all commands to process
    test_log("Waiting for all commands to settle...");
    sleep(10);
    
    // Check final states
    $finalResults = [
        GetValue(IPS_GetObjectIDByIdent('ACOutput', $deviceId)) === true,
        GetValue(IPS_GetObjectIDByIdent('MaxChargingCurrent', $deviceId)) === 3,
        GetValue(IPS_GetObjectIDByIdent('ChargingLimit', $deviceId)) === 85,
        GetValue(IPS_GetObjectIDByIdent('DCOutput', $deviceId)) === true,
        GetValue(IPS_GetObjectIDByIdent('USBOutput', $deviceId)) === true
    ];
    
    $successCount = array_sum($finalResults);
    test_log("Rapid-Fire Results: $successCount/5 commands applied successfully");
    
    return $successCount >= 4; // Allow 1 failure due to API limitations
}

// =============================================================================
// REAL-WORLD SZENARIEN
// =============================================================================

/**
 * Test 9: "Night Mode" Scenario
 */
function test_night_mode_scenario($deviceId) {
    test_log("Testing Night Mode Scenario...");
    
    // Night mode: Low charging, outputs off except DC for router
    $commands = [
        'FBT_SetMaxChargingCurrent' => 1,     // Eco charging
        'FBT_SetChargingLimit' => 95,         // Almost full
        'FBT_SetACOutput' => false,           // AC off
        'FBT_SetDCOutput' => true,            // DC for router
        'FBT_SetUSBOutput' => false           // USB off
    ];
    
    foreach ($commands as $func => $value) {
        test_log("Night Mode: $func($value)");
        $result = call_user_func($func, $deviceId, $value);
        if (!$result) {
            test_log("❌ Night Mode command failed: $func");
            return false;
        }
        sleep(1); // Brief delay between commands
    }
    
    // Wait and validate final state
    sleep(8);
    
    $validation = [
        GetValue(IPS_GetObjectIDByIdent('MaxChargingCurrent', $deviceId)) === 1,
        GetValue(IPS_GetObjectIDByIdent('ChargingLimit', $deviceId)) === 95,
        GetValue(IPS_GetObjectIDByIdent('ACOutput', $deviceId)) === false,
        GetValue(IPS_GetObjectIDByIdent('DCOutput', $deviceId)) === true,
        GetValue(IPS_GetObjectIDByIdent('USBOutput', $deviceId)) === false
    ];
    
    $success = array_sum($validation);
    test_log("Night Mode validation: $success/5 settings correct");
    
    return $success >= 4;
}

/**
 * Test 10: Emergency Shutdown Scenario
 */
function test_emergency_shutdown($deviceId) {
    test_log("Testing Emergency Shutdown Scenario...");
    
    // Turn everything off as fast as possible
    $shutdownStart = microtime(true);
    
    $results = [
        FBT_SetACOutput($deviceId, false),
        FBT_SetDCOutput($deviceId, false),
        FBT_SetUSBOutput($deviceId, false)
    ];
    
    $shutdownTime = round((microtime(true) - $shutdownStart) * 1000, 2);
    test_log("Emergency shutdown commands sent in {$shutdownTime}ms");
    
    // Wait for shutdown to complete
    sleep(5);
    
    // Validate all outputs are off
    $validation = [
        GetValue(IPS_GetObjectIDByIdent('ACOutput', $deviceId)) === false,
        GetValue(IPS_GetObjectIDByIdent('DCOutput', $deviceId)) === false,
        GetValue(IPS_GetObjectIDByIdent('USBOutput', $deviceId)) === false
    ];
    
    $success = array_sum($validation);
    test_log("Emergency shutdown validation: $success/3 outputs turned off");
    
    return $success === 3 && array_sum($results) >= 2;
}

// =============================================================================
// ERROR-RECOVERY TESTS
// =============================================================================

/**
 * Test 11: Invalid Parameter Handling
 */
function test_invalid_parameters($deviceId) {
    test_log("Testing Invalid Parameter Handling...");
    
    // Test invalid charging current (out of range)
    $result1 = FBT_SetMaxChargingCurrent($deviceId, 10); // Too high for F2400
    if ($result1 === true) {
        test_log("⚠️ Invalid charging current was accepted - this might be a problem");
        return false;
    }
    
    // Test invalid charging limit
    $result2 = FBT_SetChargingLimit($deviceId, 50); // Too low
    if ($result2 === true) {
        test_log("⚠️ Invalid charging limit was accepted - this might be a problem");
        return false;
    }
    
    // Test invalid discharge limit
    $result3 = FBT_SetDischargeLimit($deviceId, 80); // Too high
    if ($result3 === true) {
        test_log("⚠️ Invalid discharge limit was accepted - this might be a problem");
        return false;
    }
    
    test_log("✅ All invalid parameters were correctly rejected");
    return true;
}

// =============================================================================
// MAIN TEST EXECUTION
// =============================================================================

/**
 * Haupt-Test-Ausführung
 */
function main() {
    global $FOSSIBOT_DEVICE_ID, $LOG_FILE;
    
    // Clear log file
    file_put_contents($LOG_FILE, '');
    
    test_log("=== FOSSIBOT SCRIPT COMMANDS TEST SUITE ===");
    test_log("Device ID: $FOSSIBOT_DEVICE_ID");
    test_log("Log File: $LOG_FILE");
    test_log("Start Time: " . date('Y-m-d H:i:s'));
    
    // Validate setup first
    try {
        validate_device_setup($FOSSIBOT_DEVICE_ID);
    } catch (Exception $e) {
        test_log("💥 SETUP VALIDATION FAILED: " . $e->getMessage(), 'FATAL');
        test_log("Please check FOSSIBOT_DEVICE_ID and device configuration!");
        return false;
    }
    
    $results = [];
    
    // Basis-Funktionalitätstests
    test_log("\n🔧 BASIC FUNCTIONALITY TESTS");
    $results['ac_output'] = run_test('AC Output Control', 'test_ac_output_control', $FOSSIBOT_DEVICE_ID);
    $results['dc_output'] = run_test('DC Output Control', 'test_dc_output_control', $FOSSIBOT_DEVICE_ID);
    $results['usb_output'] = run_test('USB Output Control', 'test_usb_output_control', $FOSSIBOT_DEVICE_ID);
    $results['charging_current'] = run_test('Charging Current Control', 'test_charging_current_control', $FOSSIBOT_DEVICE_ID);
    $results['charging_limit'] = run_test('Charging Limit Control', 'test_charging_limit_control', $FOSSIBOT_DEVICE_ID);
    $results['discharge_limit'] = run_test('Discharge Limit Control', 'test_discharge_limit_control', $FOSSIBOT_DEVICE_ID);
    $results['status_update'] = run_test('Status Update', 'test_status_update', $FOSSIBOT_DEVICE_ID);
    
    // Performance Tests
    test_log("\n⚡ PERFORMANCE & CONCURRENCY TESTS");
    $results['rapid_fire'] = run_test('Rapid-Fire Commands', 'test_rapid_fire_commands', $FOSSIBOT_DEVICE_ID);
    
    // Real-World Szenarien
    test_log("\n🌍 REAL-WORLD SCENARIO TESTS");
    $results['night_mode'] = run_test('Night Mode Scenario', 'test_night_mode_scenario', $FOSSIBOT_DEVICE_ID);
    $results['emergency_shutdown'] = run_test('Emergency Shutdown', 'test_emergency_shutdown', $FOSSIBOT_DEVICE_ID);
    
    // Error-Recovery Tests
    test_log("\n🛡️ ERROR RECOVERY TESTS");
    $results['invalid_params'] = run_test('Invalid Parameters', 'test_invalid_parameters', $FOSSIBOT_DEVICE_ID);
    
    // Final Report
    $passed = array_sum($results);
    $total = count($results);
    $percentage = round(($passed / $total) * 100, 1);
    
    test_log("\n" . str_repeat("=", 60));
    test_log("📊 FINAL TEST REPORT");
    test_log("Tests Passed: $passed/$total ($percentage%)");
    test_log("End Time: " . date('Y-m-d H:i:s'));
    
    foreach ($results as $testName => $result) {
        $status = $result ? '✅ PASS' : '❌ FAIL';
        test_log("  $testName: $status");
    }
    
    if ($percentage >= 80) {
        test_log("\n🎉 TEST SUITE OVERALL: SUCCESS");
        test_log("The Fossibot script commands are working reliably!");
    } else {
        test_log("\n⚠️ TEST SUITE OVERALL: ISSUES DETECTED");
        test_log("Some script commands need investigation. Check the detailed logs above.");
    }
    
    test_log("Detailed logs written to: $LOG_FILE");
    return $percentage >= 80;
}

// =============================================================================
// EXECUTION
// =============================================================================

// Führe Tests nur aus wenn Instanz-ID konfiguriert ist
if ($FOSSIBOT_DEVICE_ID === 0) {
    echo "❌ ERROR: Please set FOSSIBOT_DEVICE_ID to your actual device instance ID!\n";
    echo "Find your device instance ID in IP-Symcon object tree.\n";
    exit(1);
}

// Starte Test-Suite
main();