<?php

/**
 * ABOUTME: PHP client for Sydpower API communication and device control
 * ABOUTME: Handles authentication, device management, and Modbus command execution
 */
class SydpowerClient {
    private $username;
    private $password;
    private $endpoint = "https://api.next.bspapp.com/client";
    private $clientSecret = "5rCEdl/nx7IgViBe4QYRiQ==";
    private $spaceId = "mp-6c382a98-49b8-40ba-b761-645d83e8ee74";
    
    private $deviceId;
    private $authorizeToken;
    private $accessToken;
    private $mqttAccessToken;
    private $devices = [];
    private $mqttClient;
    private $mqttConnected = false;
    private $tokenCache;
    private $lastMqttResponse = null;
    private $responseReceived = false;
    
    public function __construct($username = null, $password = null) {
        // Try to load from config if no credentials provided
        if ($username === null || $password === null) {
            $configPath = __DIR__ . '/config.local.php';
            if (file_exists($configPath)) {
                $config = require $configPath;
                $this->username = $config['username'] ?? throw new Exception("Username not found in config.local.php");
                $this->password = $config['password'] ?? throw new Exception("Password not found in config.local.php");
            } else {
                throw new Exception("No credentials provided and config.local.php not found");
            }
        } else {
            $this->username = $username;
            $this->password = $password;
        }
        
        $this->deviceId = strtoupper(bin2hex(random_bytes(16)));
        
        // Include required classes
        require_once __DIR__ . '/MqttWebSocketClient.php';
        require_once __DIR__ . '/ModbusHelper.php';
        require_once __DIR__ . '/TokenCache.php';
        
        $this->tokenCache = new TokenCache($this->username);
    }
    
    private function generateClientInfo() {
        return json_encode([
            'PLATFORM' => 'app',
            'OS' => 'android',
            'APPID' => '__UNI__55F5E7F',
            'DEVICEID' => $this->deviceId,
            'channel' => 'google',
            'scene' => 1001,
            'appId' => '__UNI__55F5E7F',
            'appLanguage' => 'en',
            'appName' => 'BrightEMS',
            'appVersion' => '1.2.3',
            'appVersionCode' => 123,
            'appWgtVersion' => '1.2.3',
            'browserName' => 'chrome',
            'browserVersion' => '130.0.6723.86',
            'deviceBrand' => 'Samsung',
            'deviceId' => $this->deviceId,
            'deviceModel' => 'SM-A426B',
            'deviceType' => 'phone',
            'osName' => 'android',
            'osVersion' => '10',
            'romName' => 'Android',
            'romVersion' => '10',
            'ua' => 'Mozilla/5.0 (Linux; Android 10; SM-A426B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.86 Mobile Safari/537.36',
            'uniPlatform' => 'app',
            'uniRuntimeVersion' => '4.24',
            'locale' => 'en',
            'LOCALE' => 'en'
        ], JSON_UNESCAPED_SLASHES);
    }
    
    private function sign($data) {
        // Sort keys alphabetically
        $sortedKeys = array_keys($data);
        sort($sortedKeys);
        
        $queryString = '';
        foreach ($sortedKeys as $key) {
            // Match JavaScript behavior: e[t] && (check for truthy values)
            if ($data[$key]) {
                $queryString .= "&" . $key . "=" . $data[$key];
            }
        }
        $queryString = substr($queryString, 1); // Remove first &
        
        $signature = hash_hmac('md5', $queryString, $this->clientSecret);
        
        return $signature;
    }
    
