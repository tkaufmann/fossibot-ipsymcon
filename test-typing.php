<?php
/**
 * ABOUTME: Comprehensive typing test for Fossibot classes
 * ABOUTME: Tests all typed methods without real API communication
 */

// Include mock functions first
require_once 'test-mocks.php';

echo "=== FOSSIBOT TYPING TEST ===\n\n";

// Mock IP-Symcon functions for our tests
if (!function_exists('IPS_SemaphoreEnter')) {
    function IPS_SemaphoreEnter($key, $timeout) {
        echo "[SEM] Enter $key (timeout: $timeout)\n";
        return true; // Always succeed in tests
    }
}

if (!function_exists('IPS_SemaphoreLeave')) {
    function IPS_SemaphoreLeave($key) {
        echo "[SEM] Leave $key\n";
        return true;
    }
}

// Test runner function
$totalTests = 0;
$passedTests = 0;

function test_method($className, $methodName, $testFunction, $description = null) {
    global $totalTests, $passedTests;
    $totalTests++;
    
    try {
        $result = $testFunction();
        $passedTests++;
        echo "✅ {$className}::{$methodName}() - " . ($description ?: 'OK') . "\n";
        return $result;
    } catch (Exception $e) {
        echo "❌ {$className}::{$methodName}() - " . $e->getMessage() . "\n";
        return false;
    }
}

// =============================================================================
// MODBUS HELPER TESTS
// =============================================================================

echo "\n--- Testing ModbusHelper ---\n";

require_once 'libs/ModbusHelper.php';

test_method('ModbusHelper', 'isSafeCommand', function() {
    $result = ModbusHelper::isSafeCommand('REGEnableACOutput');
    if (!$result) throw new Exception('Should recognize safe command');
    
    $result = ModbusHelper::isSafeCommand('DANGEROUS_COMMAND');
    if ($result) throw new Exception('Should reject dangerous command');
    
    return true;
}, 'Command safety validation');

test_method('ModbusHelper', 'validateRegisterValue', function() {
    // Test integer validation
    $result = ModbusHelper::validateRegisterValue(20, 5); // Max charging current = 5A
    if (!$result['valid'] || $result['value'] !== 5) {
        throw new Exception('Integer validation failed');
    }
    
    // Test boolean validation
    $result = ModbusHelper::validateRegisterValue(24, true); // USB output = true
    if (!$result['valid'] || $result['value'] !== 1) {
        throw new Exception('Boolean validation failed');  
    }
    
    return true;
}, 'Register value validation');

test_method('ModbusHelper', 'getWriteModbus', function() {
    $command = ModbusHelper::getWriteModbus(17, 24, 1);
    if (!is_array($command) || count($command) < 6) {
        throw new Exception('Invalid Modbus command structure');
    }
    return true;
}, 'Modbus command generation');

test_method('ModbusHelper', 'intToHighLow', function() {
    $result = ModbusHelper::intToHighLow(0x1234);
    if ($result['high'] !== 0x12 || $result['low'] !== 0x34) {
        throw new Exception('High/Low conversion failed');
    }
    return true;
}, 'Integer to High/Low conversion');

test_method('ModbusHelper', 'highLowToInt', function() {
    $result = ModbusHelper::highLowToInt(0x12, 0x34);
    if ($result !== 0x1234) {
        throw new Exception('High/Low to integer conversion failed');
    }
    return true;
}, 'High/Low to integer conversion');

// =============================================================================
// TOKEN CACHE TESTS  
// =============================================================================

echo "\n--- Testing TokenCache ---\n";

require_once 'libs/TokenCache.php';

test_method('TokenCache', '__construct', function() {
    $cache = new TokenCache('test@example.com');
    return true;
}, 'Constructor with string parameter');

test_method('TokenCache', 'saveTokens', function() {
    $cache = new TokenCache('test@example.com');
    $cache->saveTokens('access_token_123', 'mqtt_token_456');
    return true;  
}, 'Save tokens with string parameters');

test_method('TokenCache', 'getTokenInfo', function() {
    $cache = new TokenCache('test@example.com');
    $info = $cache->getTokenInfo();
    if (!is_string($info)) {
        throw new Exception('Token info should return string');
    }
    return true;
}, 'Get token info returns string');

