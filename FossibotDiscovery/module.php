<?php

/**
 * ABOUTME: Fossibot Discovery Modul für IP-Symcon
 * ABOUTME: Ermöglicht die Suche und Konfiguration von Fossibot Geräten
 */
class FossibotDiscovery extends IPSModuleStrict
{
    private $client = null;

    public function Create(): void
    {
        parent::Create();

        // Properties für Zugangsdaten
        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');

        // Status-Variable
        $this->RegisterVariableString('LastDiscovery', 'Letzte Suche', '', 1);
        $this->RegisterVariableInteger('DeviceCount', 'Gefundene Geräte', '', 2);
        $this->RegisterVariableString('ConfiguratorData', 'Configurator-Cache', '', 3);
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
        // Basis-Form laden - SCHNELL, keine API-Calls!
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Configurator-Daten aus Variable laden
        $configuratorValues = [];
        try {
            $deviceCountID = @$this->GetIDForIdent('DeviceCount');
            if ($deviceCountID !== false && GetValue($deviceCountID) > 0) {
                // Versuche gecachte Configurator-Daten zu laden
                $cachedDataID = @$this->GetIDForIdent('ConfiguratorData');
                if ($cachedDataID !== false) {
                    $cachedData = GetValue($cachedDataID);
                    if (!empty($cachedData)) {
                        $configuratorValues = json_decode($cachedData, true) ?? [];
                    }
                }
            }
        } catch (Exception $e) {
            // Ignoriere Fehler
        }

        // Standard IPSymcon Configurator hinzufügen
        $configuratorElement = [
            "type" => "Configurator",
            "name" => "DeviceConfigurator",
            "caption" => "Gefundene Fossibot-Geräte",
            "values" => $configuratorValues,
            "rowCount" => 10,
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
                    "width" => "auto",
                    "add" => 0
                ]
            ]
        ];

        $form["elements"][] = $configuratorElement;


        return json_encode($form);
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
                // Die Daten sind jetzt in ConfiguratorData gespeichert
                // IPSymcon wird das Formular neu laden und die neuen Daten anzeigen
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
     * Geräte suchen und Configurator-Array für Tabelle zurückgeben
     */
    public function FBD_DiscoverDevices(): array
    {
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

            $this->LogMessage(sprintf('🔍 Gefunden: %d Geräte', count($deviceIds)), KL_NOTIFY);

            // Configurator-Array für IPSymcon Tabelle erstellen
            $configuratorDevices = [];
            foreach ($devices as $deviceId => $device) {
                $cleanDeviceId = str_replace(':', '', $device['device_id'] ?? $deviceId);
                $deviceName = $device['device_name'] ?? $device['deviceName'] ?? 'Unbekanntes Gerät';
                
                $this->LogMessage(sprintf('📱 Gefunden: %s (ID: %s)', $deviceName, $cleanDeviceId), KL_NOTIFY);

                // Prüfen ob bereits eine Instanz existiert
                $instanceID = $this->findExistingInstance($cleanDeviceId);

                $configuratorDevices[] = [
                    "name" => $deviceName,
                    "deviceId" => $cleanDeviceId,
                    "instanceID" => $instanceID,
                    "create" => [
                        "moduleID" => "{58C595CB-5ABE-95CA-C1BC-26C5DBA45460}",
                        "configuration" => [
                            "DeviceID" => $cleanDeviceId
                        ],
                        "name" => $deviceName
                    ]
                ];
            }
            
            // Status-Variablen setzen
            $this->SetValue('DeviceCount', count($deviceIds));
            $this->SetValue('LastDiscovery', date('d.m.Y H:i:s'));
            
            // Configurator-Daten in Variable speichern
            $this->SetValue('ConfiguratorData', json_encode($configuratorDevices));

            $this->LogMessage(sprintf('🎯 %d Geräte gefunden, Tabelle wird angezeigt', count($deviceIds)), KL_NOTIFY);

            return $configuratorDevices;

        } catch (Exception $e) {
            $this->LogMessage('Fehler bei Gerätesuche: ' . $e->getMessage(), KL_ERROR);
            return [];
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
            try {
                $email = $this->ReadPropertyString('Email');
                $password = $this->ReadPropertyString('Password');
                
                $this->LogMessage("Debug: Email='{$email}' Password='".str_repeat('*', strlen($password))."'", KL_DEBUG);
                
                IPS_SetProperty($instanceID, 'Email', $email);
                IPS_SetProperty($instanceID, 'Password', $password);
            } catch (Exception $e) {
                $this->LogMessage('Fehler beim Lesen der Properties: ' . $e->getMessage(), KL_ERROR);
                throw $e;
            }

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
