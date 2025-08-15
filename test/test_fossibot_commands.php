<?php

/**
 * ABOUTME: Haupttest-Skript für Fossibot-Kommandos
 * ABOUTME: Testet alle FBT_*-Funktionen mit Event-basierter Validierung
 */

// Helper-Bibliothek einbinden
require_once(__DIR__ . '/event_test_helper.php');

class FossibotCommandTester 
{
    private $fossibotID;
    private $originalValues = [];
    private $testResults = [];
    
    public function __construct(int $fossibotID)
    {
        $this->fossibotID = $fossibotID;
        FossibotEventTestHelper::initLogging();
        FossibotEventTestHelper::log("=== FOSSIBOT COMMAND TESTER GESTARTET ===");
        FossibotEventTestHelper::log("Instance ID: $fossibotID");
    }
    
    /**
     * Startet alle Tests
     */
    public function runAllTests(): array
    {
        try {
            // 1. Originalzustand erfassen
            $this->captureOriginalState();
            
            // 2. Output-Commands testen (schnelle Response erwartet)
            $this->testOutputCommands();
            
            // 3. Value-Settings testen (langsamere Response erwartet)
            $this->testValueSettings();
            
            // 4. Status-Functions testen
            $this->testStatusFunctions();
            
            // 5. Originalzustand wiederherstellen
            $this->restoreOriginalState();
            
            // 6. Test-Report generieren
            $this->generateTestReport();
            
        } catch (Exception $e) {
            FossibotEventTestHelper::log("KRITISCHER FEHLER: " . $e->getMessage());
            $this->testResults['critical_error'] = $e->getMessage();
        }
        
        return $this->testResults;
    }
    
