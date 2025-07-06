# Fossibot IP-Symcon Module

Ein IP-Symcon Modul zur Überwachung und Steuerung von Fossibot Powerstations über die Sydpower API.

## 🔋 Unterstützte Geräte

- **Fossibot F2400** ✅ **Vollständig getestet und funktional**
- **Fossibot F3600 Pro** ⚠️ **Nicht getestet** - sollte theoretisch funktionieren

## 📋 Features

⚠️ **Wichtiger Hinweis**: Die Nutzung dieses Moduls kann dazu führen, dass du aus der Fossibot Mobile App ausgeloggt wirst, da nur eine aktive Session pro Account erlaubt ist.

### Monitoring
- **Echtzeit-Daten** alle 2 Minuten automatisch aktualisiert
- **Batteriezustand** (SOC) in Prozent
- **Eingangsleistung** (Solar/AC-Ladung)
- **Ausgangsleistung** (Aktuelle Verbrauchsleistung)
- **Output-Status** (AC/DC/USB Ausgänge An/Aus)
- **Ladelimits** (Obere/Untere Grenzwerte)
- **Ladestrom-Einstellungen**
- **Verbindungsstatus** und letzte Aktualisierung

### Konfiguration  
- **Zentrale Anmeldedaten** - Nur einmal in der Discovery-Instanz eingeben
- **Automatische Geräteerkennung** - "Geräte suchen" Button
- **Einfache Gerätekonfiguration** - Nur Geräte-ID erforderlich
- **Flexible Update-Intervalle** - Von 60 Sekunden bis 1 Stunde

### Steuerung
- **AC/DC/USB Ausgänge** - Ein-/Ausschalten über Buttons oder Skripte
- **Ladestrom-Steuerung** - 1A bis 20A in 5 Stufen (1A, 5A, 10A, 15A, 20A)
- **Ladelimit-Steuerung** - 60-100% in 5%-Schritten (9 Buttons)
- **Entladelimit-Steuerung** - 5-50% in 5%-Schritten (10 Buttons)
- **Erweiterte Funktionen** - Einstellungen anfordern und Status aktualisieren

### Integration
- **Vollständige IP-Symcon Integration** - Native Variablen und Profile
- **Smart Home Automatisierung** - Basierend auf Batterielevel, Verbrauch etc.
- **Webfront-kompatibel** - Übersichtliche Anzeige aller Werte
- **Event-driven** - Trigger für Automatisierungen verfügbar
- **Skript-Integration** - Alle Funktionen direkt in PHP-Skripten nutzbar

## 🚀 Installation

### 1. Modulbibliothek hinzufügen

1. **IP-Symcon Verwaltungskonsole** öffnen
2. **Bibliothek hinzufügen** → `https://github.com/tkaufmann/fossibot-ipsymcon`
3. **Oder per Git**: `git clone https://github.com/tkaufmann/fossibot-ipsymcon /var/lib/symcon/modules/Fossibot`
4. **Oder manuell**: Module nach `/var/lib/symcon/modules/Fossibot/` kopieren

### 2. Discovery-Instanz erstellen

1. **Instanz hinzufügen** → **Konfigurator** (Fossibot Discovery)
2. **E-Mail und Passwort** deines Fossibot-Accounts eingeben
3. **"Geräte suchen"** klicken
4. Notiere dir die **Geräte-ID** aus dem Log

### 3. Device-Instanz erstellen

1. **Instanz hinzufügen** → **Fossibot F2400** (oder F3600/Generic)
2. **Geräte-ID** eingeben (z.B. `7C2C67AB5F0E`)
3. **Update-Intervall** nach Bedarf anpassen (Standard: 120 Sekunden)
4. **Speichern** - Zugangsdaten werden automatisch übernommen

## 📊 Variablen-Übersicht

