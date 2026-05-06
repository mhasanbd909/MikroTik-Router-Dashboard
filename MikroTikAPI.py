"""
MikroTik RouterOS API Class (Python)

A Python class for connecting to MikroTik routers using the RouterOS API protocol.
"""

import socket
import hashlib
import base64
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
        self.logged_in = False
        
    def _write_length(self, length: int) -> bytes:
        """Encode length for RouterOS API protocol"""
        if length < 0x80:
            return bytes([length])
        else:
            # Multi-byte length encoding
            return bytes([
                0x80 | ((length >> 24) & 0x7F),
                (length >> 16) & 0xFF,
                (length >> 8) & 0xFF,
                length & 0xFF
            ])
    
    def _read_length(self, first_byte: int) -> int:
        """Decode length from RouterOS API protocol"""
        length = first_byte
        if length >= 0x80:
            # Read 3 more bytes
            remaining = self.socket.recv(3)
            length = ((length & 0x7F) << 24) | (remaining[0] << 16) | (remaining[1] << 8) | remaining[2]
        return length
    
    def connect(self) -> bool:
        """Connect to the MikroTik router"""
        try:
            self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            self.socket.settimeout(self.timeout)
            self.socket.connect((self.host, self.port))
            
            # Login
            if not self.login():
                self.disconnect()
                raise Exception("Login failed. Check username/password or API service is enabled.")
            
            return True
        except socket.timeout:
            raise Exception(f"Connection timeout. Could not reach {self.host}:{self.port}")
        except socket.error as e:
            raise Exception(f"Connection failed: {e}")
    
    def _send_word(self, word: str):
        """Send length-prefixed word"""
        word_bytes = word.encode('utf-8')
        self.socket.sendall(self._write_length(len(word_bytes)) + word_bytes)
    
    def _read_word(self) -> Optional[str]:
        """Read length-prefixed word"""
        try:
            first_byte = self.socket.recv(1)
            if not first_byte:
                return None
            
            length = self._read_length(first_byte[0])
            
            if length == 0:
                return ''
            
            # Read data
            data = b''
            while len(data) < length:
                chunk = self.socket.recv(length - len(data))
                if not chunk:
                    return None
                data += chunk
            
            return data.decode('utf-8', errors='replace')
        except socket.timeout:
            return None
    
    def login(self) -> bool:
        """Login to the router using RouterOS API protocol"""
        # Read tagline (MikroTik router sends version info)
        try:
            self.socket.settimeout(5)
            while True:
                word = self._read_word()
                if word is None or word == '' or (word and word.startswith('!')):
                    break
        except socket.timeout:
            pass
        
        # Send /login command
        self._send_word('/login')
        
        # Read challenge response
        self.socket.settimeout(10)
        challenge = None
        
        try:
            response = self._read_word()
            if response and '=ret=' in response:
                challenge = response.split('=ret=')[1]
        except socket.timeout:
            pass
        
        if challenge:
            # RouterOS v6 style: compute password hash
            try:
                hash_binary = base64.b64decode(challenge)
            except Exception:
                hash_binary = challenge.encode('utf-8')
            
            password_bytes = self.password.encode('utf-8')
            md5_input = b'\x00' + password_bytes + hash_binary
            password_hash = hashlib.md5(md5_input).digest()
            password_encoded = base64.b64encode(password_hash).decode('ascii')
            
            # Send login with hashed password
            self._send_word('/login')
            self._send_word(f'=name={self.user}')
            self._send_word(f'=password={password_encoded}')
            self._send_word('')
            
            # Read response
            try:
                response = self._read_word()
                if response and (response == '!done' or '=ret=' in response):
                    self.logged_in = True
                    return True
            except socket.timeout:
                pass
        else:
            # RouterOS v7 or plain login
            self._send_word('/login')
            self._send_word(f'=name={self.user}')
            self._send_word(f'=password={self.password}')
            self._send_word('')
            
            try:
                response = self._read_word()
                if response and response == '!done':
                    self.logged_in = True
                    return True
            except socket.timeout:
                pass
        
        # Try RouterOS v7 session token login
        self._send_word('/login')
        self._send_word(f'=name={self.user}')
        self._send_word(f'=password={self.password}')
        self._send_word('=_session=yes')
        self._send_word('')
        
        try:
            # Read responses until !done
            while True:
                response = self._read_word()
                if response == '!done':
                    self.logged_in = True
                    return True
                elif response is None:
                    break
        except socket.timeout:
            pass
        
        return False
    
    def send_command(self, command: str, params: Dict[str, str] = None) -> List[str]:
        """Send a command to the router"""
        if not self.socket:
            raise Exception("Not connected to router")
        
        # Send command word
        self._send_word(command)
        
        # Send parameter words
        if params:
            for key, value in params.items():
                self._send_word(f'={key}={self.escape_value(value)}')
        
        # End of command
        self._send_word('')
        
        # Read response
        response = []
        self.socket.settimeout(30)
        
        try:
            while True:
                word = self._read_word()
                if word is None:
                    break
                
                if word:
                    response.append(word)
                
                if word in [self.OK, self.TRAP, self.FATAL] or word is None:
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
            self.logged_in = False
    
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