    public function apiRequest($route, $params = '{}', $useToken = false) {
        $method = "serverless.function.runtime.invoke";
        $clientInfo = $this->generateClientInfo();
        
        switch ($route) {
            case 'auth':
                $method = "serverless.auth.user.anonymousAuthorize";
                break;
            case 'login':
                $params = json_encode([
                    'functionTarget' => 'router',
                    'functionArgs' => [
                        '$url' => 'user/pub/login',
                        'data' => [
                            'locale' => 'en',
                            'username' => $this->username,
                            'password' => $this->password
                        ],
                        'clientInfo' => json_decode($clientInfo, true)
                    ]
                ], JSON_UNESCAPED_SLASHES);
                break;
            case 'mqtt':
                $params = json_encode([
                    'functionTarget' => 'router',
                    'functionArgs' => [
                        '$url' => 'common/emqx.getAccessToken',
                        'data' => ['locale' => 'en'],
                        'clientInfo' => json_decode($clientInfo, true),
                        'uniIdToken' => $this->accessToken
                    ]
                ], JSON_UNESCAPED_SLASHES);
                break;
            case 'devices':
                $params = json_encode([
                    'functionTarget' => 'router',
                    'functionArgs' => [
                        '$url' => 'client/device/kh/getList',
                        'data' => [
                            'locale' => 'en',
                            'pageIndex' => 1,
                            'pageSize' => 100
                        ],
                        'clientInfo' => json_decode($clientInfo, true),
                        'uniIdToken' => $this->accessToken
                    ]
                ], JSON_UNESCAPED_SLASHES);
                break;
        }
        
        $data = [
            'method' => $method,
            'params' => $params,
            'spaceId' => $this->spaceId,
            'timestamp' => time() * 1000
        ];
        
        if ($useToken && $this->authorizeToken) {
            $data['token'] = $this->authorizeToken;
        }
        
        $headers = [
            'Content-Type: application/json',
            'x-serverless-sign: ' . $this->sign($data),
            'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-A426B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.86 Mobile Safari/537.36'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: {$httpCode}");
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }
        
        return $result;
    }
    
    private function apiCallWithRetry($callback, $maxRetries = 1) {
        $attempt = 0;
        while ($attempt <= $maxRetries) {
            try {
                return $callback();
            } catch (Exception $e) {
                $attempt++;
                
                // Check if this looks like an authentication error
                $isAuthError = (
                    strpos($e->getMessage(), 'HTTP Error: 403') !== false ||
                    strpos($e->getMessage(), 'HTTP Error: 401') !== false ||
                    strpos($e->getMessage(), 'Unauthorized') !== false ||
                    strpos($e->getMessage(), 'Invalid token') !== false ||
                    strpos($e->getMessage(), 'Invalid API response structure') !== false
                );
                
                if ($isAuthError && $attempt <= $maxRetries) {
                    // Silent re-authentication
                    $this->tokenCache->clearCache();
                    $this->accessToken = null;
                    $this->mqttAccessToken = null;
                    
                    // Re-authenticate
                    $this->authenticate();
                    
                    continue; // Retry the operation
                } else {
                    // Not an auth error or max retries exceeded
                    throw $e;
                }
            }
        }
    }
    
    public function authenticate() {
        // Try to use cached tokens first
        $cachedTokens = $this->tokenCache->getValidTokens();
        if ($cachedTokens) {
            $this->accessToken = $cachedTokens['accessToken'];
            $this->mqttAccessToken = $cachedTokens['mqttAccessToken'];
            return true;
        }
        
        // Silent authentication
        $authResponse = $this->apiRequest('auth');
        $this->authorizeToken = $authResponse['data']['accessToken'];
        
        $loginResponse = $this->apiRequest('login', '{}', true);
        $this->accessToken = $loginResponse['data']['token'];
        
        $mqttResponse = $this->apiRequest('mqtt', '{}', true);
        
        // Check if MQTT response has expected structure
        if (!isset($mqttResponse['data']) || !isset($mqttResponse['data']['access_token'])) {
            throw new Exception("Invalid MQTT token response: " . json_encode($mqttResponse));
        }
        
        $this->mqttAccessToken = $mqttResponse['data']['access_token'];
        
        // Cache the tokens
        $this->tokenCache->saveTokens($this->accessToken, $this->mqttAccessToken);
        return true;
    }
    
    public function setDevices($devices) {
        $this->devices = $devices;
    }
    
    public function getDevices() {
        return $this->apiCallWithRetry(function() {
            $devicesResponse = $this->apiRequest('devices', '{}', true);
            
            // Check if response has expected structure
            if (!isset($devicesResponse['data']) || !isset($devicesResponse['data']['rows'])) {
                throw new Exception("Invalid API response structure - possible token issue");
            }
            
            foreach ($devicesResponse['data']['rows'] as $device) {
                $deviceId = str_replace(':', '', $device['device_id']);
                $this->devices[$deviceId] = $device;
            }
            
            return $this->devices;
        });
    }
    
