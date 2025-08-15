<?php

/**
 * ABOUTME: Event-Helper-Bibliothek für Fossibot-Tests
 * ABOUTME: Verwaltet temporäre Events und wartet auf Variablen-Änderungen
 */

class FossibotEventTestHelper
{
    private static $activeEvents = [];
    private static $eventFlags = [];
    private static $logFile = '/tmp/fossibot_test.log';
    
    /**
     * Initialisiert das Logging-System
     */
    public static function initLogging(): void
    {
        // Log-Datei zurücksetzen
        file_put_contents(self::$logFile, "=== Fossibot Event-Test gestartet ===\n");
        self::log("Test-System initialisiert");
    }
    
    /**
     * Schreibt Log-Eintrag mit Timestamp
     */
    public static function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] $message\n";
        
        // In Datei schreiben (für SSH-Zugriff)
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Auch in IP-Symcon Messages ausgeben
        echo $logLine;
    }
    
    /**
     * Erstellt temporäres Event für Variable-Änderungen
     */
    public static function createTestEvent(int $fossibotID, string $variableIdent): int
    {
        try {
            // Variable-ID ermitteln
            $variableID = IPS_GetObjectIDByIdent($variableIdent, $fossibotID);
            if (!$variableID) {
                throw new Exception("Variable '$variableIdent' nicht gefunden in Instance $fossibotID");
            }
            
            // Event erstellen
            $eventID = IPS_CreateEvent(0); // Triggered Event
            IPS_SetParent($eventID, $fossibotID);
            IPS_SetIdent($eventID, 'TEST_EVENT_' . $variableIdent . '_' . time());
            IPS_SetName($eventID, "Test Event für $variableIdent");
            
            // Event-Trigger konfigurieren (bei Änderung der Variable)
            IPS_SetEventTrigger($eventID, 1, $variableID); // 1 = On Change
            
            // Event-Skript setzen (setzt Flag wenn Event feuert)
            $flagName = 'event_' . $variableIdent . '_' . $eventID;
            $eventScript = "FossibotEventTestHelper::\$eventFlags['$flagName'] = [
                'fired' => true, 
                'timestamp' => microtime(true),
                'value' => \$_IPS['VALUE'],
                'oldValue' => \$_IPS['OLDVALUE']
            ];";
            
            IPS_SetEventScript($eventID, $eventScript);
            IPS_SetEventActive($eventID, true);
            
            // Event registrieren für späteren Cleanup
            self::$activeEvents[] = $eventID;
            self::$eventFlags[$flagName] = ['fired' => false];
            
            self::log("Event erstellt: ID=$eventID für Variable '$variableIdent' (VarID=$variableID)");
            return $eventID;
            
        } catch (Exception $e) {
            self::log("FEHLER beim Event erstellen: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Wartet auf Event-Trigger mit Timeout
     */
    public static function waitForEvent(int $eventID, string $variableIdent, int $maxSeconds = 60): array
    {
        $flagName = 'event_' . $variableIdent . '_' . $eventID;
        $startTime = microtime(true);
        $endTime = $startTime + $maxSeconds;
        
        self::log("WARTE auf Event $eventID für '$variableIdent' (max. {$maxSeconds}s)");
        
        while (microtime(true) < $endTime) {
            // Event-Flag prüfen
            if (isset(self::$eventFlags[$flagName]) && self::$eventFlags[$flagName]['fired']) {
                $eventData = self::$eventFlags[$flagName];
                $duration = round(($eventData['timestamp'] - $startTime) * 1000, 1);
                
                self::log("EVENT GEFEUERT: '$variableIdent' nach {$duration}ms - Wert: {$eventData['oldValue']} → {$eventData['value']}");
                
                return [
                    'success' => true,
                    'duration_ms' => $duration,
                    'old_value' => $eventData['oldValue'],
                    'new_value' => $eventData['value']
                ];
            }
            
            // Kurz warten und wieder prüfen
            usleep(100000); // 100ms
        }
        
        // Timeout erreicht
        $duration = round(($maxSeconds * 1000), 1);
        self::log("TIMEOUT: Event '$variableIdent' nach {$duration}ms nicht gefeuert!");
        
        return [
            'success' => false,
            'duration_ms' => $duration,
            'error' => 'Timeout reached'
        ];
    }
    
    /**
     * Löscht alle temporären Test-Events
     */
    public static function cleanupTestEvents(): void
    {
        $deletedCount = 0;
        
        foreach (self::$activeEvents as $eventID) {
            try {
                if (IPS_ObjectExists($eventID)) {
                    IPS_DeleteEvent($eventID);
                    $deletedCount++;
                }
            } catch (Exception $e) {
                self::log("Warnung: Event $eventID konnte nicht gelöscht werden: " . $e->getMessage());
            }
        }
        
        // Arrays zurücksetzen
        self::$activeEvents = [];
        self::$eventFlags = [];
        
        self::log("Event-Cleanup: $deletedCount Events gelöscht");
    }
    
    /**
     * Testet Kommando mit Event-basierter Validierung
     */
    public static function testCommandWithEvent(
        int $fossibotID, 
        string $variableIdent, 
        callable $testFunction, 
        int $maxWaitSeconds = 60
    ): array {
        try {
            // 1. Aktuellen Wert erfassen
            $variableID = IPS_GetObjectIDByIdent($variableIdent, $fossibotID);
            $oldValue = GetValue($variableID);
            
            // 2. Event erstellen
            $eventID = self::createTestEvent($fossibotID, $variableIdent);
            
            // 3. Test-Kommando ausführen
            self::log("KOMMANDO AUSFÜHREN für Variable '$variableIdent'");
            $commandStartTime = microtime(true);
            $commandResult = call_user_func($testFunction);
            
            if (!$commandResult) {
                throw new Exception("Kommando fehlgeschlagen (Return: false)");
            }
            
            // 4. Auf Event warten
            $eventResult = self::waitForEvent($eventID, $variableIdent, $maxWaitSeconds);
            
            // 5. Event löschen
            if (IPS_ObjectExists($eventID)) {
                IPS_DeleteEvent($eventID);
            }
            
            // 6. Ergebnis zusammenfassen
            $result = [
                'success' => $eventResult['success'],
                'variable_ident' => $variableIdent,
                'old_value' => $oldValue,
                'command_result' => $commandResult,
                'event_result' => $eventResult
            ];
            
            if ($eventResult['success']) {
                self::log("TEST ERFOLGREICH: '$variableIdent' in {$eventResult['duration_ms']}ms");
            } else {
                self::log("TEST FEHLGESCHLAGEN: '$variableIdent' - " . ($eventResult['error'] ?? 'Unbekannter Fehler'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            self::log("TEST FEHLER: " . $e->getMessage());
            
            // Cleanup bei Fehler
            if (isset($eventID) && IPS_ObjectExists($eventID)) {
                IPS_DeleteEvent($eventID);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'variable_ident' => $variableIdent
            ];
        }
    }
    
    /**
     * Gibt aktuellen Log-Inhalt zurück
     */
    public static function getLogContent(): string
    {
        if (file_exists(self::$logFile)) {
            return file_get_contents(self::$logFile);
        }
        return "Log-Datei nicht gefunden";
    }
    
    /**
     * Destruktor für automatischen Cleanup
     */
    public static function shutdown(): void
    {
        self::cleanupTestEvents();
        self::log("=== Fossibot Event-Test beendet ===");
    }
}

// Automatischer Cleanup bei Script-Ende
register_shutdown_function(['FossibotEventTestHelper', 'shutdown']);