| Variable | Typ | Beschreibung | Einheit |
|----------|-----|--------------|---------|
| Ladezustand | Integer | Batterie-SOC | % |
| Eingangsleistung | Float | Solar/AC Input | W |
| Ausgangsleistung | Float | Aktueller Verbrauch | W |
| AC Ausgang | Boolean | AC-Ausgang Status | An/Aus |
| DC Ausgang | Boolean | DC-Ausgang Status | An/Aus |
| USB Ausgang | Boolean | USB-Ausgang Status | An/Aus |
| Ladelimit | Integer | Obere Ladegrenze | % |
| Entladelimit | Integer | Untere Entladegrenze | % |
| Max. Ladestrom | Integer | Maximaler Ladestrom | A |
| Letzte Aktualisierung | Integer | Timestamp | Unix-Zeit |
| Verbindungsstatus | String | Verbindungsinfo | Text |

## 🔧 Konfiguration

### Update-Intervall
- **Standard**: 120 Sekunden (2 Minuten) - Empfohlener Wert
- **Minimum**: 60 Sekunden (nur für Tests)
- **Empfohlen**: 120-300 Sekunden (Normalbetrieb)
- **Maximum**: 3600 Sekunden (1 Stunde)

⚠️ **Wichtiger Hinweis**: Häufige API-Aufrufe können dazu führen, dass du aus der Fossibot Mobile App ausgeloggt wirst, da nur eine aktive Session pro Account erlaubt ist.

### Steuerung über Buttons
- **AC/DC/USB Ein/Aus** - Direkte Ausgänge-Steuerung
- **Ladestrom** - 1A, 5A, 10A, 15A, 20A Buttons in einer Reihe
- **Ladelimit** - 60%, 65%, 70%, 75%, 80%, 85%, 90%, 95%, 100% Buttons
- **Entladelimit** - 5%, 10%, 15%, 20%, 25%, 30%, 35%, 40%, 45%, 50% Buttons
- **"Jetzt aktualisieren"** - Sofortige Datenabfrage
- **"Geräteinformationen"** - Debug-Informationen
- **"Einstellungen anfordern"** - Aktuelle Geräteeinstellungen abrufen

## 🔐 Sicherheit

- **Zugangsdaten verschlüsselt** in IP-Symcon Properties gespeichert
- **Token-Caching** - Minimiert API-Aufrufe (24h Gültigkeit)
- **Sichere MQTT-Verbindung** über WebSocket
- **Automatische Token-Erneuerung** bei Ablauf

## 🧪 Technische Details

### Architektur
- **Discovery-Modul** (Typ 4): Zentrale Anmeldung und Geräteerkennung
- **Device-Modul** (Typ 3): Individual Geräte-Monitoring
- **Fossibot-PHP Library**: API-Client für Sydpower-Backend
- **Token-Cache**: Optimierte Authentifizierung

### Kommunikation
- **HTTPS API**: Authentifizierung und Geräteabfrage
- **MQTT over WebSocket**: Echtzeit-Statusupdates
- **JWT Tokens**: 24-Stunden gültige Zugriffstoken
- **Modbus Protocol**: Gerätesteuerung über MQTT

### Debugging
- **Umfassendes Logging** auf verschiedenen Log-Leveln
- **Token-Debug-Info** zur Troubleshooting
- **Verbindungsstatus-Tracking**
- **MQTT-Message-Debugging**

## 🐛 Bekannte Probleme & Lösungen

### "Keine Statusdaten empfangen"
- **Ursache**: MQTT-Timeout oder Verbindungsproblem
- **Lösung**: Update-Intervall erhöhen, "Jetzt aktualisieren" verwenden

### Token-Invalidierung / App-Logout
- **Ursache**: Fossibot Mobile App und IP-Symcon teilen sich einen Account - nur eine aktive Session möglich
- **Symptom**: Wirst aus der Mobile App ausgeloggt, wenn IP-Symcon Updates abruft
- **Lösung**: 
  - **Update-Intervall erhöhen** (empfohlen: 2-5 Minuten)
  - **Mobile App weniger nutzen** während IP-Symcon aktiv ist
  - **Automatische Token-Erneuerung** funktioniert transparent

### Falsche Output-Status
- **Ursache**: Bit-Assignments waren in v1.0 falsch
- **Lösung**: ✅ Behoben in aktueller Version (korrekte Bit-Zuordnung)

