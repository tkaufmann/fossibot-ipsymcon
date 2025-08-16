<?php

/**
 * ABOUTME: Fossibot Discovery Modul für IP-Symcon
 * ABOUTME: Ermöglicht die Suche und Konfiguration von Fossibot Geräten
 */
class FossibotDiscovery extends IPSModuleStrict
{
    private $client = null;
    private $cachedDevices = null;
    
    public function Create(): void
    {
        parent::Create();

        // Properties für Zugangsdaten
        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');
        
        // Status-Variable
        $this->RegisterVariableString('LastDiscovery', 'Letzte Suche', '', 1);
        $this->RegisterVariableInteger('DeviceCount', 'Gefundene Geräte', '', 2);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        
        // Status setzen
        if ($this->ReadPropertyString('Email') == '' || $this->ReadPropertyString('Password') == '') {
            $this->SetStatus(201); // Inaktiv - Konfiguration erforderlich
        } else {
            $this->SetStatus(102); // Aktiv
        }
    }

    /**
     * Konfigurationsformular - verwende statische form.json
     */
    public function GetConfigurationForm(): string
    {
        // Basis-Form laden
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        
        // Nur Geräte anzeigen, wenn explizit gesucht wurde
        $discoveredDevices = [];
        
        try {
            $lastDiscoveryID = @$this->GetIDForIdent('LastDiscovery');
            if ($lastDiscoveryID !== false) {
                $lastDiscovery = GetValue($lastDiscoveryID);
                if (!empty($lastDiscovery)) {
                    // Nur wenn schon eine Suche stattgefunden hat, lade die Geräte
                    $discoveredDevices = $this->getDiscoveredDevices();
                }
            }
        } catch (Exception $e) {
            // Wenn Variable nicht existiert oder anderer Fehler - keine Geräte laden
            $discoveredDevices = [];
        }
        
        if (!empty($discoveredDevices)) {
            // Configurator-Element für gefundene Geräte hinzufügen
            $configuratorElement = [
                "type" => "Configurator",
                "name" => "DeviceConfigurator", 
                "caption" => "Gefundene Fossibot-Geräte",
                "rowCount" => min(count($discoveredDevices), 8),
                "add" => false,
                "delete" => false,
                "sort" => [
                    "column" => "name",
                    "direction" => "ascending"
                ],
                "columns" => [
                    [
                        "caption" => "Gerätename",
                        "name" => "name", 
                        "width" => "250px",
                        "add" => ""
                    ],
                    [
                        "caption" => "Geräte-ID",
                        "name" => "deviceId",
                        "width" => "200px", 
                        "add" => ""
                    ],
                    [
                        "caption" => "Status",
                        "name" => "instanceID",
                        "width" => "120px",
                        "add" => 0
                    ]
                ],
                "values" => $discoveredDevices
            ];
            
            $form["elements"][] = $configuratorElement;
        }
        
        return json_encode($form);
    }
    
    /**
     * Gefundene Geräte für Configurator aufbereiten
     */
    private function getDiscoveredDevices(): array
    {
        // Verwende gecachte Daten wenn verfügbar (für GetConfigurationForm)
        if ($this->cachedDevices !== null) {
            return $this->cachedDevices;
        }
        
        // Prüfe ob überhaupt Geräte gefunden wurden
        try {
            $foundDevicesID = @$this->GetIDForIdent('FoundDevices');
            if ($foundDevicesID === false) {
                return []; // Variable existiert noch nicht
            }
            $foundDevicesCount = GetValue($foundDevicesID);
            if ($foundDevicesCount == 0) {
                return [];
            }
        } catch (Exception $e) {
            return [];
        }
        
        // API-Aufruf nur wenn nicht gecacht
        try {
            $client = $this->getClient();
            $devices = $client->getDevices();
            
            // Falls noch keine Geräte gefunden wurden
            if (empty($devices)) {
                return [];
            }
            
            $configuratorDevices = [];
            foreach ($devices as $deviceId => $device) {
                // SydpowerClient gibt device_id und device_name zurück
                $cleanDeviceId = str_replace(':', '', $device['device_id'] ?? $deviceId);
                $deviceName = $device['device_name'] ?? $device['deviceName'] ?? 'Unbekanntes Gerät';
                
                // Prüfen ob bereits eine Instanz für dieses Gerät existiert
                $instanceID = $this->findExistingInstance($cleanDeviceId);
                
                $configuratorDevices[] = [
                    "name" => $deviceName,
                    "deviceId" => $cleanDeviceId,
                    "instanceID" => $instanceID,
                    "create" => [
                        "moduleID" => "{58C595CB-5ABE-95CA-C1BC-26C5DBA45460}", // FossibotDevice GUID
                        "configuration" => [
                            "DeviceID" => $cleanDeviceId,
                            "DeviceName" => $deviceName
                        ]
                    ]
                ];
            }
            
            // Cache für nachfolgende GetConfigurationForm-Aufrufe
            $this->cachedDevices = $configuratorDevices;
            
            return $configuratorDevices;
            
        } catch (Exception $e) {
            $this->LogMessage('Fehler beim Laden der Geräteliste: ' . $e->getMessage(), KL_ERROR);
            return [];
        }
    }
    