    public function connectMqtt() {
        return $this->apiCallWithRetry(function() {
            if (!$this->mqttAccessToken) {
                throw new Exception("MQTT access token not available. Call authenticate() first.");
            }
            
            // Connecting to MQTT (silent)
            $this->mqttClient = new MqttWebSocketClient('mqtt.sydpower.com', 8083, '/mqtt');
            $this->mqttClient->setCredentials($this->mqttAccessToken, 'helloyou');
            
            try {
                $this->mqttClient->connect();
                $this->mqttConnected = true;
            } catch (Exception $e) {
                // MQTT connection failed - might be token issue
                if (strpos($e->getMessage(), 'Connection refused') !== false || 
                    strpos($e->getMessage(), 'Authentication') !== false) {
                    throw new Exception("HTTP Error: 401"); // Trigger auth retry
                }
                throw $e;
            }
            
            // Subscribe to device response topics
            $deviceIds = $this->getDeviceIds();
            foreach ($deviceIds as $deviceId) {
                $this->mqttClient->subscribe("{$deviceId}/device/response/state");
                $this->mqttClient->subscribe("{$deviceId}/device/response/client/+");
                // Extended subscriptions for error detection
                $this->mqttClient->subscribe("{$deviceId}/device/error/+");
                $this->mqttClient->subscribe("{$deviceId}/error/+");
                $this->mqttClient->subscribe("{$deviceId}/+/error");
                $this->mqttClient->subscribe("{$deviceId}/+");  // Catch-all for debugging
            }
            
            // Set up message handler
            $this->mqttClient->onMessage(function($topic, $payload) {
                $this->handleMqttMessage($topic, $payload);
            });
            
            // MQTT connected successfully
            return true;
        });
    }
    
    private function handleMqttMessage($topic, $payload) {
        // Mark that we received a response
        $this->responseReceived = true;
        $this->lastMqttResponse = ['topic' => $topic, 'payload' => $payload, 'time' => microtime(true)];
        
        // Check for error topics or unusual topics
        if (strpos($topic, 'error') !== false) {
            // Only log errors, not all messages
            echo "❌ ERROR TOPIC detected: $topic\n";
            return;
        }
        
        // Check if this is a device response topic we can handle
        if (!strpos($topic, '/device/response/state') && !strpos($topic, '/device/response/client/')) {
            // Silently ignore non-standard topics (no spam)
            return;
        }
        
        $deviceMac = explode('/', $topic)[0];
        
        // Convert payload to array of integers
        $arr = [];
        for ($i = 0; $i < strlen($payload); $i++) {
            $arr[] = ord($payload[$i]);
        }
        
        // Following MODBUS protocol, removing 6 first control indexes
        $c = array_slice($arr, 6);
        
        // Transform to 16-bit registers
        $registers = [];
        for ($i = 0; $i < count($c); $i += 2) {
            if (isset($c[$i + 1])) {
                $registers[] = ModbusHelper::highLowToInt($c[$i], $c[$i + 1]);
            }
        }
        
        // Update device status based on message type
        if (count($registers) == 81 && strpos($topic, 'device/response/client/04') !== false) {
            $this->updateDeviceStatus04($deviceMac, $registers);
        } elseif (count($registers) == 81 && strpos($topic, 'device/response/client/data') !== false) {
            $this->updateDeviceStatusData($deviceMac, $registers);
        } elseif (count($registers) >= 57) {
            // Handle partial updates or other response types
            $this->updateDeviceStatusPartial($deviceMac, $registers, $topic);
        }
    }
    
