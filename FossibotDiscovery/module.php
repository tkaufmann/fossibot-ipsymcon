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

        // Status-Info hinzufügen falls verfügbar
        try {
            $deviceCountID = @$this->GetIDForIdent('DeviceCount');
            if ($deviceCountID !== false) {
                $deviceCount = GetValue($deviceCountID);
                if ($deviceCount > 0) {
                    // Zeige Info über gefundene Geräte
                    $lastDiscoveryID = @$this->GetIDForIdent('LastDiscovery');
                    $lastDiscovery = $lastDiscoveryID !== false ? GetValue($lastDiscoveryID) : 'Unbekannt';
                    
                    $infoElement = [
                        "type" => "Label",
                        "caption" => "✅ {$deviceCount} Geräte gefunden am {$lastDiscovery}. Instanzen wurden automatisch erstellt."
                    ];
                    $form["elements"][] = $infoElement;
                }
            }
        } catch (Exception $e) {
            // Ignoriere Fehler - Dialog soll trotzdem schnell öffnen
        }

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
     * Geräte suchen und automatisch Instanzen erstellen
     */
    public function FBD_DiscoverDevices(): bool
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

            // Zähler für automatisch erstellte Instanzen
            $createdInstances = 0;
            $existingInstances = 0;

            // Für jedes Gerät automatisch Instanz erstellen
            foreach ($devices as $deviceId => $device) {
                $cleanDeviceId = str_replace(':', '', $device['device_id'] ?? $deviceId);
                $deviceName = $device['device_name'] ?? $device['deviceName'] ?? 'Unbekanntes Gerät';
                
                $this->LogMessage(sprintf('📱 Verarbeite: %s (ID: %s)', $deviceName, $cleanDeviceId), KL_NOTIFY);

                // Prüfen ob bereits eine Instanz existiert
                $existingInstance = $this->findExistingInstance($cleanDeviceId);
                if ($existingInstance > 0) {
                    $this->LogMessage("✅ Instanz bereits vorhanden (ID: {$existingInstance})", KL_NOTIFY);
                    $existingInstances++;
                } else {
                    // Neue Instanz erstellen
                    $instanceID = $this->FBD_CreateDeviceInstance($cleanDeviceId, $deviceName);
                    if ($instanceID > 0) {
                        $this->LogMessage("🆕 Neue Instanz erstellt (ID: {$instanceID})", KL_NOTIFY);
                        $createdInstances++;
                    } else {
                        $this->LogMessage("❌ Fehler beim Erstellen der Instanz für {$deviceName}", KL_ERROR);
                    }
                }
            }
            
            // Status-Variablen setzen
            $this->SetValue('DeviceCount', count($deviceIds));
            $this->SetValue('LastDiscovery', date('d.m.Y H:i:s'));

            // Zusammenfassung ins Log
            $this->LogMessage(sprintf('🎯 Zusammenfassung: %d Geräte gefunden, %d neue Instanzen erstellt, %d bereits vorhanden', 
                count($deviceIds), $createdInstances, $existingInstances), KL_NOTIFY);

            if ($createdInstances > 0) {
                $this->LogMessage('✨ Neue Instanzen sind im Objektbaum verfügbar!', KL_NOTIFY);
            }

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