    /**
     * Prüfen ob bereits eine Instanz für ein Gerät existiert
     */
    private function findExistingInstance(string $deviceId): int
    {
        $instances = IPS_GetInstanceListByModuleID("{58C595CB-5ABE-95CA-C1BC-26C5DBA45460}");
        foreach ($instances as $instanceID) {
            $deviceID = IPS_GetProperty($instanceID, 'DeviceID');
            if ($deviceID === $deviceId) {
                return $instanceID;
            }
        }
        return 0;
    }

    /**
     * RequestAction Handler für Button-Aktionen
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'DiscoverDevices':
                $this->FBD_DiscoverDevices();
                break;
            default:
                parent::RequestAction($Ident, $Value);
                break;
        }
    }

    /**
     * Fossibot Client initialisieren (wiederverwendbar)
     */
    private function getClient()
    {
        if ($this->client === null) {
            $email = $this->ReadPropertyString('Email');
            $password = $this->ReadPropertyString('Password');
            
            if (empty($email) || empty($password)) {
                throw new Exception('Email und Passwort müssen konfiguriert werden');
            }
            
            require_once __DIR__ . '/../libs/SydpowerClient.php';
            $this->client = new SydpowerClient($email, $password);
        }
        
        return $this->client;
    }

    /**
     * Geräte suchen und konfigurieren
     */
    public function FBD_DiscoverDevices(): bool
    {
        // Cache leeren für neue Suche
        $this->cachedDevices = null;
        
        try {
            $client = $this->getClient();
            
            // Debug Token-Status VOR Authentifizierung
            $debugInfo = $client->getTokenDebugInfo();
            if ($debugInfo['has_cached_tokens']) {
                $this->LogMessage('✅ Verwende gecachte Tokens (Alter: ' . $debugInfo['cache_age_minutes'] . ' Min)', KL_NOTIFY);
            } else {
                $this->LogMessage('🔄 Keine gültigen Tokens im Cache, authentifiziere neu', KL_NOTIFY);
            }
            
            // Authentifizierung (Client entscheidet selbst ob nötig)
            $client->authenticate();
            
            // Debug Token-Status NACH Authentifizierung
            $debugInfoAfter = $client->getTokenDebugInfo();
            $this->LogMessage('Token-Debug: ' . json_encode($debugInfoAfter), KL_DEBUG);
            
            $devices = $client->getDevices();
            $deviceIds = $client->getDeviceIds();
            
            $this->LogMessage(sprintf('Gefunden: %d Geräte', count($deviceIds)), KL_NOTIFY);
            
            // Geräte-Details ins Log schreiben
            foreach ($deviceIds as $i => $deviceId) {
                $deviceName = $devices[$i]['deviceName'] ?? 'Unbekannt';
                $this->LogMessage(sprintf('📱 Gerät %d: %s', $i+1, $deviceName), KL_NOTIFY);
                $this->LogMessage(sprintf('🔑 Geräte-ID: %s', $deviceId), KL_NOTIFY);
            }
            
            $this->SetValue('DeviceCount', count($deviceIds));
            $this->SetValue('LastDiscovery', date('d.m.Y H:i:s'));
            
            return true;
            
        } catch (Exception $e) {
            $this->LogMessage('Fehler bei Gerätesuche: ' . $e->getMessage(), KL_ERROR);
            return false;
        }
    }

    /**
     * Instanz für Gerät erstellen
     */
    public function FBD_CreateDeviceInstance(string $deviceId, string $deviceName): int
    {
        // Prüfen ob Instanz bereits existiert
        $existingInstance = $this->GetInstanceByDeviceID($deviceId);
        if ($existingInstance) {
            $this->LogMessage('Instanz für Gerät bereits vorhanden: ' . $deviceName, KL_NOTIFY);
            return $existingInstance;
        }

        // Neue Instanz erstellen
        $moduleID = '{58C595CB-5ABE-95CA-C1BC-26C5DBA45460}'; // FossibotDevice GUID
        $instanceID = IPS_CreateInstance($moduleID);
        
        if ($instanceID) {
            IPS_SetName($instanceID, $deviceName);
            IPS_SetProperty($instanceID, 'DeviceID', $deviceId);
            
            // Zugangsdaten an Device-Instanz weitergeben
            IPS_SetProperty($instanceID, 'Email', $this->ReadPropertyString('Email'));
            IPS_SetProperty($instanceID, 'Password', $this->ReadPropertyString('Password'));
            
            IPS_ApplyChanges($instanceID);
            
            $this->LogMessage('Instanz erstellt für: ' . $deviceName . ' (ID: ' . $instanceID . ')', KL_NOTIFY);
            return $instanceID;
        }
        
        return false;
    }

    /**
     * Prüft ob bereits eine Instanz für eine Geräte-ID existiert
     */
    private function GetInstanceByDeviceID(string $deviceId)
    {
        $moduleID = '{58C595CB-5ABE-95CA-C1BC-26C5DBA45460}'; // FossibotDevice GUID
        $instances = IPS_GetInstanceListByModuleID($moduleID);
        
        foreach ($instances as $instanceID) {
            $configuredDeviceID = IPS_GetProperty($instanceID, 'DeviceID');
            if ($configuredDeviceID === $deviceId) {
                return $instanceID;
            }
        }
        
        return false;
    }
}