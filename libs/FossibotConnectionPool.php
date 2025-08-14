<?php
/**
 * ABOUTME: Connection Pool Manager für Fossibot MQTT-Verbindungen
 * Verwaltet Connection-Reuse, Health-Checks und verhindert Race-Conditions
 */

class FossibotConnectionPool {
    private static $connections = array();
    private static $locks = array();
    
    /**
     * Holt eine gesunde Connection aus dem Pool oder erstellt eine neue
     */
    public static function getConnection($email, $password, $instanceId) {
        $key = md5($email . '_' . $instanceId);
        
        // Lock-Check - verhindert dass zwei Prozesse gleichzeitig die Connection nutzen
        if (isset(self::$locks[$key]) && self::$locks[$key] > time() - 5) {
            // Connection wird gerade verwendet - warten
            return self::waitForConnection($key, 5);
        }
        
        // Health-Check bestehender Connection
        if (isset(self::$connections[$key])) {
            $conn = self::$connections[$key];
            if (self::isConnectionHealthy($conn)) {
                self::$locks[$key] = time();
                self::$connections[$key]['lastUsed'] = time();
                self::$connections[$key]['reuses']++;
                IPS_LogMessage("FossibotPool", "Reusing connection (reuse #{$conn['reuses']})");
                return $conn['client'];
            } else {
                // Unhealthy - cleanup
                IPS_LogMessage("FossibotPool", "Connection unhealthy, creating new one");
                self::cleanupConnection($key);
            }
        }
        
        // Neue Connection erstellen
        return self::createConnection($email, $password, $key);
    }
    