## 🎮 Steuerung über Skripte

Alle Funktionen können direkt in IP-Symcon PHP-Skripten verwendet werden:

### Verfügbare Befehle

```php
// Instanz-ID deiner FossibotDevice-Instanz
$fossibotID = 12345; // Ersetze mit deiner echten ID

// === AUSGÄNGE STEUERN ===
FBT_SetACOutput($fossibotID, true);   // AC-Ausgang einschalten
FBT_SetACOutput($fossibotID, false);  // AC-Ausgang ausschalten

FBT_SetDCOutput($fossibotID, true);   // DC-Ausgang einschalten  
FBT_SetDCOutput($fossibotID, false);  // DC-Ausgang ausschalten

FBT_SetUSBOutput($fossibotID, true);  // USB-Ausgang einschalten
FBT_SetUSBOutput($fossibotID, false); // USB-Ausgang ausschalten

// === LADEPARAMETER ===
FBT_SetMaxChargingCurrent($fossibotID, 1);   // Ladestrom: 1A (minimal)
FBT_SetMaxChargingCurrent($fossibotID, 5);   // Ladestrom: 5A
FBT_SetMaxChargingCurrent($fossibotID, 10);  // Ladestrom: 10A (normal)
FBT_SetMaxChargingCurrent($fossibotID, 15);  // Ladestrom: 15A  
FBT_SetMaxChargingCurrent($fossibotID, 20);  // Ladestrom: 20A (maximum)

FBT_SetChargingLimit($fossibotID, 80);   // Ladelimit: 80% (60-100%)
FBT_SetChargingLimit($fossibotID, 90);   // Ladelimit: 90%
FBT_SetChargingLimit($fossibotID, 100);  // Ladelimit: 100%

FBT_SetDischargeLimit($fossibotID, 20);  // Entladelimit: 20% (5-50%)
FBT_SetDischargeLimit($fossibotID, 30);  // Entladelimit: 30%

FBT_SetChargeTimer($fossibotID, 60);     // Lade-Timer: 60 Minuten

// === STATUS & UPDATES ===
FBT_UpdateDeviceStatus($fossibotID);     // Status manuell aktualisieren
FBT_RequestSettings($fossibotID);        // Einstellungen anfordern
FBT_SetUpdateInterval($fossibotID, 300); // Update-Intervall: 5 Minuten
FBT_RefreshNow($fossibotID);             // Sofort aktualisieren
FBT_GetDeviceInfo($fossibotID);          // Geräteinformationen
```

### Praktische Beispiele

**Zeitgesteuertes Laden (Ablaufplan):**
```php
// Nachts: Eco-Modus (minimales Laden)
FBT_SetMaxChargingCurrent($fossibotID, 1);
FBT_SetChargingLimit($fossibotID, 80);

// Tags: Normal-Modus
FBT_SetMaxChargingCurrent($fossibotID, 10);
FBT_SetChargingLimit($fossibotID, 100);
```

**Solar-Überschuss-Steuerung:**
```php
// Bei hoher PV-Leistung: Vollgas laden
$pvPower = GetValue($pvInstanceID);
if ($pvPower > 1000) {
    FBT_SetMaxChargingCurrent($fossibotID, 20);
    FBT_SetChargingLimit($fossibotID, 100);
}
```

**Batterie-Level Management:**
```php
// Aktuellen SOC prüfen
$soc = GetValue(IPS_GetObjectIDByIdent('BatterySOC', $fossibotID));

if ($soc < 20) {
    // Notladung aktivieren
    FBT_SetMaxChargingCurrent($fossibotID, 20);
    FBT_SetChargingLimit($fossibotID, 100);
} elseif ($soc > 95) {
    // Erhaltungsladung
    FBT_SetMaxChargingCurrent($fossibotID, 1);
}
```

**Strompreis-Optimierung:**
```php
// Bei niedrigen Strompreisen (z.B. nachts)
if ($strompreis < 0.20) {
    FBT_SetMaxChargingCurrent($fossibotID, 15);
} else {
    FBT_SetMaxChargingCurrent($fossibotID, 1);
}
```

