<?php
/**
 * ABOUTME: Response Validator für Fossibot MQTT-Responses
 * Validiert Command-Responses und wartet intelligent auf benötigte Daten
 */

class FossibotResponseValidator {
    
    /**
     * Command-Erwartungen definieren was jeder Command als Response braucht
     */
    private static $commandExpectations = [
        'REGEnableACOutput' => [
            'fields' => ['acOutput', 'maximumChargingCurrent', 'totalOutput'],
            'validate' => function($response) {
                return isset($response['acOutput']) && $response['acOutput'] === true;
            },
            'timeout' => 2000
        ],
        'REGDisableACOutput' => [
            'fields' => ['acOutput', 'maximumChargingCurrent', 'totalOutput'],
            'validate' => function($response) {
                return isset($response['acOutput']) && $response['acOutput'] === false;
            },
            'timeout' => 2000
        ],
        'REGEnableDCOutput' => [
            'fields' => ['dcOutput', 'maximumChargingCurrent'],
            'validate' => function($response) {
                return isset($response['dcOutput']) && $response['dcOutput'] === true;
            },
            'timeout' => 2000
        ],
        'REGDisableDCOutput' => [
            'fields' => ['dcOutput', 'maximumChargingCurrent'],
            'validate' => function($response) {
                return isset($response['dcOutput']) && $response['dcOutput'] === false;
            },
            'timeout' => 2000
        ],
        'REGEnableUSBOutput' => [
            'fields' => ['usbOutput', 'maximumChargingCurrent'],
            'validate' => function($response) {
                return isset($response['usbOutput']) && $response['usbOutput'] === true;
            },
            'timeout' => 2000
        ],
        'REGDisableUSBOutput' => [
            'fields' => ['usbOutput', 'maximumChargingCurrent'],
            'validate' => function($response) {
                return isset($response['usbOutput']) && $response['usbOutput'] === false;
            },
            'timeout' => 2000
        ],
        'REGChargeUpperLimit' => [
            'fields' => ['acChargingUpperLimit', 'maximumChargingCurrent'],
            'validate' => function($response, $expectedValue) {
                if (!isset($response['acChargingUpperLimit'])) return false;
                $expectedPromille = $expectedValue * 10;
                return abs($response['acChargingUpperLimit'] - $expectedPromille) < 5;
            },
            'timeout' => 2500
        ],
        'REGDischargeLowerLimit' => [
            'fields' => ['dischargeLowerLimit', 'maximumChargingCurrent'],
            'validate' => function($response, $expectedValue) {
                if (!isset($response['dischargeLowerLimit'])) return false;
                $expectedPromille = $expectedValue * 10;
                return abs($response['dischargeLowerLimit'] - $expectedPromille) < 5;
            },
            'timeout' => 2500
        ],
        'REGMaxChargeCurrent' => [
            'fields' => ['maximumChargingCurrent'],
            'validate' => function($response, $expectedValue) {
                return isset($response['maximumChargingCurrent']) && 
                       $response['maximumChargingCurrent'] == $expectedValue;
            },
            'timeout' => 2000
        ],
        'REGRequestSettings' => [
            'fields' => ['soc', 'totalInput', 'totalOutput'],
            'validate' => function($response) {
                // Settings request ist erfolgreich wenn wir basics haben
                return isset($response['soc']);
            },
            'timeout' => 3000
        ]
    ];
    
