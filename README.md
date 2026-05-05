# MikroTik Router Dashboard

A Python-based web dashboard for monitoring and managing MikroTik routers via the RouterOS API.

![Python](https://img.shields.io/badge/Python-3.8+-blue.svg)
![Flask](https://img.shields.io/badge/Flask-2.0+-green.svg)
![License](https://img.shields.io/badge/license-MIT-blue.svg)

## Features

- **Real-time Monitoring** - View router status, system resources, and network interfaces
- **Dynamic Credentials** - Login form for entering router credentials (no hardcoding required)
- **Multiple Views**:
  - 🌐 Network Interfaces
  - 🔢 IP Addresses
  - 🛡️ Firewall Rules
  - 📡 PPPoE Active Clients
  - 💻 CPU Status
- **Quick Query Tool** - Run custom RouterOS commands via API
- **Responsive Design** - Works on desktop and mobile
- **Python/Flask Backend** - Lightweight and extensible

## Requirements

- Python 3.8 or higher
- Flask 2.0+
- MikroTik router with API service enabled
- Network access to the router

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/mhasanbd909/MikroTik-Router-Dashboard.git
   cd MikroTik-Router-Dashboard
   ```

2. **Install Python dependencies**
   ```bash
   pip install -r requirements.txt
   ```

3. **Enable MikroTik API service**
   ```
   /ip service print
   /ip service enable api
   ```

4. **Run the application**
   ```bash
   python app.py
   ```

5. **Access the dashboard**
   - Open: `http://localhost:5000/`
   - Enter your router IP, API port (default: 8728), username, and password

## API Endpoints

The dashboard provides these REST API endpoints:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/` | GET | Main dashboard |
| `/login` | POST | Connect to router |
| `/logout` | GET | Disconnect from router |
| `/query` | GET | Quick command query tool |
| `/api/system` | GET | System resource info |
| `/api/interfaces` | GET | Network interfaces |
| `/api/addresses` | GET | IP addresses |
| `/api/firewall` | GET | Firewall rules |
| `/api/pppoe` | GET | PPPoE servers & active clients |
| `/api/cpu` | GET | CPU status |
| `/api/execute` | POST | Execute custom command |

## Quick Query Tool

Access `/query` for direct RouterOS command execution:
- Enter commands in path/parameter format
- Click preset buttons for common commands
- View raw and parsed responses

Example command:
```
/system/resource/print
```

## Security Notes

- Use SSL (port 8729) for production deployments
- Implement proper session management for production use
- Consider adding IP-based access restrictions
- Never expose the dashboard to untrusted networks

## Troubleshooting

**Connection Failed**
- Verify router IP address is correct
- Check firewall allows port 8728
- Ensure API service is enabled on router
- Check network connectivity to router

**Login Failed**
- Verify username and password
- Check router user permissions
- Ensure API access is allowed for the user

## Architecture

```
mikrotik/
├── MikroTikAPI.py      # RouterOS API communication class
├── app.py              # Flask application with routes
├── requirements.txt    # Python dependencies
└── templates/
    ├── login.html      # Login form
    ├── index.html      # Main dashboard
    └── query.html      # Query tool
```

## License

MIT License - feel free to use and modify.