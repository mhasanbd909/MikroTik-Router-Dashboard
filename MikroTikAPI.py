"""
MikroTik RouterOS API Class (Python)

A Python class for connecting to MikroTik routers using the RouterOS API protocol.
"""

import socket
import binascii
import hashlib
import base64
import time
from typing import List, Dict, Optional, Any

class MikroTikAPI:
    """MikroTik RouterOS API connection class"""
    
    # API response codes
    OK = '!done'
    RE = '!re'
    TRAP = '!trap'
    FATAL = '!fatal'
    
    def __init__(self, host: str, port: int, user: str, password: str, timeout: int = 10):
        """
        Initialize MikroTik API connection
        
        Args:
            host: Router IP address
            port: API port (default 8728)
            user: Username
            password: Password
            timeout: Connection timeout in seconds
        """
        self.host = host
        self.port = port
        self.user = user
        self.password = password
        self.timeout = timeout
        self.socket = None
        
    def connect(self) -> bool:
        """Connect to the MikroTik router"""
        try:
            self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            self.socket.settimeout(self.timeout)
            self.socket.connect((self.host, self.port))
            
            # Login
            if not self.login():
                self.disconnect()
                raise Exception("Login failed. Check username/password.")
            
            return True
        except socket.timeout:
            raise Exception(f"Connection timeout. Could not reach {self.host}:{self.port}")
        except socket.error as e:
            raise Exception(f"Connection failed: {e}")
    
    def login(self) -> bool:
        """Login to the router"""
        # Send login request
        response = self.send_command('/login')
        
        # Extract challenge hash
        hash_data = ''
        for line in response:
            if '=ret=' in line:
                hash_data = line.split('=ret=')[1].strip()
                break
        
        if not hash_data:
            # Try plain login for older routers
            response = self.send_command('/login', {'name': self.user, 'password': self.password})
            for line in response:
                if '=ret=' in line or self.OK in line:
                    return True
            return False
        
        # Decode hash if base64
        try:
            hash_binary = base64.b64decode(hash_data)
        except Exception:
            hash_binary = hash_data.encode()
        
        # Calculate MD5: null byte + password + challenge
        password_bytes = self.password.encode('utf-8')
        md5_input = b'\x00' + password_bytes + hash_binary
        password_hash = hashlib.md5(md5_input).digest()
        password_encoded = base64.b64encode(password_hash).decode('ascii')
        
        # Send login with encoded password
        response = self.send_command('/login', {
            'name': self.user,
            'password': password_encoded
        })
        
        # Check for success
        for line in response:
            if '=ret=' in line or self.OK in line:
                return True
        
        return False
    
    def send_command(self, command: str, params: Dict[str, str] = None) -> List[str]:
        """Send a command to the router"""
        if not self.socket:
            raise Exception("Not connected to router")
        
        # Build command string
        cmd = command
        if params:
            for key, value in params.items():
                cmd += f"\n={key}={self.escape_value(value)}"
        cmd += "\n"
        
        # Send command
        self.socket.sendall(cmd.encode('utf-8'))
        
        # Read response
        response = []
        self.socket.settimeout(30)
        
        try:
            while True:
                chunk = self.socket.recv(4096)
                if not chunk:
                    break
                    
                # Decode and split
                data = chunk.decode('utf-8', errors='replace')
                lines = data.split('\n')
                
                for line in lines:
                    line = line.strip()
                    if line:
                        response.append(line)
                        if line in [self.OK, self.TRAP, self.FATAL]:
                            break
                            
                if response and response[-1] in [self.OK, self.TRAP, self.FATAL]:
                    break
                    
        except socket.timeout:
            pass
        
        return response
    
    def parse_response(self, response: List[str]) -> List[Dict[str, str]]:
        """Parse API response into structured data"""
        data = []
        current = {}
        
        for line in response:
            if line in [self.OK, self.RE, self.TRAP, self.FATAL]:
                if current:
                    data.append(current)
                    current = {}
                continue
            
            if line.startswith('='):
                # Parse attribute
                parts = line[1:].split('=', 1)
                if len(parts) == 2:
                    key = parts[0]
                    value = parts[1]
                    current[key] = value
        
        if current:
            data.append(current)
        
        return data
    
    def query(self, command: str, params: Dict[str, str] = None) -> List[Dict[str, str]]:
        """Run a query and return parsed results"""
        response = self.send_command(command, params)
        return self.parse_response(response)
    
    def escape_value(self, value: Any) -> str:
        """Escape special characters in values"""
        if value is None:
            return ''
        return str(value).replace('\\', '\\\\').replace('"', '\\"').replace('\n', 'n').replace('\r', 'r')
    
    def disconnect(self):
        """Disconnect from the router"""
        if self.socket:
            try:
                self.socket.close()
            except Exception:
                pass
            self.socket = None
    
    def __enter__(self):
        """Context manager entry"""
        self.connect()
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        """Context manager exit"""
        self.disconnect()
    
    def __del__(self):
        """Destructor"""
        self.disconnect()