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
        $this->RegisterPropertyInteger('UpdateInterval', 120);

        // Energie-Variablen
        $this->RegisterVariableInteger('BatterySOC', 'Ladezustand', '~Battery.100', 100);
        $this->RegisterVariableFloat('TotalInput', 'Eingangsleistung', '~Watt.3680', 110);
        $this->RegisterVariableFloat('TotalOutput', 'Ausgangsleistung', '~Watt.3680', 120);

        // Ausgänge
        $this->RegisterVariableBoolean('ACOutput', 'AC Ausgang', '~Switch', 200);
        $this->RegisterVariableBoolean('DCOutput', 'DC Ausgang', '~Switch', 210);
        $this->RegisterVariableBoolean('USBOutput', 'USB Ausgang', '~Switch', 220);

        // Ladelimits
        $this->RegisterVariableInteger('ChargingLimit', 'Ladelimit', '~Battery.100', 300);
        $this->RegisterVariableInteger('DischargeLimit', 'Entladelimit', '~Battery.100', 310);
        $this->RegisterVariableInteger('MaxChargingCurrent', 'Max. Ladestrom', '', 320);

        // Systemstatus
        $this->RegisterVariableInteger('LastUpdate', 'Letzte Aktualisierung', '~UnixTimestamp', 400);
        $this->RegisterVariableString('ConnectionStatus', 'Verbindungsstatus', '', 410);

        // Timer registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "TimerUpdate", true);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

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
        
        // Erste Aktualisierung
        $this->FBT_UpdateDeviceStatus();
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
                $this->LogMessage('Timer-Update ausgeführt', KL_DEBUG);
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
            return false;
        }

        try {
            // Fossibot Client laden
            require_once __DIR__ . '/../libs/SydpowerClient.php';
            
            $client = new SydpowerClient($credentials['email'], $credentials['password']);
            $client->authenticate();
            
            // Gerätedaten abrufen
            $this->SetValue('ConnectionStatus', 'Lade Geräteliste...');
            
            // Zuerst Geräteliste laden (wichtig!)
            $devices = $client->getDevices();
            $this->LogMessage('Debug: Geräteliste geladen, Anzahl: ' . count($devices), KL_DEBUG);
            
            // MQTT verbinden
            $this->SetValue('ConnectionStatus', 'Verbinde MQTT...');
            $client->connectMqtt();
            
            // Geräteeinstellungen anfordern
            $this->SetValue('ConnectionStatus', 'Lade Gerätedaten...');
            $client->requestDeviceSettings($deviceId);
            
            // Kurz warten und auf Updates hören
            $client->listenForUpdates(5);
            
            // MQTT-Verbindung sauber schließen
            $client->disconnect();
            
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
                $this->SetValue('ConnectionStatus', 'Verbunden');
                $this->SetValue('LastUpdate', time());
                
                // Status-Update erfolgreich
                $this->LogMessage('Status aktualisiert', KL_DEBUG);
                
                return true;
            } else {
                $this->SetValue('ConnectionStatus', 'Keine Daten');
                $this->LogMessage('Keine Statusdaten empfangen - Alle Versuche fehlgeschlagen', KL_WARNING);
                
                // Debug: Zeige komplettes devices Array
                $allData = $client->getAllDeviceData();
                $this->LogMessage('Debug: Komplettes devices Array: ' . json_encode($allData), KL_DEBUG);
                
                return false;
            }
            
        } catch (Exception $e) {
            $this->SetValue('ConnectionStatus', 'Fehler');
            $this->LogMessage('Update-Fehler: ' . $e->getMessage(), KL_ERROR);
            return false;
        }
    }

    /**
     * Statusdaten verarbeiten und Variablen aktualisieren
     */
    private function ProcessStatusData($status)
    {
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
        if (isset($status['totalInput'])) {
            $this->SetValue('TotalInput', floatval($status['totalInput']));
        }
        if (isset($status['totalOutput'])) {
            $this->SetValue('TotalOutput', floatval($status['totalOutput']));
        }

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

        // Ladelimits (Werte sind in Promille, durch 10 teilen für Prozent)
        if (isset($status['acChargingUpperLimit'])) {
            $chargingLimit = round($status['acChargingUpperLimit'] / 10);
            $this->SetValue('ChargingLimit', $chargingLimit);
        }
        if (isset($status['dischargeLowerLimit'])) {
            $dischargeLimit = round($status['dischargeLowerLimit'] / 10);
            $this->SetValue('DischargeLimit', $dischargeLimit);
        }
        if (isset($status['maximumChargingCurrent'])) {
            $this->SetValue('MaxChargingCurrent', intval($status['maximumChargingCurrent']));
        }
    }

    /**
     * Aktualisierungsintervall ändern
     */
    public function FBT_SetUpdateInterval(int $seconds)
    {
        if ($seconds < 10) {
            $this->LogMessage('Aktualisierungsintervall muss mindestens 10 Sekunden betragen', KL_WARNING);
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
    public function FBT_SetACOutput(bool $enabled)
    {
        $command = $enabled ? 'REGEnableACOutput' : 'REGDisableACOutput';
        return $this->SendDeviceCommand($command, null);
    }

    /**
     * DC-Ausgang ein-/ausschalten
     */
    public function FBT_SetDCOutput(bool $enabled)
    {
        $command = $enabled ? 'REGEnableDCOutput' : 'REGDisableDCOutput';
        return $this->SendDeviceCommand($command, null);
    }

    /**
     * USB-Ausgang ein-/ausschalten
     */
    public function FBT_SetUSBOutput(bool $enabled)
    {
        $command = $enabled ? 'REGEnableUSBOutput' : 'REGDisableUSBOutput';
        return $this->SendDeviceCommand($command, null);
    }

    /**
     * Ladelimit setzen (60-100%)
     */
    public function FBT_SetChargingLimit(int $percent)
    {
        if ($percent < 60 || $percent > 100) {
            $this->LogMessage('Ladelimit muss zwischen 60-100% liegen', KL_ERROR);
            return false;
        }
        
        $promille = $percent * 10; // Konvertierung zu Promille
        return $this->SendDeviceCommand('REGChargeUpperLimit', $promille);
    }

    /**
     * Maximalen Ladestrom setzen (1-20A)
     */
    public function FBT_SetMaxChargingCurrent(int $ampere)
    {
        if ($ampere < 1 || $ampere > 20) {
            $this->LogMessage('Ladestrom muss zwischen 1-20A liegen', KL_ERROR);
            return false;
        }
        
        return $this->SendDeviceCommand('REGMaxChargeCurrent', $ampere);
    }

    /**
     * Lade-Timer setzen (in Minuten)
     */
    public function FBT_SetChargeTimer(int $minutes)
    {
        if ($minutes < 0) {
            $this->LogMessage('Lade-Timer muss positiv sein', KL_ERROR);
            return false;
        }
        
        return $this->SendDeviceCommand('REGStopChargeAfter', $minutes);
    }

    /**
     * Entladelimit setzen (0-50%)
     */
    public function FBT_SetDischargeLimit(int $percent)
    {
        if ($percent < 0 || $percent > 50) {
            $this->LogMessage('Entladelimit muss zwischen 0-50% liegen', KL_ERROR);
            return false;
        }
        
        $promille = $percent * 10; // Konvertierung zu Promille
        return $this->SendDeviceCommand('REGDischargeLowerLimit', $promille);
    }


    /**
     * Geräteeinstellungen manuell anfordern
     */
    public function FBT_RequestSettings()
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        $credentials = $this->GetDiscoveryCredentials();

        if (empty($deviceId) || !$credentials) {
            $this->LogMessage('Gerät nicht konfiguriert', KL_ERROR);
            return false;
        }

        try {
            require_once __DIR__ . '/../libs/SydpowerClient.php';
            
            $client = new SydpowerClient($credentials['email'], $credentials['password']);
            $client->authenticate();
            
            $devices = $client->getDevices();
            $client->connectMqtt();
            
            return $this->SendDeviceCommand('REGRequestSettings', null);
            
        } catch (Exception $e) {
            $this->LogMessage('Fehler beim Anfordern der Einstellungen: ' . $e->getMessage(), KL_ERROR);
            return false;
        }
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
            require_once __DIR__ . '/../libs/SydpowerClient.php';
            
            $client = new SydpowerClient($credentials['email'], $credentials['password']);
            $client->clearTokenCache();
            
            $this->LogMessage('Token-Cache geleert - nächste Authentifizierung erfolgt neu', KL_NOTIFY);
            $this->SetValue('ConnectionStatus', 'Token-Cache geleert');
            
            return true;
            
        } catch (Exception $e) {
            $this->LogMessage('Fehler beim Leeren des Token-Cache: ' . $e->getMessage(), KL_ERROR);
            return false;
        }
    }

    /**
     * Befehl an das Gerät senden
     */
    private function SendDeviceCommand(string $command, $value)
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        $credentials = $this->GetDiscoveryCredentials();

        if (empty($deviceId) || !$credentials) {
            $this->LogMessage('Gerät nicht konfiguriert', KL_ERROR);
            return false;
        }

        try {
            require_once __DIR__ . '/../libs/SydpowerClient.php';
            
            $client = new SydpowerClient($credentials['email'], $credentials['password']);
            $client->authenticate();
            
            // Geräteliste laden (wichtig für MQTT-Verbindung)
            $devices = $client->getDevices();
            $client->connectMqtt();
            
            // Befehl senden
            $result = $client->sendCommand($deviceId, $command, $value);
            
            if ($result) {
                $valueText = $value !== null ? "($value)" : "";
                $this->LogMessage("$command$valueText gesendet", KL_DEBUG);
                
                // Nach Befehl kurz warten und Status aktualisieren
                sleep(2);
                $this->FBT_UpdateDeviceStatus();
            } else {
                $valueText = $value !== null ? "($value)" : "";
                $this->LogMessage("Fehler: $command$valueText", KL_ERROR);
            }
            
            $client->disconnect();
            return $result;
            
        } catch (Exception $e) {
            $this->LogMessage('Fehler beim Senden des Befehls: ' . $e->getMessage(), KL_ERROR);
            return false;
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