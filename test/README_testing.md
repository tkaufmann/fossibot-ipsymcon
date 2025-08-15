# Fossibot Command Testing

Dieses Verzeichnis enthält Test-Skripte zur Validierung der Fossibot FBT_*-Funktionen.

## 🎯 Überblick

Das Test-System nutzt **Event-basierte Validierung** statt fester Wartezeiten:
- Events werden temporär auf Variablen registriert
- Kommando wird ausgeführt
- System wartet auf Variable-Änderung (mit Timeout)
- Exakte Timing-Messung der API-Response

## 📁 Dateien

- **`test_fossibot_commands.php`**: Haupttest-Skript für alle FBT_*-Funktionen
- **`event_test_helper.php`**: Event-Management-Bibliothek  
- **`README_testing.md`**: Diese Anleitung

## 🚀 Test ausführen

### 1. Vorbereitung

1. **Instance ID ermitteln**: 
   - In IP-Symcon → Objektbaum → FossibotDevice-Instanz
   - Rechtsklick → Eigenschaften → ID notieren (z.B. 12345)

2. **Test-Skript konfigurieren**:
   ```php
   // In test_fossibot_commands.php Zeile 279:
   $FOSSIBOT_INSTANCE_ID = 12345; // <<<< DEINE INSTANCE ID HIER
   ```

### 2. Test starten

**In IP-Symcon-Script ausführen:**
```php
// test_fossibot_commands.php komplett kopieren und als Script ausführen
// ODER Include verwenden:
include '/pfad/zu/fossibot/test/test_fossibot_commands.php';
```

### 3. Log-Monitoring per SSH

**Während der Test läuft** kannst du das Log live verfolgen:
```bash
ssh benutzer@ip-symcon-host "tail -f /tmp/fossibot_test.log"
```

## 📊 Test-Kategorien

### Output-Commands (Timeout: 10s)
- `FBT_SetACOutput(true/false)` → Variable: `ACOutput`
- `FBT_SetDCOutput(true/false)` → Variable: `DCOutput`  
- `FBT_SetUSBOutput(true/false)` → Variable: `USBOutput`

**Erwartung**: Schnelle Response durch Response-Validierung

### Value-Settings (Timeout: 60s)
- `FBT_SetMaxChargingCurrent(1-5)` → Variable: `MaxChargingCurrent`
- `FBT_SetChargingLimit(60-100)` → Variable: `ChargingLimit`
- `FBT_SetDischargeLimit(0-50)` → Variable: `DischargeLimit`

**Erwartung**: Langsamere Response durch Timer-System

### Status-Functions
- `FBT_UpdateDeviceStatus()` → Return-Value-Test
- `FBT_RequestSettings()` → Return-Value-Test  
- `FBT_GetDeviceInfo()` → Return-Value-Test

**Erwartung**: Sofortige Return-Values, keine Event-Validierung

## 🔄 Test-Ablauf

1. **Originalzustand erfassen** (alle Variablenwerte)
2. **Output-Commands testen** (6 Tests)
3. **Value-Settings testen** (6 Tests)  
4. **Status-Functions testen** (3 Tests)
5. **Originalzustand wiederherstellen**
6. **Test-Report generieren**

## 📈 Log-Format

```
[2025-01-15 14:30:15] === FOSSIBOT COMMAND TESTER GESTARTET ===
[2025-01-15 14:30:15] Instance ID: 12345
[2025-01-15 14:30:15] === ORIGINALZUSTAND ERFASSEN ===
[2025-01-15 14:30:15] Original ACOutput: 1
[2025-01-15 14:30:16] --- TEST: FBT_SetACOutput(false) ---
[2025-01-15 14:30:16] Event erstellt: ID=67890 für Variable 'ACOutput'
[2025-01-15 14:30:16] KOMMANDO AUSFÜHREN für Variable 'ACOutput'
[2025-01-15 14:30:16] WARTE auf Event 67890 für 'ACOutput' (max. 10s)
[2025-01-15 14:30:18] EVENT GEFEUERT: 'ACOutput' nach 2347.5ms - Wert: 1 → 0
[2025-01-15 14:30:18] TEST ERFOLGREICH: 'ACOutput' in 2347.5ms
[2025-01-15 14:30:18] ✅ FBT_SetACOutput(false): SUCCESS
```

## 📋 Test-Report

Am Ende zeigt das System:
- **Anzahl Tests**: Gesamt / Erfolgreich
- **Erfolgsrate**: Prozent
- **Timing-Statistiken**: Durchschnitt, Min, Max
- **Detaillierte Timings**: Pro Kommando

Beispiel:
```
=== TEST-REPORT ===
Tests insgesamt: 15
Tests erfolgreich: 14
Erfolgsrate: 93.3%
Timing-Statistiken:
  Durchschnitt: 4832.1ms
  Minimum: 1203.2ms  
  Maximum: 23456.7ms
Detaillierte Timings:
  FBT_SetACOutput(true): 2347.5ms
  FBT_SetMaxChargingCurrent(3): 23456.7ms
  ...
```

## 🔧 Anpassungen

### Timeouts ändern
```php
// In testOutputCommands():
$this->testSingleCommand('ACOutput', $func, 'Command', 15); // 15s statt 10s

// In testValueSettings():  
$this->testSingleCommand('ChargingLimit', $func, 'Command', 90); // 90s statt 60s
```

### Zusätzliche Tests
```php
// Neue Test-Kategorie hinzufügen:
private function testCustomCommands(): void {
    $this->testSingleCommand(
        'VariableIdent',
        function() { return FBT_CustomFunction($this->fossibotID, $param); },
        'FBT_CustomFunction(param)',
        30
    );
}
```

### Log-Pfad ändern
```php
// In event_test_helper.php:
private static $logFile = '/var/log/fossibot_test.log'; // Anderer Pfad
```

## ⚠️ Wichtige Hinweise

1. **Originalzustand**: Tests stellen automatisch den ursprünglichen Zustand wieder her
2. **Wartezeiten**: Zwischen Tests sind 2s Pause eingeplant
3. **Instance Validation**: Script prüft ob die angegebene ID eine FossibotDevice-Instanz ist
4. **Event Cleanup**: Temporäre Events werden automatisch gelöscht
5. **Verbindung**: F2400 muss online und verbunden sein

## 🐛 Troubleshooting

### "Instance ID existiert nicht"
→ Korrekte FossibotDevice Instance ID in Script setzen

### "Variable nicht gefunden"  
→ Modul-Version überprüfen, evtl. Variable-Namen geändert

### "Event feuert nicht" / Timeouts
→ F2400-Verbindung prüfen, API könnte offline sein

### SSH-Zugriff zum Log
→ Je nach IP-Symcon Installation:
```bash
# SymBox
ssh admin@ip-symcon-ip "tail -f /tmp/fossibot_test.log"

# Linux Installation  
ssh user@ip-symcon-ip "sudo tail -f /tmp/fossibot_test.log"

# Docker
docker exec -it symcon tail -f /tmp/fossibot_test.log
```

## 📞 Support

Bei Problemen:
1. **Komplettes Log** aus `/tmp/fossibot_test.log` sammeln
2. **IP-Symcon Messages** prüfen  
3. **Instance Status** der FossibotDevice-Instanz prüfen
4. **GitHub Issue** erstellen mit Log-Daten