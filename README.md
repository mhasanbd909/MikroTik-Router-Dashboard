# MikroTik Router Dashboard

A PHP-based web dashboard for monitoring and managing MikroTik routers via the RouterOS API.

![MikroTik Dashboard](https://img.shields.io/badge/PHP-8.0+-purple.svg)
![License](https://img.shields.io/badge/license-MIT-blue.svg)

## Features

- **Real-time Monitoring** - View router status, system resources, and network interfaces
- **Dynamic Credentials** - Login form for entering router credentials (no hardcoding required)
- **Multiple Views**:
  - 🌐 Network Interfaces
  - 📱 DHCP Leases
  - 🛡️ Firewall Rules
  - 🔢 IP Addresses
  - 🔥 Hotspot Active Users
- **Quick Query Tool** - Run custom RouterOS commands
- **Responsive Design** - Works on desktop and mobile

## Requirements

- PHP 8.0 or higher
- XAMPP or similar local server
- MikroTik router with API service enabled
- Network access to the router

## Installation

1. **Clone the repository**
   ```bash
   git clone <your-repo-url> mikrotik-dashboard
   cd mikrotik-dashboard
   ```

2. **Configure your web server**
   - For XAMPP: Copy files to `C:\xampp\htdocs\mikrotik\`
   - For Linux: Copy to `/var/www/html/mikrotik/`

3. **Enable MikroTik API service**
   ```
   /ip service print
   /ip service enable api
   ```

4. **Access the dashboard**
   - Open: `http://localhost/mikrotik/`
   - Enter your router IP, API port (default: 8728), username, and password

## Configuration

Copy `config.sample.php` to `config.php` and modify if needed:

```php
define('ROUTER_HOST', '192.168.1.1');
define('ROUTER_PORT', 8728);
define('DEFAULT_USER', 'admin');
define('DEFAULT_PASS', '');
define('CONNECTION_TIMEOUT', 10);
define('DEBUG_MODE', false);
```

## Quick Query Tool

Access `query.php` for direct RouterOS command execution:
- Enter commands in path/parameter format
- Click preset buttons for common commands
- View raw and parsed responses

Example command:
```
/system/resource/print
```

## API Endpoints (AJAX)

The dashboard uses these API actions:
- `?action=system` - System resource info
- `?action=interfaces` - Network interfaces
- `?action=dhcp` - DHCP leases
- `?action=firewall` - Firewall rules
- `?action=addresses` - IP addresses
- `?action=hotspot` - Hotspot users

## Security Notes

- Never commit `config.php` with real credentials
- Use SSL (port 8729) for production deployments
- Implement proper session management for production use
- Consider adding IP-based access restrictions

## Troubleshooting

**Connection Failed**
- Verify router IP address is correct
- Check firewall allows port 8728
- Ensure API service is enabled on router

**Login Failed**
- Verify username and password
- Check router user permissions
- Enable DEBUG_MODE in config.php for detailed logs

## License

MIT License - feel free to use and modify.