    private function updateDeviceStatus04($deviceMac, $registers) {
        if (!isset($this->devices[$deviceMac])) {
            $this->devices[$deviceMac] = [];
        }
        
        // Track update timestamp for change detection
        $this->devices[$deviceMac]['_lastUpdate'] = microtime(true);
        
        $activeOutputs = str_pad(decbin($registers[41]), 16, '0', STR_PAD_LEFT);
        $activeOutputs = str_split(strrev($activeOutputs)); // Reverse for correct bit order
        
        $this->devices[$deviceMac]['soc'] = round(($registers[56] / 1000) * 100, 1);
        $this->devices[$deviceMac]['totalInput'] = $registers[6];
        $this->devices[$deviceMac]['totalOutput'] = $registers[39];
        // FINALE korrekte Bit-Zuordnung basierend auf vollständigen Tests:
        $this->devices[$deviceMac]['acOutput'] = $activeOutputs[11] == '1';  // Bit[11] = AC
        $this->devices[$deviceMac]['usbOutput'] = $activeOutputs[9] == '1';   // Bit[9] = USB  
        $this->devices[$deviceMac]['dcOutput'] = $activeOutputs[10] == '1';   // Bit[10] = DC
        $this->devices[$deviceMac]['ledOutput'] = $activeOutputs[3] == '1';
        
        // WICHTIG: Auch Settings-Register aus /client/04 extrahieren
        // Da wir nie /client/data bekommen, müssen wir alles aus /client/04 nehmen
        if (count($registers) >= 68) {
            $this->devices[$deviceMac]['maximumChargingCurrent'] = $registers[20];
            $this->devices[$deviceMac]['acSilentCharging'] = ($registers[57] ?? 0) == 1;
            $this->devices[$deviceMac]['usbStandbyTime'] = $registers[59] ?? null;
            $this->devices[$deviceMac]['acStandbyTime'] = $registers[60] ?? null;
            $this->devices[$deviceMac]['dcStandbyTime'] = $registers[61] ?? null;
            $this->devices[$deviceMac]['screenRestTime'] = $registers[62] ?? null;
            $this->devices[$deviceMac]['stopChargeAfter'] = $registers[63] ?? null;
            $this->devices[$deviceMac]['dischargeLowerLimit'] = isset($registers[66]) ? $registers[66] : null;
            $this->devices[$deviceMac]['acChargingUpperLimit'] = isset($registers[67]) ? $registers[67] : null;
            $this->devices[$deviceMac]['wholeMachineUnusedTime'] = $registers[68] ?? null;
        }
        
        // Status update processed silently
    }
    
    private function updateDeviceStatusData($deviceMac, $registers) {
        if (!isset($this->devices[$deviceMac])) {
            $this->devices[$deviceMac] = [];
        }
        
        $this->devices[$deviceMac]['maximumChargingCurrent'] = $registers[20];
        $this->devices[$deviceMac]['acSilentCharging'] = $registers[57] == 1;
        $this->devices[$deviceMac]['usbStandbyTime'] = $registers[59];
        $this->devices[$deviceMac]['acStandbyTime'] = $registers[60];
        $this->devices[$deviceMac]['dcStandbyTime'] = $registers[61];
        $this->devices[$deviceMac]['screenRestTime'] = $registers[62];
        $this->devices[$deviceMac]['stopChargeAfter'] = $registers[63];
        $this->devices[$deviceMac]['dischargeLowerLimit'] = $registers[66];
        $this->devices[$deviceMac]['acChargingUpperLimit'] = $registers[67];
        $this->devices[$deviceMac]['wholeMachineUnusedTime'] = $registers[68];
        
        // Settings update processed silently
    }
    
    private function updateDeviceStatusPartial($deviceMac, $registers, $topic) {
        if (!isset($this->devices[$deviceMac])) {
            $this->devices[$deviceMac] = [];
        }
        
        // Log the topic to understand what we're getting
        echo "DEBUG: Partial update from topic: $topic with " . count($registers) . " registers\n";
        
        // Always update SOC if available
        if (count($registers) >= 57) {
            $this->devices[$deviceMac]['soc'] = round(($registers[56] / 1000) * 100, 1);
        }
        
        // Check if this might contain settings data
        if (count($registers) >= 68) {
            // Try to extract settings (similar to Home Assistant data topic)
            $this->devices[$deviceMac]['maximumChargingCurrent'] = $registers[20] ?? null;
            $this->devices[$deviceMac]['acSilentCharging'] = ($registers[57] ?? 0) == 1;
            $this->devices[$deviceMac]['usbStandbyTime'] = $registers[59] ?? null;
            $this->devices[$deviceMac]['acStandbyTime'] = $registers[60] ?? null;
            $this->devices[$deviceMac]['dcStandbyTime'] = $registers[61] ?? null;
            $this->devices[$deviceMac]['screenRestTime'] = $registers[62] ?? null;
            $this->devices[$deviceMac]['stopChargeAfter'] = $registers[63] ?? null;
            $this->devices[$deviceMac]['dischargeLowerLimit'] = isset($registers[66]) ? ($registers[66] / 10) : null;
            $this->devices[$deviceMac]['acChargingUpperLimit'] = isset($registers[67]) ? ($registers[67] / 10) : null;
            $this->devices[$deviceMac]['wholeMachineUnusedTime'] = $registers[68] ?? null;
            
            echo "DEBUG: Extracted charge limit: " . ($this->devices[$deviceMac]['acChargingUpperLimit'] ?? 'null') . "\n";
        }
    }
    
