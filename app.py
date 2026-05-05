"""
MikroTik Router Dashboard (Flask Application)

Real-time monitoring interface for MikroTik routers using Flask.
"""

from flask import Flask, render_template, request, redirect, url_for, session, jsonify
from MikroTikAPI import MikroTikAPI
import secrets

app = Flask(__name__)
app.secret_key = secrets.token_hex(32)

# Default configuration
CONFIG = {
    'router_host': '192.168.1.1',
    'router_port': 8728,
    'connection_timeout': 10,
    'debug_mode': False
}


@app.route('/')
def index():
    """Main dashboard page"""
    if not session.get('connected'):
        return redirect(url_for('login'))
    return render_template('index.html', 
                         host=session.get('router_host'),
                         port=session.get('router_port'))


@app.route('/login', methods=['GET', 'POST'])
def login():
    """Login page"""
    error = ''
    
    if request.method == 'POST':
        host = request.form.get('host', CONFIG['router_host'])
        port = int(request.form.get('port', CONFIG['router_port']))
        user = request.form.get('username', '')
        password = request.form.get('password', '')
        
        if not user or not password:
            error = 'Please provide both username and password.'
        else:
            try:
                # Test connection
                api = MikroTikAPI(host, port, user, password, CONFIG['connection_timeout'])
                api.connect()
                api.disconnect()
                
                # Save to session
                session['router_host'] = host
                session['router_port'] = port
                session['router_user'] = user
                session['router_pass'] = password
                session['connected'] = True
                
                return redirect(url_for('index'))
            except Exception as e:
                error = f'Connection failed: {str(e)}'
    
    return render_template('login.html', 
                         host=session.get('router_host', CONFIG['router_host']),
                         port=session.get('router_port', CONFIG['router_port']),
                         error=error)


@app.route('/logout')
def logout():
    """Logout and clear session"""
    session.clear()
    return redirect(url_for('login'))


@app.route('/query')
def query_page():
    """Query tool page"""
    if not session.get('connected'):
        return redirect(url_for('login'))
    return render_template('query.html',
                         host=session.get('router_host'),
                         port=session.get('router_port'))


# API endpoints
@app.route('/api/system')
def api_system():
    """Get system resource info"""
    if not session.get('connected'):
        return jsonify({'success': False, 'error': 'Not connected'})
    
    try:
        api = MikroTikAPI(
            session.get('router_host'),
            session.get('router_port'),
            session.get('router_user'),
            session.get('router_pass'),
            CONFIG['connection_timeout']
        )
        api.connect()
        data = api.query('/system/resource/print')
        api.disconnect()
        
        return jsonify({'success': True, 'data': data[0] if data else {}})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/interfaces')
def api_interfaces():
    """Get network interfaces"""
    if not session.get('connected'):
        return jsonify({'success': False, 'error': 'Not connected'})
    
    try:
        api = MikroTikAPI(
            session.get('router_host'),
            session.get('router_port'),
            session.get('router_user'),
            session.get('router_pass'),
            CONFIG['connection_timeout']
        )
        api.connect()
        data = api.query('/interface/print')
        api.disconnect()
        
        return jsonify({'success': True, 'data': data})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/firewall')
def api_firewall():
    """Get firewall rules"""
    if not session.get('connected'):
        return jsonify({'success': False, 'error': 'Not connected'})
    
    try:
        api = MikroTikAPI(
            session.get('router_host'),
            session.get('router_port'),
            session.get('router_user'),
            session.get('router_pass'),
            CONFIG['connection_timeout']
        )
        api.connect()
        data = api.query('/ip/firewall/filter/print')
        api.disconnect()
        
        return jsonify({'success': True, 'data': data})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/addresses')
def api_addresses():
    """Get IP addresses"""
    if not session.get('connected'):
        return jsonify({'success': False, 'error': 'Not connected'})
    
    try:
        api = MikroTikAPI(
            session.get('router_host'),
            session.get('router_port'),
            session.get('router_user'),
            session.get('router_pass'),
            CONFIG['connection_timeout']
        )
        api.connect()
        data = api.query('/ip/address/print')
        api.disconnect()
        
        return jsonify({'success': True, 'data': data})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/pppoe')
def api_pppoe():
    """Get PPPoE active clients"""
    if not session.get('connected'):
        return jsonify({'success': False, 'error': 'Not connected'})
    
    try:
        api = MikroTikAPI(
            session.get('router_host'),
            session.get('router_port'),
            session.get('router_user'),
            session.get('router_pass'),
            CONFIG['connection_timeout']
        )
        api.connect()
        servers = api.query('/interface/pppoe-server/print')
        active = api.query('/interface/pppoe-server/active/print')
        api.disconnect()
        
        return jsonify({'success': True, 'data': servers, 'active': active})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/cpu')
def api_cpu():
    """Get CPU status"""
    if not session.get('connected'):
        return jsonify({'success': False, 'error': 'Not connected'})
    
    try:
        api = MikroTikAPI(
            session.get('router_host'),
            session.get('router_port'),
            session.get('router_user'),
            session.get('router_pass'),
            CONFIG['connection_timeout']
        )
        api.connect()
        data = api.query('/system/resource/print')
        api.disconnect()
        
        return jsonify({'success': True, 'data': data[0] if data else {}})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/check')
def api_check():
    """Check connection status"""
    return jsonify({'success': session.get('connected', False)})


@app.route('/api/execute', methods=['POST'])
def api_execute():
    """Execute custom command"""
    if not session.get('connected'):
        return jsonify({'success': False, 'error': 'Not connected'})
    
    try:
        command = request.json.get('command', '')
        
        api = MikroTikAPI(
            session.get('router_host'),
            session.get('router_port'),
            session.get('router_user'),
            session.get('router_pass'),
            CONFIG['connection_timeout']
        )
        api.connect()
        
        # Parse command and params
        lines = command.strip().split('\n')
        cmd_path = lines[0].strip()
        params = {}
        
        for i in range(1, len(lines)):
            line = lines[i].strip()
            if '=' in line:
                key, value = line.split('=', 1)
                params[key.strip()] = value.strip()
        
        raw_response = api.send_command(cmd_path, params)
        parsed_data = api.parse_response(raw_response)
        api.disconnect()
        
        return jsonify({
            'success': True,
            'command': cmd_path,
            'raw': raw_response,
            'parsed': parsed_data
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)