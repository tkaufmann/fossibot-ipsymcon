<?php

/**
 * ABOUTME: Fossibot Device Modul für IP-Symcon
 * ABOUTME: Stellt ein einzelnes Fossibot Gerät dar und verwaltet dessen Status
 */
class FossibotDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Properties
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyInteger('UpdateInterval', 30); // Optimaler Keep-Alive Interval

        // Timer registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "TimerUpdate", true);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // FIRST: Create all custom profiles
        $this->CreateChargingStatusProfile();
        $this->CreateDischargingStatusProfile();
        $this->CreateChargingLimitProfile();
        $this->CreateDischargeLimitProfile();
        $this->CreateChargingCurrentProfile();

        // THEN: Register variables with profiles
        // === CLEANUP: Alle veralteten Variablen entfernen ===
        $this->CleanupOldVariables();
        
        // Force-Update für Label-Änderung
        @$this->UnregisterVariable('TotalInput');
        @$this->UnregisterVariable('TotalOutput');
        
        // Hauptvariablen ohne Kategorien (erstmal funktionierend)
        $this->RegisterVariableInteger('BatterySOC', 'Ladezustand', '~Battery.100', 100);
        $this->RegisterVariableFloat('TotalInput', 'Gesamt-Eingang', '~Watt.3680', 110);
        $this->RegisterVariableFloat('TotalOutput', 'Gesamt-Ausgang', '~Watt.3680', 120);

        // === AUSGÄNGE ===
        $this->RegisterVariableBoolean('ACOutput', 'AC Ausgang', '~Switch', 200);
        $this->EnableAction('ACOutput');
        $this->RegisterVariableBoolean('DCOutput', 'DC Ausgang', '~Switch', 210);
        $this->EnableAction('DCOutput');
        $this->RegisterVariableBoolean('USBOutput', 'USB Ausgang', '~Switch', 220);
        $this->EnableAction('USBOutput');

        // === LADEPARAMETER ===
        $this->RegisterVariableInteger('MaxChargingCurrent', 'Max. Ladestrom', 'FBT.ChargingCurrent', 300);
        $this->EnableAction('MaxChargingCurrent');
        // Standardwert setzen falls Variable leer
        if ($this->GetValue('MaxChargingCurrent') == 0) {
            $this->SetValue('MaxChargingCurrent', 3); // 3A = 690W (moderate Ladung)
        }
        
        $this->RegisterVariableInteger('ChargingLimit', 'Ladelimit', 'FBT.ChargingLimit', 310);
        $this->EnableAction('ChargingLimit');
        // Standardwert setzen falls Variable leer
        if ($this->GetValue('ChargingLimit') == 0) {
            $this->SetValue('ChargingLimit', 80);
        }
        
        $this->RegisterVariableInteger('DischargeLimit', 'Entladelimit', 'FBT.DischargeLimit', 320);
        $this->EnableAction('DischargeLimit');
        // Standardwert setzen falls Variable leer
        if ($this->GetValue('DischargeLimit') < 0) {
            $this->SetValue('DischargeLimit', 20);
        }

        // === SYSTEM-INFO ===
        $this->RegisterVariableInteger('LastUpdate', 'Letzte Aktualisierung', '~UnixTimestamp', 900);
        $this->RegisterVariableString('ConnectionStatus', 'Verbindungsstatus', '', 910);

        // Validierung
        $deviceId = $this->ReadPropertyString('DeviceID');
        $credentials = $this->GetDiscoveryCredentials();
        
        if (empty($deviceId) || !$credentials) {
            $this->SetStatus(201); // Inaktiv - Konfiguration erforderlich
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        // Status auf aktiv setzen
        $this->SetStatus(102);
        $this->SetValue('ConnectionStatus', 'Konfiguriert');
        
        // Timer konfigurieren
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval('UpdateTimer', $interval * 1000);
        
        // Nur beim ersten Mal (Create) oder wenn DeviceID geändert wurde, Status aktualisieren
        // Nicht bei simplen Timer-Änderungen!
        static $lastDeviceId = null;
        if ($lastDeviceId === null || $lastDeviceId !== $deviceId) {
            $lastDeviceId = $deviceId;
            // Erste Aktualisierung
            $this->FBT_UpdateDeviceStatus();
        }
    }

    /**
     * RequestAction Handler für Button-Aktionen
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'RefreshNow':
                $this->FBT_UpdateDeviceStatus();
                break;
            case 'GetDeviceInfo':
                $info = $this->FBT_GetDeviceInfo();
                $this->LogMessage('Geräteinformationen: ' . $info, KL_NOTIFY);
                break;
            case 'TimerUpdate':
                $this->LogMessage('Keep-Alive Timer-Update ausgeführt', KL_DEBUG);
                // Keep-Alive Update: Verhindert dass F2400 in Schlafmodus geht
                $this->FBT_UpdateDeviceStatus();
                break;
            case 'SetACOutput':
                $this->FBT_SetACOutput($Value);
                break;
            case 'SetDCOutput':
                $this->FBT_SetDCOutput($Value);
                break;
            case 'SetUSBOutput':
                $this->FBT_SetUSBOutput($Value);
                break;
            case 'SetMaxChargingCurrent':
                $this->FBT_SetMaxChargingCurrent($Value);
                break;
            case 'SetChargingLimit':
                $this->FBT_SetChargingLimit($Value);
                break;
            case 'SetDischargeLimit':
                $this->FBT_SetDischargeLimit($Value);
                break;
            case 'RequestSettings':
                $this->FBT_RequestSettings();
                break;
            case 'ClearTokenCache':
                $this->FBT_ClearTokenCache();
                break;
            case 'ChargingLimit':
                $this->FBT_SetChargingLimit($Value);
                break;
            case 'DischargeLimit':
                $this->FBT_SetDischargeLimit($Value);
                break;
            case 'MaxChargingCurrent':
                $this->FBT_SetMaxChargingCurrent($Value);
                break;
            case 'ACOutput':
                $this->FBT_SetACOutput($Value);
                break;
            case 'DCOutput':
                $this->FBT_SetDCOutput($Value);
                break;
            case 'USBOutput':
                $this->FBT_SetUSBOutput($Value);
                break;
            default:
                parent::RequestAction($Ident, $Value);
                break;
        }
    }

    /**
     * Gerätestatus aktualisieren
     */
    public function FBT_UpdateDeviceStatus()
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        $credentials = $this->GetDiscoveryCredentials();

        if (empty($deviceId) || !$credentials) {
            $this->LogMessage('Gerät nicht konfiguriert oder keine Discovery-Instanz gefunden', KL_ERROR);
            $this->SetValue('ConnectionStatus', 'Fehler: Gerät nicht konfiguriert');
            return false;
        }

        // Status anzeigen
        $this->SetValue('ConnectionStatus', 'Aktualisiere Status...');

        try {
            // Load helper classes
            require_once __DIR__ . '/../libs/FossibotConnectionPool.php';
            require_once __DIR__ . '/../libs/FossibotSemaphore.php';
            
            // Acquire semaphore to prevent collisions
            if (!FossibotSemaphore::acquire('status_update', 5000)) {
                $this->LogMessage('Status-Update blockiert - andere Operation läuft', KL_WARNING);
                $this->SetValue('ConnectionStatus', 'Warte auf andere Operation...');
                return false;
            }
            
            try {
                // Get connection from pool (nutzt Cache!)
                // WICHTIG: Gleicher Key wie SendDeviceCommand damit gleiche Connection!
                $client = FossibotConnectionPool::getConnection(
                    $credentials['email'],
                    $credentials['password'],
                    $this->InstanceID
                );
                
                if (!$client) {
                    throw new Exception("Verbindung fehlgeschlagen");
                }
                
                // Devices sind bereits im Pool gecached
                $devices = $client->getDevices();
                $this->LogMessage('Debug: Geräte aus Pool, Anzahl: ' . count($devices), KL_DEBUG);
            
            // Geräteeinstellungen anfordern
            $this->SetValue('ConnectionStatus', 'Lade Gerätedaten...');
            $client->requestDeviceSettings($deviceId);
            
            // Kurz warten und auf Updates hören
            $client->listenForUpdates(5);
            
            // Alle verfügbaren Geräte-IDs prüfen
            $allDeviceIds = $client->getDeviceIds();
            $this->LogMessage('Debug: Verfügbare Geräte-IDs: ' . json_encode($allDeviceIds), KL_DEBUG);
            
            // Aktuellen Status abrufen
            $status = $client->getDeviceStatus($deviceId);
            $this->LogMessage('Debug: Status für ' . $deviceId . ': ' . json_encode($status), KL_DEBUG);
            
            // Fallback: Falls deviceId nicht funktioniert, versuche alle verfügbaren IDs
            if (!$status) {
                foreach ($allDeviceIds as $availableId) {
                    $this->LogMessage('Debug: Versuche alternative ID: ' . $availableId, KL_DEBUG);
                    $status = $client->getDeviceStatus($availableId);
                    if ($status) {
                        $this->LogMessage('Debug: Erfolg mit alternativer ID: ' . $availableId, KL_DEBUG);
                        break;
                    }
                }
            }
            
            if ($status && !empty($status)) {
                $this->ProcessStatusData($status);
                $this->SetValue('ConnectionStatus', 'Online');
                $this->SetValue('LastUpdate', time());
                
                // Timer wieder auf normale Intervall-Zeit zurücksetzen falls er verkürzt wurde
                $normalInterval = $this->ReadPropertyInteger('UpdateInterval') * 1000;
                if ($normalInterval > 0) {
                    $this->SetTimerInterval('UpdateTimer', $normalInterval);
                }
                
                // Status-Update erfolgreich
                $this->LogMessage('Status aktualisiert', KL_DEBUG);
                
                // Release connection back to pool (NICHT disconnect!)
                FossibotConnectionPool::releaseConnection($this->InstanceID);
                FossibotSemaphore::release('status_update');
                
                return true;
            } else {
                $this->SetValue('ConnectionStatus', 'Fehler: Keine Daten empfangen');
                $this->LogMessage('Keine Statusdaten empfangen - Alle Versuche fehlgeschlagen', KL_WARNING);
                
                // Debug: Zeige komplettes devices Array
                $allData = $client->getAllDeviceData();
                $this->LogMessage('Debug: Komplettes devices Array: ' . json_encode($allData), KL_DEBUG);
                
                // Release connection back to pool
                FossibotConnectionPool::releaseConnection($this->InstanceID);
                FossibotSemaphore::release('status_update');
                
                return false;
            }
            
            } finally {
                // Always release resources
                FossibotConnectionPool::releaseConnection($this->InstanceID);
                FossibotSemaphore::release('status_update');
            }
            
        } catch (Exception $e) {
            $this->SetValue('ConnectionStatus', 'Fehler: ' . $e->getMessage());
            $this->LogMessage('Update-Fehler: ' . $e->getMessage(), KL_ERROR);
            return false;
        }
    }

    /**
     * Statusdaten verarbeiten und Variablen aktualisieren
     */
    private function ProcessStatusData($status)
    {
        // KOMPLETTE MQTT-RESPONSE LOGGEN
        $this->LogMessage('MQTT-RESPONSE KOMPLETT: ' . json_encode($status, JSON_PARTIAL_OUTPUT_ON_ERROR), KL_NOTIFY);
        // Entfernt: Zu viel Debug-Output
        
        // Der SydpowerClient speichert die Daten in diesem Format:
        // $this->devices[$deviceMac]['soc'] = round(($registers[56] / 1000) * 100, 1);
        // $this->devices[$deviceMac]['totalInput'] = $registers[6];
        // $this->devices[$deviceMac]['totalOutput'] = $registers[39];
        // etc.
        
        // SOC (State of Charge) - Batterie-Ladezustand
        if (isset($status['soc'])) {
            $this->SetValue('BatterySOC', round($status['soc']));
        }

        // Eingangs-/Ausgangsleistung
        $totalInput = 0;
        $totalOutput = 0;
        
        if (isset($status['totalInput'])) {
            $totalInput = floatval($status['totalInput']);
            $this->SetValue('TotalInput', $totalInput);
        }
        if (isset($status['totalOutput'])) {
            $totalOutput = floatval($status['totalOutput']);
            $this->SetValue('TotalOutput', $totalOutput);
        }
        
        // Redundante Status-Variablen entfernt - Werte sind bereits in TotalInput/TotalOutput

        // Ausgangsstatus
        if (isset($status['acOutput'])) {
            $this->SetValue('ACOutput', $status['acOutput'] ? true : false);
        }
        if (isset($status['dcOutput'])) {
            $this->SetValue('DCOutput', $status['dcOutput'] ? true : false);
        }
        if (isset($status['usbOutput'])) {
            $this->SetValue('USBOutput', $status['usbOutput'] ? true : false);
        }

        // Ladelimits (Werte sind bereits in Promille, durch 10 teilen für Prozent)
        // WICHTIG: Nur aktualisieren wenn Wert vorhanden, NIE löschen!
        if (isset($status['acChargingUpperLimit'])) {
            if ($status['acChargingUpperLimit'] > 0) {
                $chargingLimit = round($status['acChargingUpperLimit'] / 10);
                $this->SetValue('ChargingLimit', $chargingLimit);
                $this->LogMessage('LADELIMIT-RESPONSE: F2400 meldet acChargingUpperLimit=' . $status['acChargingUpperLimit'] . ' Promille = ' . $chargingLimit . '%', KL_NOTIFY);
            }
            // Wenn 0, dann NICHT aktualisieren (könnte fehlende Daten bedeuten)
        }
        // Kein else-Zweig mehr - wenn nicht vorhanden, alten Wert behalten!
        
        if (isset($status['dischargeLowerLimit'])) {
            if ($status['dischargeLowerLimit'] > 0) {
                $dischargeLimit = round($status['dischargeLowerLimit'] / 10);
                $this->SetValue('DischargeLimit', $dischargeLimit);
            }
            // Wenn 0, dann NICHT aktualisieren
        }
        // Kein else-Zweig - wenn nicht vorhanden, alten Wert behalten!
        
        if (isset($status['maximumChargingCurrent'])) {
            if ($status['maximumChargingCurrent'] > 0) {
                $this->SetValue('MaxChargingCurrent', intval($status['maximumChargingCurrent']));
            }
            // Wenn 0, dann NICHT aktualisieren (fehlerhafte Response von Output-Commands)
        }
        // WICHTIG: MaxChargingCurrent nicht löschen wenn nicht in Response
        // (passiert bei Output-Commands die keine Settings mitliefern)
    }

    /**
     * Aktualisierungsintervall ändern
     */
    public function FBT_SetUpdateInterval(int $seconds)
    {
        if ($seconds < 30) {
            $this->LogMessage('Aktualisierungsintervall muss mindestens 30 Sekunden betragen (Keep-Alive für F2400)', KL_WARNING);
            return false;
        }

        IPS_SetProperty($this->InstanceID, 'UpdateInterval', $seconds);
        IPS_ApplyChanges($this->InstanceID);
        
        $this->LogMessage('Aktualisierungsintervall geändert auf ' . $seconds . ' Sekunden', KL_NOTIFY);
        return true;
    }

    /**
     * Manuelle Aktualisierung auslösen
     */
    public function FBT_RefreshNow()
    {
        return $this->FBT_UpdateDeviceStatus();
    }

    /**
     * Cache löschen (z.B. wenn Discovery neue Geräte sucht)
     */
    public function FBT_ClearDeviceCache()
    {
        // Statische Variablen zurücksetzen
        $clearCache = function() {
            static $cachedDevices = null;
            static $devicesCacheTime = 0;
            $cachedDevices = null;
            $devicesCacheTime = 0;
        };
        $clearCache();
        
        $this->LogMessage('DEVICE-CACHE: Cache gelöscht - nächster Befehl lädt Geräteliste neu', KL_NOTIFY);
        return true;
    }
    
    /**
     * Geräteinformationen abrufen
     */
    public function FBT_GetDeviceInfo()
    {
        $info = [
            'DeviceID' => $this->ReadPropertyString('DeviceID'),
            'UpdateInterval' => $this->ReadPropertyInteger('UpdateInterval'),
            'Status' => $this->GetStatus(),
            'LastUpdate' => $this->GetValue('LastUpdate'),
            'ConnectionStatus' => $this->GetValue('ConnectionStatus')
        ];
        
        return json_encode($info);
    }

    /**
     * AC-Ausgang ein-/ausschalten
     */
    public function FBT_SetACOutput(bool $enabled, bool $statusUpdate = true)
    {
        $command = $enabled ? 'REGEnableACOutput' : 'REGDisableACOutput';
        $statusText = $enabled ? 'Schalte AC-Ausgang ein' : 'Schalte AC-Ausgang aus';
        
        $this->SetValue('ConnectionStatus', $statusText . '...');
        $this->LogMessage("AC Output: " . ($enabled ? 'EIN' : 'AUS'), KL_NOTIFY);
        return $this->SendDeviceCommand($command, null, $statusUpdate);
    }

    /**
     * DC-Ausgang ein-/ausschalten
     */
    public function FBT_SetDCOutput(bool $enabled, bool $statusUpdate = true)
    {
        $command = $enabled ? 'REGEnableDCOutput' : 'REGDisableDCOutput';
        $statusText = $enabled ? 'Schalte DC-Ausgang ein' : 'Schalte DC-Ausgang aus';
        
        $this->SetValue('ConnectionStatus', $statusText . '...');
        $this->LogMessage("DC Output: " . ($enabled ? 'EIN' : 'AUS'), KL_NOTIFY);
        return $this->SendDeviceCommand($command, null, $statusUpdate);
    }

    /**
     * USB-Ausgang ein-/ausschalten
     */
    public function FBT_SetUSBOutput(bool $enabled, bool $statusUpdate = true)
    {
        $command = $enabled ? 'REGEnableUSBOutput' : 'REGDisableUSBOutput';
        $statusText = $enabled ? 'Schalte USB-Ausgang ein' : 'Schalte USB-Ausgang aus';
        
        $this->SetValue('ConnectionStatus', $statusText . '...');
        $this->LogMessage("USB Output: " . ($enabled ? 'EIN' : 'AUS'), KL_NOTIFY);
        return $this->SendDeviceCommand($command, null, $statusUpdate);
    }

    /**
     * Ladelimit setzen (60-100%)
     */
    public function FBT_SetChargingLimit(int $percent, bool $statusUpdate = true)
    {
        $this->LogMessage("LADELIMIT-DEBUG: Setze Ladelimit von {$percent}% (statusUpdate: " . ($statusUpdate ? 'true' : 'false') . ")", KL_NOTIFY);
        
        if ($percent < 60 || $percent > 100) {
            $this->LogMessage('Ladelimit muss zwischen 60-100% liegen', KL_ERROR);
            $this->SetValue('ConnectionStatus', 'Fehler: Ungültiger Wert (60-100%)');
            return false;
        }
        
        $this->SetValue('ConnectionStatus', "Setze Ladelimit auf {$percent}%...");
        $promille = $percent * 10; // Konvertierung zu Promille
        $this->LogMessage("LADELIMIT-DEBUG: Konvertiert zu Promille: {$promille}", KL_NOTIFY);
        
        $result = $this->SendDeviceCommand('REGChargeUpperLimit', $promille, $statusUpdate);
        
        $this->LogMessage("LADELIMIT-DEBUG: SendDeviceCommand Ergebnis: " . ($result ? 'true' : 'false'), KL_NOTIFY);
        
        return $result;
    }

    /**
     * Maximalen Ladestrom setzen (1-5A für F2400, max 1100W)
     */
    public function FBT_SetMaxChargingCurrent(int $ampere, bool $statusUpdate = true)
    {
        // F2400 unterstützt nur 1-5A Ladestrom (max 1100W)
        if ($ampere < 1 || $ampere > 5) {
            $this->LogMessage('Ladestrom muss zwischen 1-5A liegen', KL_ERROR);
            $this->SetValue('ConnectionStatus', 'Fehler: Ungültiger Wert (1-5A)');
            return false;
        }
        
        // Status anzeigen
        $this->SetValue('ConnectionStatus', "Ändere Ladestrom auf {$ampere}A...");
        $this->LogMessage("Setze Max. Ladestrom auf {$ampere}A", KL_NOTIFY);
        
        // Command senden OHNE auto-refresh (false)
        $result = $this->SendDeviceCommand('REGMaxChargeCurrent', $ampere, false);
        
        if ($result) {
            // Timer temporär auf 3s verkürzen für schnelles Update
            $this->SetTimerInterval('UpdateTimer', 3000);
            $this->LogMessage("Timer auf 3s gesetzt für Ladestrom-Update", KL_DEBUG);
        } else {
            $this->SetValue('ConnectionStatus', 'Fehler: Ladestrom-Änderung fehlgeschlagen');
        }
        
        return $result;
    }

    /**
     * Lade-Timer setzen (in Minuten)
     */
    public function FBT_SetChargeTimer(int $minutes, bool $statusUpdate = true)
    {
        if ($minutes < 0) {
            $this->LogMessage('Lade-Timer muss positiv sein', KL_ERROR);
            $this->SetValue('ConnectionStatus', 'Fehler: Ungültiger Timer-Wert');
            return false;
        }
        
        $this->SetValue('ConnectionStatus', "Setze Lade-Timer auf {$minutes} Min...");
        return $this->SendDeviceCommand('REGStopChargeAfter', $minutes, $statusUpdate);
    }

    /**
     * Entladelimit setzen (0-50%)
     */
    public function FBT_SetDischargeLimit(int $percent, bool $statusUpdate = true)
    {
        if ($percent < 0 || $percent > 50) {
            $this->LogMessage('Entladelimit muss zwischen 0-50% liegen', KL_ERROR);
            $this->SetValue('ConnectionStatus', 'Fehler: Ungültiger Wert (0-50%)');
            return false;
        }
        
        $this->SetValue('ConnectionStatus', "Setze Entladelimit auf {$percent}%...");
        $promille = $percent * 10; // Konvertierung zu Promille
        return $this->SendDeviceCommand('REGDischargeLowerLimit', $promille, $statusUpdate);
    }


    /**
     * Geräteeinstellungen manuell anfordern
     */
    public function FBT_RequestSettings()
    {
        // Einfach den Command senden - SendDeviceCommand kümmert sich um alles
        $this->SetValue('ConnectionStatus', 'Fordere Einstellungen an...');
        return $this->SendDeviceCommand('REGRequestSettings', null, true);
    }

    /**
     * Token-Cache leeren (bei Token-Problemen)
     */
    public function FBT_ClearTokenCache()
    {
        $credentials = $this->GetDiscoveryCredentials();
        
        if (!$credentials) {
            $this->LogMessage('Keine Discovery-Instanz konfiguriert', KL_ERROR);
            return false;
        }
        
        try {
            require_once __DIR__ . '/../libs/FossibotConnectionPool.php';
            require_once __DIR__ . '/../libs/SydpowerClient.php';
            
            // Pool zurücksetzen, um alle Connections zu schließen
            FossibotConnectionPool::reset();
            
            // Token-Cache direkt leeren
            $client = new SydpowerClient($credentials['email'], $credentials['password']);
            $client->clearTokenCache();
            
            $this->LogMessage('Token-Cache und Connection-Pool geleert', KL_NOTIFY);
            $this->SetValue('ConnectionStatus', 'Cache geleert');
            
            return true;
            
        } catch (Exception $e) {
            $this->LogMessage('Fehler beim Leeren des Cache: ' . $e->getMessage(), KL_ERROR);
            return false;
        }
    }

    /**
     * Gibt deutschen Namen für Command zurück
     */
    private function getCommandDisplayName(string $command, $value = null): string
    {
        switch($command) {
            case 'REGEnableACOutput':
                return 'Schalte AC-Ausgang ein';
            case 'REGDisableACOutput':
                return 'Schalte AC-Ausgang aus';
            case 'REGEnableDCOutput':
                return 'Schalte DC-Ausgang ein';
            case 'REGDisableDCOutput':
                return 'Schalte DC-Ausgang aus';
            case 'REGEnableUSBOutput':
                return 'Schalte USB-Ausgang ein';
            case 'REGDisableUSBOutput':
                return 'Schalte USB-Ausgang aus';
            case 'REGMaxChargeCurrent':
                return $value ? "Setze Ladestrom auf {$value}A" : 'Setze Ladestrom';
            case 'REGChargeUpperLimit':
                return $value ? "Setze Ladelimit auf {$value}%" : 'Setze Ladelimit';
            case 'REGDischargeLowerLimit':
                return $value ? "Setze Entladelimit auf {$value}%" : 'Setze Entladelimit';
            case 'REGStopChargeAfter':
                return $value ? "Setze Lade-Timer auf {$value} Min" : 'Setze Lade-Timer';
            case 'REGRequestSettings':
                return 'Fordere Einstellungen an';
            default:
                return "Sende {$command}";
        }
    }

    /**
     * Befehl an das Gerät senden - REFACTORED mit neuen Pool/Validator/Semaphore Klassen
     */
    private function SendDeviceCommand(string $command, $value, bool $autoRefresh = true)
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        $credentials = $this->GetDiscoveryCredentials();

        if (empty($deviceId) || !$credentials) {
            $this->LogMessage('Gerät nicht konfiguriert', KL_ERROR);
            $this->SetValue('ConnectionStatus', 'Fehler: Gerät nicht konfiguriert');
            return false;
        }

        // Load new helper classes
        require_once __DIR__ . '/../libs/FossibotConnectionPool.php';
        require_once __DIR__ . '/../libs/FossibotResponseValidator.php';
        require_once __DIR__ . '/../libs/FossibotSemaphore.php';
        
        // Acquire semaphore to prevent collisions
        if (!FossibotSemaphore::acquire('mqtt_command', 5000)) {
            $this->LogMessage('Command blocked - another operation in progress', KL_WARNING);
            $this->SetValue('ConnectionStatus', 'Warte auf andere Operation...');
            return false;
        }

        // Zeige Status-Meldung nur wenn nicht bereits gesetzt (z.B. von SetMaxChargingCurrent)
        $currentStatus = $this->GetValue('ConnectionStatus');
        if (strpos($currentStatus, '...') === false) {
            $displayName = $this->getCommandDisplayName($command, $value);
            $this->SetValue('ConnectionStatus', $displayName . '...');
        }

        try {
            // Get connection from pool (handles caching automatically)
            $client = FossibotConnectionPool::getConnection(
                $credentials['email'],
                $credentials['password'],
                $this->InstanceID
            );
            
            if (!$client) {
                throw new Exception("Verbindung fehlgeschlagen");
            }
            
            // Special handling for AC Output when charging current is 0
            if ($command === 'REGEnableACOutput') {
                // Check current charging current
                $status = $client->getDeviceStatus($deviceId);
                $currentChargingCurrent = isset($status['maximumChargingCurrent']) ? $status['maximumChargingCurrent'] : 0;
                
                if ($currentChargingCurrent == 0) {
                    $this->LogMessage('AC Output benötigt Ladestrom > 0, setze auf 5A', KL_NOTIFY);
                    
                    // Set charging current to 5A first
                    $client->sendCommand($deviceId, 'REGMaxChargeCurrent', 5);
                    
                    // Wait for it to apply
                    $chargeResponse = FossibotResponseValidator::waitForValidResponse(
                        $client,
                        $deviceId,
                        'REGMaxChargeCurrent',
                        5
                    );
                    
                    if (!$chargeResponse['success']) {
                        $this->LogMessage('Warnung: Ladestrom konnte nicht gesetzt werden', KL_WARNING);
                    }
                }
            }
            
            // Send command
            $this->LogMessage("Sending command: $command with value: " . json_encode($value), KL_DEBUG);
            $result = $client->sendCommand($deviceId, $command, $value);
            
            if (!$result) {
                throw new Exception("Command konnte nicht gesendet werden");
            }
            
            // Auto-refresh handling with intelligent response validation
            if ($autoRefresh) {
                $this->LogMessage('Waiting for validated response...', KL_DEBUG);
                
                // Use ResponseValidator for intelligent waiting
                $response = FossibotResponseValidator::waitForValidResponse(
                    $client,
                    $deviceId,
                    $command,
                    $value
                );
                
                if ($response['success']) {
                    // Update frontend with validated data
                    $this->ProcessStatusData($response['data']);
                    $this->SetValue('LastUpdate', time());
                    $this->SetValue('ConnectionStatus', 'Online');
                    
                    $this->LogMessage("✅ Command successful in {$response['time']}ms", KL_NOTIFY);
                    
                    // Release connection back to pool
                    FossibotConnectionPool::releaseConnection($this->InstanceID);
                    FossibotSemaphore::release('mqtt_command');
                    
                    return true;
                    
                } else {
                    // Timeout or partial response
                    if (isset($response['partial']) && !empty($response['partial'])) {
                        $this->LogMessage('⚠️ Partial response received, updating what we have', KL_WARNING);
                        $this->ProcessStatusData($response['partial']);
                        $this->SetValue('LastUpdate', time());
                        
                        // Bei Output-Commands: Wenn das Hauptfeld korrekt ist, ist es OK
                        $isOutputCommand = strpos($command, 'Output') !== false;
                        if ($isOutputCommand) {
                            // Prüfe ob der gewünschte Output-Status erreicht wurde
                            $outputOk = false;
                            if (strpos($command, 'ACOutput') !== false && isset($response['partial']['acOutput'])) {
                                $outputOk = true;
                            } elseif (strpos($command, 'DCOutput') !== false && isset($response['partial']['dcOutput'])) {
                                $outputOk = true;
                            } elseif (strpos($command, 'USBOutput') !== false && isset($response['partial']['usbOutput'])) {
                                $outputOk = true;
                            }
                            
                            if ($outputOk) {
                                $this->SetValue('ConnectionStatus', 'Online');
                                $this->LogMessage('Output-Command erfolgreich (trotz partial response)', KL_DEBUG);
                                return true;
                            }
                        }
                        
                        // Sonst als Warnung markieren
                        $this->SetValue('ConnectionStatus', 'Online (teilweise Daten)');
                    } else {
                        // Gar keine Daten erhalten
                        $missing = isset($response['missing']) ? implode(', ', $response['missing']) : 'unknown';
                        $this->LogMessage("⚠️ Response validation failed. Missing: {$missing}", KL_WARNING);
                        $this->SetValue('ConnectionStatus', 'Warnung: Keine Antwort');
                    }
                }
            } else {
                // Ohne autoRefresh setzen wir Status auf "Warte auf Update..."
                // Der Timer wird dann das Update durchführen
                $this->SetValue('ConnectionStatus', 'Warte auf Update...');
            }
            
            // Release resources
            FossibotConnectionPool::releaseConnection($this->InstanceID);
            FossibotSemaphore::release('mqtt_command');
            
            return $result;
            
        } catch (Exception $e) {
            $this->LogMessage('Fehler beim Senden: ' . $e->getMessage(), KL_ERROR);
            $this->SetValue('ConnectionStatus', 'Fehler: ' . $e->getMessage());
            
            // Always release resources
            FossibotConnectionPool::releaseConnection($this->InstanceID);
            FossibotSemaphore::release('mqtt_command');
            
            return false;
        }
    }
    
    /**
     * Prüft ob Befehl Settings-Änderungen betrifft (braucht Auto-Refresh)
     */
    private function isSettingsCommand(string $command): bool
    {
        $settingsCommands = [
            'REGChargeUpperLimit',
            'REGMaxChargeCurrent', 
            'REGStopChargeAfter',
            'REGDischargeLowerLimit',
            'REGUSBStandbyTime',
            'REGACStandbyTime',
            'REGDCStandbyTime'
        ];
        
        return in_array($command, $settingsCommands);
    }
    

    /**
     * Erstellt Custom Profile für Eingang-Status
     */
    private function CreateChargingStatusProfile()
    {
        $profileName = 'FBT.ChargingStatus';
        
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 2); // 2 = Float
            IPS_SetVariableProfileText($profileName, '', 'W');
            IPS_SetVariableProfileDigits($profileName, 1);
            IPS_SetVariableProfileIcon($profileName, 'Energy');
        }
    }

    /**
     * Erstellt Custom Profile für Ausgang-Status  
     */
    private function CreateDischargingStatusProfile()
    {
        $profileName = 'FBT.DischargingStatus';
        
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 2); // 2 = Float
            IPS_SetVariableProfileText($profileName, '', 'W');
            IPS_SetVariableProfileDigits($profileName, 1);
            IPS_SetVariableProfileIcon($profileName, 'Electricity');
        }
    }

    /**
     * Erstellt Custom Profile für Ladelimit-Slider (60-100%)
     */
    private function CreateChargingLimitProfile()
    {
        $profileName = 'FBT.ChargingLimit';
        
        // Profile löschen falls vorhanden, um es neu zu erstellen
        if (IPS_VariableProfileExists($profileName)) {
            try {
                IPS_DeleteVariableProfile($profileName);
            } catch (Exception $e) {
                // Profile wird verwendet, nicht löschen
                return;
            }
        }
        
        IPS_CreateVariableProfile($profileName, 1); // 1 = Integer
        IPS_SetVariableProfileText($profileName, '', '%');
        IPS_SetVariableProfileIcon($profileName, 'Battery');
        
        // Nur die erlaubten Werte als Associations (ohne % in Text)
        IPS_SetVariableProfileAssociation($profileName, 60, '60', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profileName, 65, '65', '', 0xFF4000);
        IPS_SetVariableProfileAssociation($profileName, 70, '70', '', 0xFF8000);
        IPS_SetVariableProfileAssociation($profileName, 75, '75', '', 0xFFBF00);
        IPS_SetVariableProfileAssociation($profileName, 80, '80', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profileName, 85, '85', '', 0xBFFF00);
        IPS_SetVariableProfileAssociation($profileName, 90, '90', '', 0x80FF00);
        IPS_SetVariableProfileAssociation($profileName, 95, '95', '', 0x40FF00);
        IPS_SetVariableProfileAssociation($profileName, 100, '100', '', 0x00FF00);
    }

    /**
     * Erstellt Custom Profile für Entladelimit-Slider (0-50%)
     */
    private function CreateDischargeLimitProfile()
    {
        $profileName = 'FBT.DischargeLimit';
        
        // Profile löschen falls vorhanden, um es neu zu erstellen
        if (IPS_VariableProfileExists($profileName)) {
            try {
                IPS_DeleteVariableProfile($profileName);
            } catch (Exception $e) {
                // Profile wird verwendet, nicht löschen
                return;
            }
        }
        
        IPS_CreateVariableProfile($profileName, 1); // 1 = Integer
        IPS_SetVariableProfileText($profileName, '', '%');
        IPS_SetVariableProfileIcon($profileName, 'Battery');
        
        // Nur die erlaubten Werte als Associations (ohne % in Text)
        IPS_SetVariableProfileAssociation($profileName, 0, '0', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 5, '5', '', 0x20FF00);
        IPS_SetVariableProfileAssociation($profileName, 10, '10', '', 0x40FF00);
        IPS_SetVariableProfileAssociation($profileName, 15, '15', '', 0x60FF00);
        IPS_SetVariableProfileAssociation($profileName, 20, '20', '', 0x80FF00);
        IPS_SetVariableProfileAssociation($profileName, 25, '25', '', 0xA0FF00);
        IPS_SetVariableProfileAssociation($profileName, 30, '30', '', 0xC0FF00);
        IPS_SetVariableProfileAssociation($profileName, 35, '35', '', 0xE0FF00);
        IPS_SetVariableProfileAssociation($profileName, 40, '40', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profileName, 45, '45', '', 0xFFE000);
        IPS_SetVariableProfileAssociation($profileName, 50, '50', '', 0xFF0000);
    }

    /**
     * Erstellt Custom Profile für Ladestrom-Dropdown (1,5,10,15,20A)
     */
    private function CreateChargingCurrentProfile()
    {
        $profileName = 'FBT.ChargingCurrent';
        
        // Profile löschen falls vorhanden, um es neu zu erstellen
        if (IPS_VariableProfileExists($profileName)) {
            try {
                IPS_DeleteVariableProfile($profileName);
            } catch (Exception $e) {
                // Profile wird verwendet, nicht löschen
                return;
            }
        }
        
        IPS_CreateVariableProfile($profileName, 1); // 1 = Integer
        IPS_SetVariableProfileText($profileName, '', 'A');
        IPS_SetVariableProfileIcon($profileName, 'Electricity');
        
        // Dropdown-Werte für F2400 (1-5A, da max 1100W AC)
        IPS_SetVariableProfileAssociation($profileName, 1, '1', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 2, '2', '', 0x40FF00);
        IPS_SetVariableProfileAssociation($profileName, 3, '3', '', 0x80FF00);
        IPS_SetVariableProfileAssociation($profileName, 4, '4', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profileName, 5, '5', '', 0xFF8000);
    }

    /**
     * Entfernt alle veralteten/irreführenden Variablen
     */
    private function CleanupOldVariables()
    {
        // Liste aller möglichen veralteten Variable-Idents
        $oldVariables = [
            'ChargingStatus',
            'DischargingStatus', 
            'Lädt gerade',
            'Entlädt gerade',
            'ChargingIndicator',
            'DischargingIndicator'
        ];
        
        foreach ($oldVariables as $ident) {
            @$this->UnregisterVariable($ident);
        }
        
        // Auch alle Variablen mit "gerade" im Namen finden und löschen
        $allVars = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($allVars as $varID) {
            if (IPS_GetObject($varID)['ObjectType'] == 2) { // 2 = Variable
                $name = IPS_GetName($varID);
                if (strpos($name, 'gerade') !== false || 
                    strpos($name, 'Status') !== false && 
                    (strpos($name, 'Lade') !== false || strpos($name, 'Entlade') !== false)) {
                    @IPS_DeleteVariable($varID);
                }
            }
        }
    }

    /**
     * Sucht automatisch die Discovery-Instanz und holt Zugangsdaten
     */
    private function GetDiscoveryCredentials()
    {
        $discoveryModuleID = '{A67EA473-8B4A-0901-E134-21F1363D9036}'; // FossibotDiscovery GUID
        $instances = IPS_GetInstanceListByModuleID($discoveryModuleID);
        
        if (empty($instances)) {
            $this->LogMessage('Keine FossibotDiscovery-Instanz gefunden', KL_ERROR);
            return false;
        }
        
        // Nimm die erste gefundene Discovery-Instanz
        $discoveryInstance = $instances[0];
        
        $email = IPS_GetProperty($discoveryInstance, 'Email');
        $password = IPS_GetProperty($discoveryInstance, 'Password');
        
        if (empty($email) || empty($password)) {
            $this->LogMessage('Discovery-Instanz hat keine Zugangsdaten konfiguriert', KL_ERROR);
            return false;
        }
        
        return [
            'email' => $email,
            'password' => $password
        ];
    }
}