<?php
/**
 * MikroTik Router Connection Configuration
 * 
 * UPDATE THESE VALUES WITH YOUR ROUTER DETAILS
 */

// Prevent multiple includes from redefining constants
if (!function_exists('mikrotik_config_loaded')) {
    
    // RouterOS API Connection Settings
    if (!defined('ROUTER_HOST')) define('ROUTER_HOST', '192.168.1.1');
    if (!defined('ROUTER_PORT')) define('ROUTER_PORT', 8728);
    
    // Default credentials (can be overridden via form input)
    if (!defined('DEFAULT_USER')) define('DEFAULT_USER', 'admin');
    if (!defined('DEFAULT_PASS')) define('DEFAULT_PASS', '');
    
    // Optional: Enable SSL for secure connection
    if (!defined('USE_SSL')) define('USE_SSL', false);
    
    // Connection timeout in seconds
    if (!defined('CONNECTION_TIMEOUT')) define('CONNECTION_TIMEOUT', 10);
    
    // Debug mode - set to true to see raw API responses
    if (!defined('DEBUG_MODE')) define('DEBUG_MODE', false);
    
    /**
     * Get credentials from POST or session
     */
    function getCredentials() {
        return [
            'host' => $_SESSION['router_host'] ?? ROUTER_HOST,
            'user' => $_SESSION['router_user'] ?? DEFAULT_USER,
            'pass' => $_SESSION['router_pass'] ?? DEFAULT_PASS
        ];
    }
}