    /**
     * Wartet intelligent auf eine valide Response
     */
    public static function waitForValidResponse($client, $deviceId, $command, $value = null) {
        $expectation = self::$commandExpectations[$command] ?? null;
        
        if (!$expectation) {
            // Unbekannter Command - generisches Waiting
            IPS_LogMessage("ResponseValidator", "Unknown command {$command}, using generic wait");
            return self::genericWait($client, $deviceId, 2000);
        }
        
        $startTime = microtime(true);
        $timeout = $expectation['timeout'] / 1000; // ms to seconds
        $lastStatus = null;
        $updateCount = 0;
        
        // Request Settings für vollständige Daten
        $client->requestDeviceSettings($deviceId);
        
        // Smart Polling mit Backoff
        $pollIntervals = [50, 100, 100, 200, 200, 500]; // ms
        $pollIndex = 0;
        
        IPS_LogMessage("ResponseValidator", "Waiting for {$command} response (timeout: {$expectation['timeout']}ms)");
        
        while ((microtime(true) - $startTime) < $timeout) {
            // Adaptive polling
            $pollTime = $pollIntervals[min($pollIndex++, count($pollIntervals)-1)] / 1000;
            
            // MQTT Loop für Messages
            if ($client->isMqttConnected()) {
                $client->listenForUpdates($pollTime);
            } else {
                usleep($pollTime * 1000000);
            }
            
            // Status holen
            $status = $client->getDeviceStatus($deviceId);
            
            // Hat sich was geändert?
            if ($status !== $lastStatus) {
                $updateCount++;
                $lastStatus = $status;
                
                // Debug welche Felder wir haben
                $hasFields = [];
                foreach ($expectation['fields'] as $field) {
                    if (isset($status[$field])) {
                        $hasFields[] = $field;
                    }
                }
                
                if (count($hasFields) > 0) {
                    IPS_LogMessage("ResponseValidator", "Update #{$updateCount}: Got fields: " . implode(', ', $hasFields));
                }
                
                // Validierung
                if (self::hasRequiredFields($status, $expectation['fields'])) {
                    $validator = $expectation['validate'];
                    if ($validator($status, $value)) {
                        $elapsed = round((microtime(true) - $startTime) * 1000);
                        IPS_LogMessage("ResponseValidator", "✅ Valid response for {$command} after {$elapsed}ms");
                        return [
                            'success' => true,
                            'data' => $status,
                            'time' => $elapsed,
                            'updates' => $updateCount
                        ];
                    } else {
                        IPS_LogMessage("ResponseValidator", "Fields present but validation failed");
                    }
                }
            }
        }
        
        // Timeout - aber vielleicht haben wir Teildaten?
        $elapsed = round((microtime(true) - $startTime) * 1000);
        $missing = self::getMissingFields($lastStatus, $expectation['fields']);
        
        IPS_LogMessage("ResponseValidator", "⚠️ Timeout for {$command} after {$elapsed}ms. Missing: " . implode(', ', $missing));
        
        return [
            'success' => false,
            'partial' => $lastStatus,
            'missing' => $missing,
            'timeout' => true,
            'time' => $elapsed,
            'updates' => $updateCount
        ];
    }
    
    /**
     * Generisches Warten für unbekannte Commands
     */
    private static function genericWait($client, $deviceId, $timeoutMs) {
        $startTime = microtime(true);
        $timeout = $timeoutMs / 1000;
        
        // Request Settings
        $client->requestDeviceSettings($deviceId);
        
        // Einfach warten und letzte Daten zurückgeben
        while ((microtime(true) - $startTime) < $timeout) {
            $client->listenForUpdates(0.1);
        }
        
        $status = $client->getDeviceStatus($deviceId);
        $elapsed = round((microtime(true) - $startTime) * 1000);
        
        return [
            'success' => !empty($status),
            'data' => $status,
            'time' => $elapsed
        ];
    }
    
    /**
     * Prüft ob alle benötigten Felder vorhanden sind
     */
    private static function hasRequiredFields($data, $fields) {
        if (!is_array($data)) return false;
        
        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Gibt fehlende Felder zurück
     */
    private static function getMissingFields($data, $requiredFields) {
        $missing = [];
        
        if (!is_array($data)) {
            return $requiredFields;
        }
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Prüft ob ein Command Settings betrifft
     */
    public static function isSettingsCommand($command) {
        $settingsCommands = [
            'REGChargeUpperLimit',
            'REGMaxChargeCurrent',
            'REGDischargeLowerLimit',
            'REGUSBStandbyTime',
            'REGACStandbyTime',
            'REGDCStandbyTime',
            'REGStopChargeAfter'
        ];
        
        return in_array($command, $settingsCommands);
    }
    
    /**
     * Prüft ob ein Command Outputs betrifft
     */
    public static function isOutputCommand($command) {
        return strpos($command, 'Output') !== false;
    }
}