    /**
     * Erfasst aktuellen Zustand aller relevanten Variablen
     */
    private function captureOriginalState(): void
    {
        FossibotEventTestHelper::log("=== ORIGINALZUSTAND ERFASSEN ===");
        
        $variables = [
            'ACOutput', 'DCOutput', 'USBOutput',
            'MaxChargingCurrent', 'ChargingLimit', 'DischargeLimit',
            'BatterySOC', 'TotalInput', 'TotalOutput'
        ];
        
        foreach ($variables as $varIdent) {
            try {
                $varID = IPS_GetObjectIDByIdent($varIdent, $this->fossibotID);
                $value = GetValue($varID);
                $this->originalValues[$varIdent] = $value;
                FossibotEventTestHelper::log("Original $varIdent: $value");
            } catch (Exception $e) {
                FossibotEventTestHelper::log("Warnung: Variable '$varIdent' nicht gefunden: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Testet Output-Commands (AC/DC/USB)
     */
    private function testOutputCommands(): void
    {
        FossibotEventTestHelper::log("=== OUTPUT-COMMANDS TESTEN ===");
        
        // AC Output Tests
        $this->testSingleCommand(
            'ACOutput', 
            function() { return FBT_SetACOutput($this->fossibotID, true); },
            'FBT_SetACOutput(true)',
            10 // Kurzer Timeout für Output-Commands
        );
        
        $this->testSingleCommand(
            'ACOutput', 
            function() { return FBT_SetACOutput($this->fossibotID, false); },
            'FBT_SetACOutput(false)',
            10
        );
        
        // DC Output Tests
        $this->testSingleCommand(
            'DCOutput', 
            function() { return FBT_SetDCOutput($this->fossibotID, true); },
            'FBT_SetDCOutput(true)',
            10
        );
        
        $this->testSingleCommand(
            'DCOutput', 
            function() { return FBT_SetDCOutput($this->fossibotID, false); },
            'FBT_SetDCOutput(false)',
            10
        );
        
        // USB Output Tests
        $this->testSingleCommand(
            'USBOutput', 
            function() { return FBT_SetUSBOutput($this->fossibotID, true); },
            'FBT_SetUSBOutput(true)',
            10
        );
        
        $this->testSingleCommand(
            'USBOutput', 
            function() { return FBT_SetUSBOutput($this->fossibotID, false); },
            'FBT_SetUSBOutput(false)',
            10
        );
    }
    
    /**
     * Testet Value-Settings (Ladestrom, Limits)
     */
    private function testValueSettings(): void
    {
        FossibotEventTestHelper::log("=== VALUE-SETTINGS TESTEN ===");
        
        // Ladestrom Tests
        $this->testSingleCommand(
            'MaxChargingCurrent', 
            function() { return FBT_SetMaxChargingCurrent($this->fossibotID, 3); },
            'FBT_SetMaxChargingCurrent(3)',
            60 // Längerer Timeout für Value-Settings
        );
        
        $this->testSingleCommand(
            'MaxChargingCurrent', 
            function() { return FBT_SetMaxChargingCurrent($this->fossibotID, 1); },
            'FBT_SetMaxChargingCurrent(1)',
            60
        );
        
        // Ladelimit Tests
        $this->testSingleCommand(
            'ChargingLimit', 
            function() { return FBT_SetChargingLimit($this->fossibotID, 85); },
            'FBT_SetChargingLimit(85)',
            60
        );
        
        $this->testSingleCommand(
            'ChargingLimit', 
            function() { return FBT_SetChargingLimit($this->fossibotID, 80); },
            'FBT_SetChargingLimit(80)',
            60
        );
        
        // Entladelimit Tests
        $this->testSingleCommand(
            'DischargeLimit', 
            function() { return FBT_SetDischargeLimit($this->fossibotID, 25); },
            'FBT_SetDischargeLimit(25)',
            60
        );
        
        $this->testSingleCommand(
            'DischargeLimit', 
            function() { return FBT_SetDischargeLimit($this->fossibotID, 20); },
            'FBT_SetDischargeLimit(20)',
            60
        );
    }
    
    /**
     * Testet Status-Functions
     */
    private function testStatusFunctions(): void
    {
        FossibotEventTestHelper::log("=== STATUS-FUNCTIONS TESTEN ===");
        
        // UpdateDeviceStatus - kann mehrere Variablen beeinflussen
        FossibotEventTestHelper::log("Test: FBT_UpdateDeviceStatus()");
        $result = FBT_UpdateDeviceStatus($this->fossibotID);
        $this->testResults['FBT_UpdateDeviceStatus'] = [
            'command_result' => $result,
            'success' => $result !== false
        ];
        FossibotEventTestHelper::log("FBT_UpdateDeviceStatus() Result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        // RequestSettings
        FossibotEventTestHelper::log("Test: FBT_RequestSettings()");
        $result = FBT_RequestSettings($this->fossibotID);
        $this->testResults['FBT_RequestSettings'] = [
            'command_result' => $result,
            'success' => $result !== false
        ];
        FossibotEventTestHelper::log("FBT_RequestSettings() Result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        // GetDeviceInfo
        FossibotEventTestHelper::log("Test: FBT_GetDeviceInfo()");
        $result = FBT_GetDeviceInfo($this->fossibotID);
        $this->testResults['FBT_GetDeviceInfo'] = [
            'command_result' => $result,
            'success' => !empty($result)
        ];
        FossibotEventTestHelper::log("FBT_GetDeviceInfo() Result Length: " . strlen($result ?? ''));
    }
    
    /**
     * Testet einzelnes Kommando mit Event-Validierung
     */
    private function testSingleCommand(string $variableIdent, callable $testFunction, string $commandName, int $timeout): void
    {
        FossibotEventTestHelper::log("--- TEST: $commandName ---");
        
        $result = FossibotEventTestHelper::testCommandWithEvent(
            $this->fossibotID,
            $variableIdent,
            $testFunction,
            $timeout
        );
        
        $this->testResults[$commandName] = $result;
        
        if ($result['success']) {
            FossibotEventTestHelper::log("✅ $commandName: SUCCESS");
        } else {
            FossibotEventTestHelper::log("❌ $commandName: FAILED - " . ($result['error'] ?? 'Unknown error'));
        }
        
        // Kurze Pause zwischen Tests
        sleep(2);
    }
    
    /**
     * Stellt Originalzustand wieder her
     */
    private function restoreOriginalState(): void
    {
        FossibotEventTestHelper::log("=== ORIGINALZUSTAND WIEDERHERSTELLEN ===");
        
        // Output-States wiederherstellen
        if (isset($this->originalValues['ACOutput'])) {
            FossibotEventTestHelper::log("Restore ACOutput: " . ($this->originalValues['ACOutput'] ? 'true' : 'false'));
            FBT_SetACOutput($this->fossibotID, (bool)$this->originalValues['ACOutput']);
            sleep(3);
        }
        
        if (isset($this->originalValues['DCOutput'])) {
            FossibotEventTestHelper::log("Restore DCOutput: " . ($this->originalValues['DCOutput'] ? 'true' : 'false'));
            FBT_SetDCOutput($this->fossibotID, (bool)$this->originalValues['DCOutput']);
            sleep(3);
        }
        
        if (isset($this->originalValues['USBOutput'])) {
            FossibotEventTestHelper::log("Restore USBOutput: " . ($this->originalValues['USBOutput'] ? 'true' : 'false'));
            FBT_SetUSBOutput($this->fossibotID, (bool)$this->originalValues['USBOutput']);
            sleep(3);
        }
        
        // Value-Settings wiederherstellen (mit längerer Wartezeit)
        if (isset($this->originalValues['MaxChargingCurrent'])) {
            FossibotEventTestHelper::log("Restore MaxChargingCurrent: " . $this->originalValues['MaxChargingCurrent']);
            FBT_SetMaxChargingCurrent($this->fossibotID, (int)$this->originalValues['MaxChargingCurrent']);
            sleep(10);
        }
        
        if (isset($this->originalValues['ChargingLimit'])) {
            FossibotEventTestHelper::log("Restore ChargingLimit: " . $this->originalValues['ChargingLimit']);
            FBT_SetChargingLimit($this->fossibotID, (int)$this->originalValues['ChargingLimit']);
            sleep(10);
        }
        
        if (isset($this->originalValues['DischargeLimit'])) {
            FossibotEventTestHelper::log("Restore DischargeLimit: " . $this->originalValues['DischargeLimit']);
            FBT_SetDischargeLimit($this->fossibotID, (int)$this->originalValues['DischargeLimit']);
            sleep(10);
        }
        
        FossibotEventTestHelper::log("Originalzustand wiederhergestellt");
    }
    
    /**
     * Generiert Test-Report
     */
    private function generateTestReport(): void
    {
        FossibotEventTestHelper::log("=== TEST-REPORT ===");
        
        $total = 0;
        $successful = 0;
        $timings = [];
        
        foreach ($this->testResults as $testName => $result) {
            if (isset($result['success'])) {
                $total++;
                if ($result['success']) {
                    $successful++;
                    
                    // Timing-Daten sammeln
                    if (isset($result['event_result']['duration_ms'])) {
                        $timings[$testName] = $result['event_result']['duration_ms'];
                    }
                }
            }
        }
        
        FossibotEventTestHelper::log("Tests insgesamt: $total");
        FossibotEventTestHelper::log("Tests erfolgreich: $successful");
        FossibotEventTestHelper::log("Erfolgsrate: " . round(($successful / max($total, 1)) * 100, 1) . "%");
        
        // Timing-Statistiken
        if (!empty($timings)) {
            $avgTiming = round(array_sum($timings) / count($timings), 1);
            $minTiming = min($timings);
            $maxTiming = max($timings);
            
            FossibotEventTestHelper::log("Timing-Statistiken:");
            FossibotEventTestHelper::log("  Durchschnitt: {$avgTiming}ms");
            FossibotEventTestHelper::log("  Minimum: {$minTiming}ms");
            FossibotEventTestHelper::log("  Maximum: {$maxTiming}ms");
            
            // Detaillierte Timings
            FossibotEventTestHelper::log("Detaillierte Timings:");
            foreach ($timings as $test => $timing) {
                FossibotEventTestHelper::log("  $test: {$timing}ms");
            }
        }
        
        FossibotEventTestHelper::log("=== TEST-REPORT ENDE ===");
    }
}

// ============================================================================
// SCRIPT AUSFÜHRUNG
// ============================================================================

// WICHTIG: Fossibot Instance ID hier eintragen!
$FOSSIBOT_INSTANCE_ID = 12894; // <<<< Tim's F2400 Instance

try {
    // Instance ID validieren
    if (!IPS_ObjectExists($FOSSIBOT_INSTANCE_ID)) {
        throw new Exception("Fossibot Instance ID $FOSSIBOT_INSTANCE_ID existiert nicht!");
    }
    
    // Prüfen ob es eine FossibotDevice-Instanz ist
    $instanceInfo = IPS_GetInstance($FOSSIBOT_INSTANCE_ID);
    if ($instanceInfo['ModuleInfo']['ModuleName'] !== 'FossibotDevice') {
        throw new Exception("Instance ID $FOSSIBOT_INSTANCE_ID ist keine FossibotDevice-Instanz!");
    }
    
    FossibotEventTestHelper::log("Instance validiert: " . IPS_GetName($FOSSIBOT_INSTANCE_ID));
    
    // Tester erstellen und ausführen
    $tester = new FossibotCommandTester($FOSSIBOT_INSTANCE_ID);
    $results = $tester->runAllTests();
    
    FossibotEventTestHelper::log("=== ALLE TESTS BEENDET ===");
    FossibotEventTestHelper::log("Log-Datei: /tmp/fossibot_test.log");
    FossibotEventTestHelper::log("SSH-Zugriff: ssh user@hostname 'tail -f /tmp/fossibot_test.log'");
    
} catch (Exception $e) {
    FossibotEventTestHelper::log("KRITISCHER FEHLER: " . $e->getMessage());
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo "Bitte FOSSIBOT_INSTANCE_ID in der Script-Datei korrekt setzen!\n";
}