<?php
/**
 * MikroTik RouterOS API Class
 * 
 * A lightweight PHP class for connecting to MikroTik routers
 * using the RouterOS API protocol.
 */

require_once __DIR__ . '/config.php';

class MikroTikAPI {
    private $socket;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $debug = false;
    
    // API response codes
    const OK = '!done';
    const RE = '!re';
    const TRAP = '!trap';
    const FATAL = '!fatal';
    
    /**
     * Constructor
     */
    public function __construct($host, $port, $user, $pass, $debug = false) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->debug = $debug;
    }
    
    /**
     * Connect to the MikroTik router
     */
    public function connect() {
        $errno = 0;
        $errstr = '';
        
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, CONNECTION_TIMEOUT);
        
        if (!$this->socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }
        
        stream_set_Timeout($this->socket, 30);
        
        // Login
        $login = $this->login();
        if (!$login) {
            $this->disconnect();
            throw new Exception("Login failed. Check username/password.");
        }
        
        return true;
    }
    
    /**
     * Login to the router
     */
    private function login() {
        // Send login request
        $response = $this->sendCommand('/login');
        
        if ($this->debug) {
            error_log("LOGIN STEP 1: " . implode("\n", $response));
        }
        
        $hash = '';
        
        // Extract challenge hash
        foreach ($response as $line) {
            if (preg_match('/=ret=(.+)/', $line, $match)) {
                $hash = trim($match[1]);
                break;
            }
        }
        
        if (empty($hash)) {
            // Check if router accepts plain login (older versions)
            $response2 = $this->sendCommand('/login', [
                'name' => $this->user,
                'password' => $this->pass
            ]);
            
            if ($this->debug) {
                error_log("PLAIN LOGIN RESPONSE: " . implode("\n", $response2));
            }
            
            foreach ($response2 as $line) {
                if (strpos($line, '=ret=') !== false || strpos($line, '!done') !== false) {
                    return true;
                }
            }
            return false;
        }
        
        // Decode hash if base64
        $hashBinary = base64_decode($hash, true);
        if ($hashBinary !== false && strlen($hashBinary) > 0) {
            $hash = $hashBinary;
        }
        
        // Calculate MD5: null char + password + challenge
        $password = md5(chr(0) . $this->pass . $hash, true);
        $passwordEncoded = base64_encode($password);
        
        if ($this->debug) {
            error_log("HASH LEN: " . strlen($hash) . ", PASSWORD_ENCODED: " . $passwordEncoded);
        }
        
        // Send login with encoded password
        $response = $this->sendCommand('/login', [
            'name' => $this->user,
            'password' => $passwordEncoded
        ]);
        
        if ($this->debug) {
            error_log("LOGIN STEP 2: " . implode("\n", $response));
        }
        
        // Check for success
        foreach ($response as $line) {
            if (strpos($line, '=ret=') !== false || strpos($line, '!done') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Send command to router
     */
    public function sendCommand($command, $params = []) {
        // Build command string
        $cmd = $command;
        foreach ($params as $key => $value) {
            $cmd .= "\n=" . $key . "=" . $this->escapeValue($value);
        }
        $cmd .= "\n";
        
        if ($this->debug) {
            error_log(">>> SENDING: " . trim($cmd));
        }
        
        // Send command
        fwrite($this->socket, $cmd);
        
        // Read response
        $response = [];
        $startTime = time();
        
        while (!feof($this->socket)) {
            if ((time() - $startTime) > 30) break;
            
            $line = fgets($this->socket);
            if ($line === false) break;
            
            $line = rtrim($line);
            $response[] = $line;
            
            if ($line === self::OK || $line === self::TRAP || $line === self::FATAL) {
                break;
            }
        }
        
        if ($this->debug) {
            error_log("<<< RECEIVED: " . implode("\n", $response));
        }
        
        return $response;
    }
    
    /**
     * Parse response into structured data
     */
    public function parseResponse($response) {
        $data = [];
        $current = [];
        
        foreach ($response as $line) {
            if (in_array($line, [self::OK, self::RE, self::TRAP, self::FATAL])) {
                if (!empty($current)) {
                    $data[] = $current;
                    $current = [];
                }
                continue;
            }
            
            if (preg_match('/^=(\w+)=(.*)$/', $line, $match)) {
                $current[$match[1]] = $match[2];
            }
        }
        
        if (!empty($current)) {
            $data[] = $current;
        }
        
        return $data;
    }
    
    /**
     * Run a query and return parsed results
     */
    public function query($command, $params = []) {
        $response = $this->sendCommand($command, $params);
        return $this->parseResponse($response);
    }
    
    /**
     * Escape special characters in values
     */
    private function escapeValue($value) {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', 'n', 'r'], $value);
    }
    
    /**
     * Disconnect from router
     */
    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
    
    public function __destruct() {
        $this->disconnect();
    }

}