    /**
     * Prüft ob eine Connection noch gesund ist
     */
    private static function isConnectionHealthy($conn) {
        if (!isset($conn['client']) || !$conn['client']) {
            return false;
        }
        
        // Alter Check - Connections über 25s sind suspect
        if ($conn['created'] < time() - 25) {
            return false;
        }
        
        // MQTT Connection Check
        try {
            if (method_exists($conn['client'], 'isHealthy')) {
                return $conn['client']->isHealthy();
            }
            // Fallback: Prüfe ob MQTT noch connected
            return $conn['client']->isMqttConnected();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Wartet auf eine freiwerdende Connection
     */
    private static function waitForConnection($key, $maxWaitSeconds) {
        $waited = 0;
        while ($waited < $maxWaitSeconds) {
            usleep(100000); // 100ms
            $waited += 0.1;
            
            if (!isset(self::$locks[$key]) || self::$locks[$key] < time() - 5) {
                // Lock ist frei geworden
                if (isset(self::$connections[$key]) && self::isConnectionHealthy(self::$connections[$key])) {
                    self::$locks[$key] = time();
                    return self::$connections[$key]['client'];
                }
                break;
            }
        }
        
        // Timeout - neue Connection erstellen
        IPS_LogMessage("FossibotPool", "Wait timeout, creating new connection");
        return self::createConnection('', '', $key . '_timeout');
    }
    
    /**
     * Erstellt eine neue Connection mit Caching
     */
    private static function createConnection($email, $password, $key) {
        require_once __DIR__ . '/SydpowerClient.php';
        
        $start = microtime(true);
        $client = new SydpowerClient($email, $password);
        
        // Token-Cache Check
        $tokenCacheKey = md5($email);
        if (isset(self::$connections[$tokenCacheKey]['token']) && 
            self::$connections[$tokenCacheKey]['tokenExpiry'] > time()) {
            // Reuse cached token
            $client->setAccessToken(self::$connections[$tokenCacheKey]['token']);
            $client->setMqttToken(self::$connections[$tokenCacheKey]['mqttToken']);
            IPS_LogMessage("FossibotPool", "Reusing cached token");
        } else {
            // Neue Authentication
            $authStart = microtime(true);
            $client->authenticate();
            $authTime = round((microtime(true) - $authStart) * 1000);
            
            // Token cachen
            self::$connections[$tokenCacheKey]['token'] = $client->getAccessToken();
            self::$connections[$tokenCacheKey]['mqttToken'] = $client->getMqttToken();
            self::$connections[$tokenCacheKey]['tokenExpiry'] = time() + 3600; // 1h
            
            IPS_LogMessage("FossibotPool", "New auth completed in {$authTime}ms");
        }
        
        // Device-Cache Check
        if (isset(self::$connections[$key]['devices']) && 
            self::$connections[$key]['devicesExpiry'] > time()) {
            // Reuse cached devices
            $client->setDevices(self::$connections[$key]['devices']);
            IPS_LogMessage("FossibotPool", "Reusing cached devices");
        } else {
            // Devices laden
            $devicesStart = microtime(true);
            $devices = $client->getDevices();
            $devicesTime = round((microtime(true) - $devicesStart) * 1000);
            
            // Devices cachen
            self::$connections[$key]['devices'] = $devices;
            self::$connections[$key]['devicesExpiry'] = time() + 3600; // 1h
            
            IPS_LogMessage("FossibotPool", "Loaded devices in {$devicesTime}ms");
        }
        
        // MQTT Connect
        $mqttStart = microtime(true);
        $client->connectMqtt();
        $mqttTime = round((microtime(true) - $mqttStart) * 1000);
        
        $totalTime = round((microtime(true) - $start) * 1000);
        IPS_LogMessage("FossibotPool", "New connection created in {$totalTime}ms (MQTT: {$mqttTime}ms)");
        
        // Connection speichern
        self::$connections[$key] = [
            'client' => $client,
            'created' => time(),
            'lastUsed' => time(),
            'reuses' => 0
        ];
        
        self::$locks[$key] = time();
        return $client;
    }
    
    /**
     * Gibt eine Connection frei
     */
    public static function releaseConnection($instanceId) {
        // Alle möglichen Keys durchgehen
        foreach (self::$locks as $key => $time) {
            if (strpos($key, $instanceId) !== false) {
                unset(self::$locks[$key]);
                IPS_LogMessage("FossibotPool", "Released connection lock for instance {$instanceId}");
            }
        }
    }
    
    /**
     * Räumt eine Connection auf
     */
    private static function cleanupConnection($key) {
        if (isset(self::$connections[$key])) {
            try {
                if (isset(self::$connections[$key]['client'])) {
                    self::$connections[$key]['client']->disconnect();
                }
            } catch (Exception $e) {
                // Ignore disconnect errors
            }
            unset(self::$connections[$key]);
            unset(self::$locks[$key]);
        }
    }
    
    /**
     * Räumt alte Connections auf
     */
    public static function cleanup() {
        $cleaned = 0;
        foreach (self::$connections as $key => $conn) {
            // Connections älter als 30s oder lange nicht benutzt
            if ($conn['created'] < time() - 30 || $conn['lastUsed'] < time() - 30) {
                self::cleanupConnection($key);
                $cleaned++;
            }
        }
        if ($cleaned > 0) {
            IPS_LogMessage("FossibotPool", "Cleaned up {$cleaned} old connections");
        }
    }
    
    /**
     * Gibt Pool-Statistiken zurück
     */
    public static function getStats() {
        $stats = [
            'total_connections' => count(self::$connections),
            'active_locks' => count(self::$locks),
            'connections' => []
        ];
        
        foreach (self::$connections as $key => $conn) {
            $stats['connections'][] = [
                'age' => time() - $conn['created'],
                'last_used' => time() - $conn['lastUsed'],
                'reuses' => $conn['reuses'],
                'locked' => isset(self::$locks[$key])
            ];
        }
        
        return $stats;
    }
    
    /**
     * Reset des gesamten Pools (für Debugging)
     */
    public static function reset() {
        foreach (self::$connections as $key => $conn) {
            self::cleanupConnection($key);
        }
        self::$connections = [];
        self::$locks = [];
        IPS_LogMessage("FossibotPool", "Connection pool reset");
    }
}