// =============================================================================
// SEMAPHORE TESTS
// =============================================================================

echo "\n--- Testing FossibotSemaphore ---\n";

require_once 'libs/FossibotSemaphore.php';

test_method('FossibotSemaphore', 'acquire', function() {
    $result = FossibotSemaphore::acquire('test_resource', 1000);
    if (!is_bool($result)) {
        throw new Exception('Acquire should return bool');
    }
    return true;
}, 'Acquire with string resource and int timeout');

test_method('FossibotSemaphore', 'release', function() {
    FossibotSemaphore::acquire('test_resource2', 1000);
    $result = FossibotSemaphore::release('test_resource2');
    if (!is_bool($result)) {
        throw new Exception('Release should return bool');
    }
    return true;
}, 'Release with string parameter');

test_method('FossibotSemaphore', 'getStatistics', function() {
    $stats = FossibotSemaphore::getStatistics();
    if (!is_array($stats)) {
        throw new Exception('Statistics should return array');
    }
    return true;
}, 'Get statistics returns array');

test_method('FossibotSemaphore', 'withLock', function() {
    $result = FossibotSemaphore::withLock('test_resource3', function() {
        return 'success';
    }, 1000);
    
    if ($result !== 'success') {
        throw new Exception('WithLock callback failed');
    }
    return true;
}, 'WithLock with callable parameter');

// =============================================================================
// CONNECTION POOL TESTS
// =============================================================================

echo "\n--- Testing FossibotConnectionPool ---\n";

require_once 'libs/FossibotConnectionPool.php';

test_method('FossibotConnectionPool', 'getStats', function() {
    $stats = FossibotConnectionPool::getStats();
    if (!is_array($stats)) {
        throw new Exception('Stats should return array');
    }
    return true;
}, 'Get stats returns array');

test_method('FossibotConnectionPool', 'cleanup', function() {
    FossibotConnectionPool::cleanup();
    return true; // Void method, just check it doesn't crash
}, 'Cleanup returns void');

// =============================================================================
// METHOD SIGNATURE CHECKS
// =============================================================================

echo "\n--- Checking Method Signatures ---\n";

// Check some key method signatures
test_method('ModbusHelper', 'signature check', function() {
    check_method_signature('ModbusHelper', 'isSafeCommand');
    check_method_signature('ModbusHelper', 'getWriteModbus');
    check_method_signature('ModbusHelper', 'validateRegisterValue');
    return true;
}, 'Method signatures are properly typed');

test_method('TokenCache', 'signature check', function() {
    check_method_signature('TokenCache', 'saveTokens');
    check_method_signature('TokenCache', 'getTokenInfo');
    return true;
}, 'TokenCache signatures are properly typed');

test_method('FossibotSemaphore', 'signature check', function() {
    check_method_signature('FossibotSemaphore', 'acquire');
    check_method_signature('FossibotSemaphore', 'withLock'); 
    return true;
}, 'FossibotSemaphore signatures are properly typed');

// =============================================================================
// INDIVIDUAL CLASS SYNTAX CHECKS
// =============================================================================

echo "\n--- PHP Syntax Validation ---\n";

$libFiles = [
    'libs/ModbusHelper.php',
    'libs/TokenCache.php', 
    'libs/SydpowerClient.php',
    'libs/MqttWebSocketClient.php',
    'libs/FossibotSemaphore.php',
    'libs/FossibotConnectionPool.php',
    'libs/FossibotResponseValidator.php',
    'FossibotDevice/module.php',
    'FossibotDiscovery/module.php'
];

foreach ($libFiles as $file) {
    test_method('PHP', 'syntax check', function() use ($file) {
        $output = [];
        $returnCode = 0;
        exec("php -l $file", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Syntax error in $file: " . implode("\n", $output));
        }
        return true;
    }, "Syntax check for $file");
}

// =============================================================================
// SUMMARY
// =============================================================================

echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Total tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";

if ($passedTests === $totalTests) {
    echo "\n🎉 ALL TESTS PASSED! Typing implementation is successful.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Check the output above.\n";
    exit(1);
}