<?php
/**
 * ABOUTME: Semaphore Manager für Thread-sichere Fossibot Operationen
 * Verhindert Race-Conditions zwischen Timer-Updates und manuellen Commands
 */

class FossibotSemaphore {
    
    private static $activeSemaphores = array();
    private static $statistics = array(
        'acquired' => 0,
        'released' => 0,
        'timeouts' => 0,
        'conflicts' => 0
    );
    
    /**
     * Versucht ein Semaphore zu erwerben
     */
    public static function acquire(string $resource, int $timeoutMs = 5000): bool {
        $key = "Fossibot_" . $resource;
        $startTime = microtime(true);
        
        // Log attempt
        IPS_LogMessage("FossibotSemaphore", "Attempting to acquire {$resource}");
        
        // Try to acquire with exponential backoff
        $attempts = 0;
        $backoffMs = 10; // Start with 10ms
        
        while ((microtime(true) - $startTime) * 1000 < $timeoutMs) {
            $attempts++;
            
            if (IPS_SemaphoreEnter($key, 10)) {
                // Success!
                $waitedMs = round((microtime(true) - $startTime) * 1000);
                
                self::$activeSemaphores[$resource] = [
                    'key' => $key,
                    'acquired' => microtime(true),
                    'pid' => getmypid(),
                    'attempts' => $attempts
                ];
                
                self::$statistics['acquired']++;
                
                if ($waitedMs > 100) {
                    IPS_LogMessage("FossibotSemaphore", 
                        "✅ Acquired {$resource} after {$waitedMs}ms ({$attempts} attempts)");
                }
                
                return true;
            }
            
            // Someone else has it
            if ($attempts == 1) {
                self::$statistics['conflicts']++;
                IPS_LogMessage("FossibotSemaphore", "⚠️ {$resource} is locked, waiting...");
            }
            
            // Exponential backoff sleep
            usleep($backoffMs * 1000);
            $backoffMs = min($backoffMs * 2, 500); // Max 500ms between attempts
        }
        
        // Timeout
        self::$statistics['timeouts']++;
        $elapsedMs = round((microtime(true) - $startTime) * 1000);
        IPS_LogMessage("FossibotSemaphore", 
            "❌ Failed to acquire {$resource} after {$elapsedMs}ms ({$attempts} attempts)");
        
        return false;
    }
    
    /**
     * Versucht ein Semaphore zu erwerben (non-blocking)
     */
    public static function tryAcquire(string $resource): bool {
        $key = "Fossibot_" . $resource;
        
        if (IPS_SemaphoreEnter($key, 10)) {
            self::$activeSemaphores[$resource] = [
                'key' => $key,
                'acquired' => microtime(true),
                'pid' => getmypid(),
                'attempts' => 1
            ];
            
            self::$statistics['acquired']++;
            return true;
        }
        
        self::$statistics['conflicts']++;
        return false;
    }
    
    /**
     * Gibt ein Semaphore frei
     */
    public static function release(string $resource): bool {
        if (!isset(self::$activeSemaphores[$resource])) {
            IPS_LogMessage("FossibotSemaphore", "⚠️ Trying to release non-acquired semaphore: {$resource}");
            return false;
        }
        
        $sem = self::$activeSemaphores[$resource];
        $heldMs = round((microtime(true) - $sem['acquired']) * 1000);
        
        // Release the semaphore
        IPS_SemaphoreLeave($sem['key']);
        
        self::$statistics['released']++;
        
        // Log if held for long time
        if ($heldMs > 1000) {
            IPS_LogMessage("FossibotSemaphore", 
                "⚠️ Released {$resource} after {$heldMs}ms (long hold time!)");
        } elseif ($heldMs > 100) {
            IPS_LogMessage("FossibotSemaphore", 
                "Released {$resource} after {$heldMs}ms");
        }
        
        unset(self::$activeSemaphores[$resource]);
        return true;
    }
    
    /**
     * Gibt alle aktiven Semaphores frei (für Cleanup)
     */
    public static function releaseAll(): void {
        $count = count(self::$activeSemaphores);
        
        if ($count > 0) {
            IPS_LogMessage("FossibotSemaphore", "Releasing {$count} active semaphores");
        }
        
        foreach (self::$activeSemaphores as $resource => $sem) {
            IPS_SemaphoreLeave($sem['key']);
            self::$statistics['released']++;
        }
        
        self::$activeSemaphores = [];
    }
    
    /**
     * Führt eine Funktion mit Semaphore-Schutz aus
     */
    public static function withLock(string $resource, callable $callback, int $timeoutMs = 5000) {
        if (!self::acquire($resource, $timeoutMs)) {
            return false;
        }
        
        try {
            $result = $callback();
            return $result;
        } catch (Exception $e) {
            IPS_LogMessage("FossibotSemaphore", "Error in locked section: " . $e->getMessage());
            throw $e;
        } finally {
            self::release($resource);
        }
    }
    
    /**
     * Prüft ob ein Semaphore aktiv ist
     */
    public static function isLocked(string $resource): bool {
        return isset(self::$activeSemaphores[$resource]);
    }
    
    /**
     * Gibt Statistiken zurück
     */
    public static function getStatistics(): array {
        $stats = self::$statistics;
        $stats['active'] = count(self::$activeSemaphores);
        
        // Details zu aktiven Semaphores
        $stats['active_details'] = [];
        foreach (self::$activeSemaphores as $resource => $sem) {
            $stats['active_details'][] = [
                'resource' => $resource,
                'held_ms' => round((microtime(true) - $sem['acquired']) * 1000),
                'attempts' => $sem['attempts']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Reset Statistiken
     */
    public static function resetStatistics(): void {
        self::$statistics = [
            'acquired' => 0,
            'released' => 0,
            'timeouts' => 0,
            'conflicts' => 0
        ];
    }
    
    /**
     * Cleanup alte/stuck Semaphores
     */
    public static function cleanup(int $maxAgeSeconds = 60): int {
        $cleaned = 0;
        $now = microtime(true);
        
        foreach (self::$activeSemaphores as $resource => $sem) {
            $age = $now - $sem['acquired'];
            
            if ($age > $maxAgeSeconds) {
                IPS_LogMessage("FossibotSemaphore", 
                    "⚠️ Force-releasing stuck semaphore {$resource} (age: " . round($age) . "s)");
                
                IPS_SemaphoreLeave($sem['key']);
                unset(self::$activeSemaphores[$resource]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            IPS_LogMessage("FossibotSemaphore", "Cleaned up {$cleaned} stuck semaphores");
        }
        
        return $cleaned;
    }
}