    public function getDeviceIds() {
        return array_keys($this->devices);
    }
    
    public function sendCommand($deviceId, $command, $value = null) {
        return $this->apiCallWithRetry(function() use ($deviceId, $command, $value) {
            if (!$this->mqttConnected) {
                throw new Exception("MQTT not connected. Call connectMqtt() first.");
            }
        
        // CRITICAL: Whitelist of safe commands to prevent device damage
        $safeCommands = [
            'REGRequestSettings',
            'REGMaxChargeCurrent',
            'REGChargeUpperLimit', 
            'REGDischargeLowerLimit',
            'REGStopChargeAfter',
            'REGEnableUSBOutput',
            'REGDisableUSBOutput',
            'REGEnableDCOutput',
            'REGDisableDCOutput',
            'REGEnableACOutput',
            'REGDisableACOutput'
            // DANGEROUS COMMANDS INTENTIONALLY EXCLUDED:
            // - REGSleepTime (value 0 bricks device!)
            // - Any register modification not explicitly tested
        ];
        
        if (!in_array($command, $safeCommands, true)) {
            throw new Exception("CRITICAL: Command '$command' is not in the safe commands whitelist. BLOCKING to prevent device damage!");
        }
        
        try {
            $modbusMessage = null;
            
            // Generate appropriate Modbus command with validation
            switch ($command) {
                case 'REGRequestSettings':
                    $modbusMessage = ModbusHelper::getRequestSettingsCommand();
                    break;
                    
                case 'REGMaxChargeCurrent':
                    if ($value === null) throw new Exception("Value required for {$command}");
                    $modbusMessage = ModbusHelper::getMaxChargeCurrentCommand($value);
                    break;
                    
                case 'REGChargeUpperLimit':
                    if ($value === null) throw new Exception("Value required for {$command}");
                    $modbusMessage = ModbusHelper::getChargeUpperLimitCommand($value);
                    break;
                    
                case 'REGDischargeLowerLimit':
                    if ($value === null) throw new Exception("Value required for {$command}");
                    $modbusMessage = ModbusHelper::getDischargeLimitCommand($value);
                    break;
                    
                case 'REGStopChargeAfter':
                    if ($value === null) throw new Exception("Value required for {$command}");
                    $modbusMessage = ModbusHelper::getStopChargeAfterCommand($value);
                    break;
                    
                case 'REGEnableUSBOutput':
                    $modbusMessage = ModbusHelper::getUSBOutputCommand(true);
                    break;
                    
                case 'REGDisableUSBOutput':
                    $modbusMessage = ModbusHelper::getUSBOutputCommand(false);
                    break;
                    
                case 'REGEnableDCOutput':
                    $modbusMessage = ModbusHelper::getDCOutputCommand(true);
                    break;
                    
                case 'REGDisableDCOutput':
                    $modbusMessage = ModbusHelper::getDCOutputCommand(false);
                    break;
                    
                case 'REGEnableACOutput':
                    $modbusMessage = ModbusHelper::getACOutputCommand(true);
                    break;
                    
                case 'REGDisableACOutput':
                    $modbusMessage = ModbusHelper::getACOutputCommand(false);
                    break;
                    
                // Add dynamic command support (like Home Assistant)
                default:
                    // Check if it's a dynamic write command
                    if (strpos($command, 'set_') === 0 && $value !== null) {
                        // Extract register name from command
                        // e.g., 'set_charging_current' -> REGISTER_MAXIMUM_CHARGING_CURRENT
                        if ($command === 'set_charging_current') {
                            $modbusMessage = ModbusHelper::getWriteModbus(
                                ModbusHelper::REGISTER_MODBUS_ADDRESS,
                                ModbusHelper::REGISTER_MAXIMUM_CHARGING_CURRENT,
                                $value
                            );
                        } else {
                            throw new Exception("Unknown dynamic command: {$command}");
                        }
                    } else {
                        throw new Exception("Unknown command: {$command}");
                    }
                    break;
            }
            
            if ($modbusMessage === null) {
                throw new Exception("Failed to generate Modbus message for {$command}");
            }
            
            // Send via MQTT
            $topic = "{$deviceId}/client/request/data";
            $this->mqttClient->publish($topic, $modbusMessage, 1);
            
            return ['success' => "Command {$command} sent"];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
        }); // End of apiCallWithRetry
    }
    
    public function getDeviceStatus($deviceId) {
        if (isset($this->devices[$deviceId])) {
            return $this->devices[$deviceId];
        }
        return false;
    }
    
    public function waitForResponse($timeout = 3.0) {
        if (!$this->mqttConnected) {
            return false;
        }
        
        $this->responseReceived = false;
        $start = microtime(true);
        
        // Poll in 100ms chunks für bessere Responsivität
        while ((microtime(true) - $start) < $timeout) {
            $this->mqttClient->loop(0.1);
            
            if ($this->responseReceived) {
                return true;
            }
        }
        
        return false;
    }
    
    public function waitForSettingsResponse($timeout = 2.0) {
        if (!$this->mqttConnected) {
            return false;
        }
        
        $start = microtime(true);
        $initialDataState = $this->devices;
        
        // Warte spezifisch auf Settings-Updates (acChargingUpperLimit etc.)
        while ((microtime(true) - $start) < $timeout) {
            $this->mqttClient->loop(0.1);
            
            // Prüfe ob Settings-Daten angekommen sind
            foreach ($this->devices as $deviceId => $data) {
                if (isset($data['acChargingUpperLimit']) || isset($data['dischargeLowerLimit'])) {
                    return true; // Settings Response erhalten
                }
            }
        }
        
        return false;
    }
    
    public function hasReceivedResponse() {
        return $this->responseReceived;
    }
    
    public function getLastResponse() {
        return $this->lastMqttResponse;
    }
    
    public function quickPing($deviceId) {
        // Quick connectivity check with 1 second timeout
        $this->responseReceived = false;
        $this->sendCommand($deviceId, 'REGRequestSettings');
        return $this->waitForResponse(1.0);
    }
    
    public function requestDeviceSettings($deviceId) {
        // Request fresh settings from device
        $this->sendCommand($deviceId, 'REGRequestSettings');
    }
    
    public function listenForUpdates($timeout = 30) {
        if (!$this->mqttConnected) {
            throw new Exception("MQTT not connected. Call connectMqtt() first.");
        }
        
        // Listening for MQTT messages
        $this->mqttClient->loop($timeout);
        return true; // Return true to indicate we listened
    }
    
    /**
     * Wait for any data update after command (universal for all command types)
     */
    public function waitForDataUpdate($deviceId, $timeout = 2.0) {
        if (!$this->mqttConnected) {
            return false;
        }
        
        $start = microtime(true);
        $initialTimestamp = isset($this->devices[$deviceId]['_lastUpdate']) ? $this->devices[$deviceId]['_lastUpdate'] : 0;
        
        // Poll in 100ms chunks waiting for any data update
        while ((microtime(true) - $start) < $timeout) {
            $this->mqttClient->loop(0.1);
            
            // Check if we got a new update (timestamp changed)
            if (isset($this->devices[$deviceId]['_lastUpdate']) && 
                $this->devices[$deviceId]['_lastUpdate'] > $initialTimestamp) {
                return true; // Got fresh data
            }
        }
        
        return false;
    }
    
    public function disconnect() {
        if ($this->mqttClient && $this->mqttConnected) {
            $this->mqttClient->disconnect();
            $this->mqttConnected = false;
        }
    }
    
    public function getTokenInfo() {
        return $this->tokenCache->getTokenInfo();
    }
    
    public function hasValidCachedTokens() {
        if (!$this->tokenCache) return false;
        return $this->tokenCache->getValidTokens() !== null;
    }
    
    public function getTokenDebugInfo() {
        $cachedTokens = $this->tokenCache ? $this->tokenCache->getValidTokens() : null;
        
        return [
            'has_cached_tokens' => $cachedTokens !== null,
            'current_access_token' => $this->accessToken ? substr($this->accessToken, 0, 20) . '...' : null,
            'current_mqtt_token' => $this->mqttAccessToken ? substr($this->mqttAccessToken, 0, 20) . '...' : null,
            'cache_age_minutes' => $cachedTokens && isset($cachedTokens['timestamp']) ? 
                round((time() - $cachedTokens['timestamp']) / 60) : null,
            'cache_created' => $cachedTokens['cached_at'] ?? null
        ];
    }
    
    public function clearTokenCache() {
        $this->tokenCache->clearCache();
    }
    
    public function getAllDeviceData() {
        return $this->devices;
    }
}