**Geräte basierend auf Batterielevel steuern:**
```php
$soc = GetValue(IPS_GetObjectIDByIdent('BatterySOC', $fossibotID));

if ($soc < 30) {
    // Stromsparmodus: Nur AC für wichtige Geräte
    FBT_SetUSBOutput($fossibotID, false);
    FBT_SetDCOutput($fossibotID, false);
} else {
    // Normalmodus: Alle Ausgänge an
    FBT_SetUSBOutput($fossibotID, true);
    FBT_SetDCOutput($fossibotID, true);
}
```

### Instanz-ID ermitteln

```php
// Per Objektbaum: Rechtsklick auf FossibotDevice → Eigenschaften → ID

// Oder automatisch suchen:
$instances = IPS_GetInstanceListByModuleID('{DEINE-FOSSIBOT-MODUL-GUID}');
$fossibotID = $instances[0]; // Erste gefundene Instanz
```

## 🔄 Changelog

### v1.4 - Aktuell  
- ✅ **App-konforme Limits** - Ladelimit 60-100%, Entladelimit 5-50%
- ✅ **Erweiterte Button-Arrays** - 9 Ladelimit + 10 Entladelimit Buttons
- ✅ **Validierung angepasst** - Sichere Bereiche wie in Fossibot App
- ✅ **Vollständige Batterie-Kontrolle** - Lade- und Entladeparameter

### v1.3
- ✅ **Entladelimit-Steuerung** - Neue FBT_SetDischargeLimit() Funktion  
- ✅ **REGDischargeLowerLimit** - Modbus-Befehl implementiert
- ✅ **Erweiterte Ladelimits** - 50-100% in 5%-Schritten

### v1.2
- ✅ **Vollständige Steuerung** - AC/DC/USB Ausgänge schaltbar
- ✅ **Ladeparameter-Steuerung** - Ladestrom (1-20A) und Ladelimit (80-100%)
- ✅ **Skript-Integration** - Alle Funktionen in PHP-Skripten nutzbar
- ✅ **Optimiertes Button-Layout** - Übersichtliche Reihen-Anordnung
- ✅ **Reduziertes Logging** - Weniger Spam, nur relevante Meldungen

### v1.1
- ✅ **Korrekte Output-Bit-Zuordnung** für F2400
- ✅ **Automatische Timer-Updates** funktional
- ✅ **Verbesserte Token-Wiederverwendung**
- ✅ **Zentrale Zugangsdaten-Verwaltung**
- ✅ **Umfassendes Debug-System**

### v1.0 - Initial Release  
- ⚠️ Falsche AC/DC/USB Bit-Assignments
- ⚠️ Timer-Updates nicht funktional

## 🤝 Contributing

### Neue Geräte testen
Falls du einen **F3600 Pro** oder andere Fossibot-Geräte hast:

1. **Teste das Modul** und dokumentiere die Ergebnisse
2. **Bit-Pattern analysieren** falls Output-Status falsch
3. **Issues erstellen** auf GitHub mit Debug-Logs
4. **Pull Requests** für Verbesserungen willkommen

### Bug Reports
- **Debug-Logs** aus IP-Symcon Meldungen anhängen
- **Gerätemodell** und Firmware-Version angeben
- **Schritte zur Reproduktion** beschreiben

## 📞 Support

- **GitHub Issues**: https://github.com/tkaufmann/fossibot-ipsymcon/issues
- **IP-Symcon Community**: https://community.symcon.de
- **Fossibot-PHP Library**: https://github.com/tkaufmann/fossibot-php

## 📜 License

MIT License - Siehe LICENSE Datei für Details.

## 🙏 Credits

- **Fossibot-PHP Library**: Basis-API-Client für Sydpower
- **IP-Symcon Community**: Unterstützung und Testing
- **Reverse Engineering**: Sydpower API Protokoll-Analyse

---

⚡ **Powered by Fossibot F2400** - Getestet mit echten Geräten für maximale Zuverlässigkeit!