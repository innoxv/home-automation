from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, validator
from typing import Optional
import serial
import time
import glob
import sys

app = FastAPI(title="Smart Bulb Control API - 3 Bulbs with Voice")

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global state for 3 bulbs
current_state = {
    "bulb1": {"state": "off", "brightness": 0, "pin": 9},
    "bulb2": {"state": "off", "brightness": 0, "pin": 10},
    "bulb3": {"state": "off", "brightness": 0, "pin": 5},
    "mode": "manual",
    "strobe_speed": 2,
    "connected": False
}

# Serial connection
arduino = None

class BulbCommand(BaseModel):
    bulb: int
    action: str
    value: Optional[int] = None
    
    @validator('value', pre=True)
    def convert_value(cls, v):
        if v is None or v == '':
            return None
        try:
            return int(v)
        except (ValueError, TypeError):
            return None

class EffectCommand(BaseModel):
    effect: str
    speed: Optional[int] = None
    
    @validator('speed', pre=True)
    def convert_speed(cls, v):
        if v is None or v == '':
            return None
        try:
            return int(v)
        except (ValueError, TypeError):
            return None

class GroupCommand(BaseModel):
    action: str
    brightness: Optional[int] = None
    
    @validator('brightness', pre=True)
    def convert_brightness(cls, v):
        if v is None or v == '':
            return None
        try:
            return int(v)
        except (ValueError, TypeError):
            return None
        
class VoiceCommand(BaseModel):
    command: str
    bulb: Optional[int] = None
    action: Optional[str] = None
    value: Optional[int] = None
    effect: Optional[str] = None

def get_available_ports():
    """Find all available serial ports"""
    ports = []
    
    # Platform-specific port detection
    if sys.platform.startswith('win'):
        # Windows
        ports = [f'COM{i}' for i in range(1, 257)]
    elif sys.platform.startswith('linux'):
        # Linux
        patterns = ['/dev/ttyACM*', '/dev/ttyUSB*', '/dev/ttyS*']
        for pattern in patterns:
            ports.extend(glob.glob(pattern))
    elif sys.platform.startswith('darwin'):
        # Mac OS
        patterns = ['/dev/tty.usb*', '/dev/tty.usbserial*']
        for pattern in patterns:
            ports.extend(glob.glob(pattern))
    
    # Remove duplicates and sort
    ports = list(set(ports))
    ports.sort()
    
    return ports

def connect_arduino():
    """Connect to Arduino - dynamically find the correct port"""
    global arduino, current_state
    
    # Get available ports
    available_ports = get_available_ports()
    print(f"🔍 Available serial ports: {available_ports}")
    
    if not available_ports:
        print("❌ No serial ports found!")
        current_state["connected"] = False
        return False
    
    # Try each available port
    for port in available_ports:
        print(f"🔌 Attempting connection on {port}...")
        
        try:
            # Try to connect
            arduino = serial.Serial(
                port=port,
                baudrate=9600,
                timeout=1,
                write_timeout=1,
                bytesize=serial.EIGHTBITS,
                parity=serial.PARITY_NONE,
                stopbits=serial.STOPBITS_ONE
            )
            
            # Give Arduino time to reset
            time.sleep(2)
            
            # Clear buffers
            arduino.flushInput()
            arduino.flushOutput()
            
            # Send newline to trigger response
            arduino.write(b"\n")
            arduino.flush()
            time.sleep(1)
            
            # Read startup messages (Arduino sends multiple lines)
            all_responses = []
            start_time = time.time()
            
            while time.time() - start_time < 2:
                if arduino.in_waiting:
                    line = arduino.readline().decode('utf-8', errors='ignore').strip()
                    if line:
                        all_responses.append(line)
                        print(f"   📨 Line: {line}")
                else:
                    time.sleep(0.05)
            
            # Check if we got Arduino startup messages
            arduino_detected = False
            for response in all_responses:
                response_upper = response.upper()
                arduino_indicators = [
                    'SMART_BULBS', 'VOICE_READY', 'VOICE:READY',
                    'READY', 'HELLO', 'LIGHT', 'CONTROL',
                    'OK:', 'PONG', 'VOICE_ACTIVE'
                ]
                
                for indicator in arduino_indicators:
                    if indicator in response_upper:
                        print(f"✅✅✅ Arduino identified on {port} (matched '{indicator}')")
                        current_state["connected"] = True
                        arduino_detected = True
                        break
                
                if arduino_detected:
                    break
            
            if arduino_detected:
                # Get initial status
                time.sleep(0.1)
                arduino.write(b"STATUS\n")
                arduino.flush()
                return True
            
            # Try PING if startup messages didn't work
            print("   🔄 Trying PING command...")
            arduino.write(b"PING\n")
            arduino.flush()
            time.sleep(0.5)
            
            if arduino.in_waiting:
                response = arduino.readline().decode('utf-8', errors='ignore').strip()
                print(f"   📨 PING response: {response}")
                
                if response and ("PONG" in response.upper() or "VOICE_ACTIVE" in response.upper()):
                    print(f"✅✅✅ Arduino identified on {port} via PING")
                    current_state["connected"] = True
                    return True
            
            # Close and try next port
            arduino.close()
            arduino = None
            
        except PermissionError:
            print(f"   ❌ Permission denied on {port}")
            continue
        except serial.SerialException as e:
            print(f"   ❌ Serial error on {port}: {e}")
            continue
        except Exception as e:
            print(f"   ❌ Error on {port}: {e}")
            continue
    
    print("❌ Could not connect to Arduino on any available port")
    current_state["connected"] = False
    arduino = None
    return False

def get_arduino_connection():
    """Get or create Arduino connection with retry"""
    global arduino
    
    if arduino is not None and current_state["connected"]:
        try:
            # Quick test
            arduino.write(b"\n")
            time.sleep(0.1)
            return arduino
        except:
            current_state["connected"] = False
            arduino = None
    
    # Try to reconnect
    if connect_arduino():
        return arduino
    
    return None

def send_to_arduino(cmd):
    """Send command to Arduino with auto-reconnect"""
    max_retries = 2
    
    for attempt in range(max_retries):
        try:
            arduino_conn = get_arduino_connection()
            if arduino_conn is None:
                print(f"⚠️ Arduino not connected (attempt {attempt + 1}/{max_retries})")
                if attempt < max_retries - 1:
                    time.sleep(1)
                    continue
                return None
            
            print(f"📨 Sending: {cmd}")
            
            # Clear input buffer
            arduino_conn.flushInput()
            
            # Send command
            arduino_conn.write(f"{cmd}\n".encode())
            arduino_conn.flush()
            
            # Wait for response (voice takes time)
            time.sleep(0.5)
            
            # Read response
            response_lines = []
            start_time = time.time()
            timeout = 2.0
            
            while time.time() - start_time < timeout:
                if arduino_conn.in_waiting:
                    line = arduino_conn.readline().decode('utf-8', errors='ignore').strip()
                    if line:
                        print(f"📨 Raw line: {line}")
                        
                        # Skip "CMD: ..." lines
                        if line.startswith("CMD:"):
                            continue
                        
                        response_lines.append(line)
                        
                        # Stop if we got a response marker
                        if any(line.startswith(prefix) for prefix in ["OK:", "ERROR:", "STATUS:", "EFFECT:", "PONG", "STROBE_SPEED:"]):
                            break
                else:
                    time.sleep(0.01)
            
            if response_lines:
                response = " | ".join(response_lines)
                print(f"📨 Final response: {response}")
                
                # Update state
                if cmd.upper() == "STATUS":
                    parse_status(response)
                elif cmd.upper() in ["B1 ON", "B1 OFF", "B2 ON", "B2 OFF", "B3 ON", "B3 OFF", "ALL ON", "ALL OFF"]:
                    update_state_from_command(cmd.upper())
                elif cmd.upper().startswith("STROBE SPEED"):
                    # Update strobe speed
                    try:
                        parts = response.split(":")
                        if len(parts) > 1:
                            current_state["strobe_speed"] = int(parts[1])
                    except:
                        pass
                
                return response
            else:
                print(f"📨 No response for: {cmd}")
                return None
                
        except Exception as e:
            print(f"❌ Error sending command (attempt {attempt + 1}): {e}")
            current_state["connected"] = False
            arduino = None
            
            if attempt < max_retries - 1:
                print("🔄 Retrying connection...")
                time.sleep(1)
                continue
    
    return None

def update_state_from_command(cmd):
    """Update local state based on command"""
    cmd = cmd.upper()
    
    if cmd == "B1 ON":
        current_state["bulb1"]["state"] = "on"
        current_state["bulb1"]["brightness"] = 100
    elif cmd == "B1 OFF":
        current_state["bulb1"]["state"] = "off"
        current_state["bulb1"]["brightness"] = 0
    elif cmd == "B2 ON":
        current_state["bulb2"]["state"] = "on"
        current_state["bulb2"]["brightness"] = 100
    elif cmd == "B2 OFF":
        current_state["bulb2"]["state"] = "off"
        current_state["bulb2"]["brightness"] = 0
    elif cmd == "B3 ON":
        current_state["bulb3"]["state"] = "on"
        current_state["bulb3"]["brightness"] = 100
    elif cmd == "B3 OFF":
        current_state["bulb3"]["state"] = "off"
        current_state["bulb3"]["brightness"] = 0
    elif cmd == "ALL ON":
        for i in range(1, 4):
            current_state[f"bulb{i}"]["state"] = "on"
            current_state[f"bulb{i}"]["brightness"] = 100
    elif cmd == "ALL OFF":
        for i in range(1, 4):
            current_state[f"bulb{i}"]["state"] = "off"
            current_state[f"bulb{i}"]["brightness"] = 0

def parse_status(response):
    """Parse Arduino status response"""
    if response and ("STATUS:" in response):
        try:
            for line in response.split(" | "):
                if "STATUS:" in line:
                    parts = line.split(":")
                    if len(parts) >= 9:
                        # Bulb 1
                        b1_val = int(parts[2])
                        current_state["bulb1"]["brightness"] = int(b1_val / 2.55)
                        current_state["bulb1"]["state"] = "on" if b1_val > 0 else "off"
                        
                        # Bulb 2
                        b2_val = int(parts[4])
                        current_state["bulb2"]["brightness"] = int(b2_val / 2.55)
                        current_state["bulb2"]["state"] = "on" if b2_val > 0 else "off"
                        
                        # Bulb 3
                        b3_val = int(parts[6])
                        current_state["bulb3"]["brightness"] = int(b3_val / 2.55)
                        current_state["bulb3"]["state"] = "on" if b3_val > 0 else "off"
                        
                        # Mode
                        current_state["mode"] = parts[8].lower() if len(parts) > 8 else "manual"
                    break
        except Exception as e:
            print(f"⚠️ Error parsing status: {e}")

# ========== API ENDPOINTS ==========

@app.get("/")
def root():
    return {
        "service": "Smart Bulb Control with Voice",
        "version": "6.0",
        "bulbs": 3,
        "pins": {"bulb1": 9, "bulb2": 10, "bulb3": 5},
        "voice_feedback": True,
        "effects": ["strobe"],
        "endpoints": {
            "bulb_control": "POST /api/bulb",
            "effect": "POST /api/effect",
            "group": "POST /api/group",
            "status": "GET /api/status",
            "command": "POST /api/command",
            "voice": "POST /api/voice",
            "test": "GET /api/test"
        },
        "arduino_connected": current_state["connected"]
    }

@app.post("/api/command")
def send_command(cmd: str):
    """Send raw command to Arduino"""
    response = send_to_arduino(cmd)
    
    if response:
        return {
            "success": True,
            "command": cmd,
            "response": response,
            "state": current_state,
            "connected": current_state["connected"]
        }
    else:
        return {
            "success": False,
            "error": "Arduino not connected or no response",
            "state": current_state,
            "connected": current_state["connected"]
        }

@app.post("/api/bulb")
def control_bulb(command: BulbCommand):
    """Control individual bulb (1, 2, or 3)"""
    if command.bulb not in [1, 2, 3]:
        raise HTTPException(status_code=400, detail="Bulb must be 1, 2, or 3")
    
    bulb_key = f"bulb{command.bulb}"
    response = None
    
    if command.action == "on":
        response = send_to_arduino(f"B{command.bulb} ON")
        current_state["mode"] = "manual"
        
    elif command.action == "off":
        response = send_to_arduino(f"B{command.bulb} OFF")
        current_state["mode"] = "manual"
        
    elif command.action == "brightness":
        if command.value is None or not 0 <= command.value <= 100:
            raise HTTPException(status_code=400, detail="Brightness must be 0-100")
        
        pwm_value = int(command.value * 2.55)
        response = send_to_arduino(f"B{command.bulb} {pwm_value}")
        current_state["mode"] = "manual"
    
    else:
        raise HTTPException(status_code=400, detail="Action must be 'on', 'off', or 'brightness'")
    
    return {
        "success": True if response else False,
        "bulb": command.bulb,
        "action": command.action,
        "value": command.value,
        "response": response,
        "state": current_state[bulb_key],
        "connected": current_state["connected"]
    }

@app.post("/api/effect")
def control_effect(command: EffectCommand):
    """Start/stop effects"""
    response = None
    
    # Only strobe is supported
    if command.effect == "strobe":
        if command.speed:
            cmd = f"STROBE SPEED {command.speed}"
            current_state["strobe_speed"] = command.speed
        else:
            cmd = "START STROBE"
        response = send_to_arduino(cmd)
        current_state["mode"] = "strobe"
        
    elif command.effect == "stop":
        response = send_to_arduino("STOP")
        current_state["mode"] = "manual"
        
    else:
        raise HTTPException(status_code=400, detail="Only 'strobe' and 'stop' effects are supported")
    
    return {
        "success": True if response else False,
        "effect": command.effect,
        "speed": command.speed,
        "response": response,
        "mode": current_state["mode"],
        "connected": current_state["connected"]
    }

@app.post("/api/group")
def group_control(command: GroupCommand):
    """Control all bulbs together"""
    response = None
    
    if command.action == "on":
        response = send_to_arduino("ALL ON")
        current_state["mode"] = "manual"
        
    elif command.action == "off":
        response = send_to_arduino("ALL OFF")
        current_state["mode"] = "manual"
        
    elif command.action == "brightness":
        if command.brightness is None or not 0 <= command.brightness <= 100:
            raise HTTPException(status_code=400, detail="Brightness must be 0-100")
        
        pwm_value = int(command.brightness * 2.55)
        responses = []
        for i in range(1, 4):
            resp = send_to_arduino(f"B{i} {pwm_value}")
            if resp:
                responses.append(resp)
        
        response = " | ".join(responses) if responses else f"All bulbs set to {command.brightness}%"
        current_state["mode"] = "manual"
    
    else:
        raise HTTPException(status_code=400, detail="Action must be 'on', 'off', or 'brightness'")
    
    return {
        "success": True if response else False,
        "action": command.action,
        "brightness": command.brightness,
        "response": response,
        "state": current_state,
        "connected": current_state["connected"]
    }

@app.get("/api/status")
def get_status():
    """Get system status"""
    try:
        response = send_to_arduino("STATUS")
        if response:
            parse_status(response)
        
        return {
            "success": True,
            "state": current_state,
            "arduino_response": response,
            "connected": current_state["connected"],
            "timestamp": time.time()
        }
    except Exception as e:
        return {
            "success": False,
            "state": current_state,
            "error": str(e),
            "connected": False,
            "timestamp": time.time()
        }

@app.post("/api/voice")
def voice_command(command: VoiceCommand):
    """Handle voice commands"""
    try:
        response = None
        
        if command.command == "turn_on":
            if command.bulb:
                response = send_to_arduino(f"B{command.bulb} ON")
            else:
                response = send_to_arduino("ALL ON")
            
        elif command.command == "turn_off":
            if command.bulb:
                response = send_to_arduino(f"B{command.bulb} OFF")
            else:
                response = send_to_arduino("ALL OFF")
            
        elif command.command == "set_brightness":
            if command.value is not None and 0 <= command.value <= 100:
                pwm_value = int(command.value * 2.55)
                if command.bulb:
                    response = send_to_arduino(f"B{command.bulb} {pwm_value}")
                else:
                    responses = []
                    for i in range(1, 4):
                        resp = send_to_arduino(f"B{i} {pwm_value}")
                        if resp:
                            responses.append(resp)
                    response = " | ".join(responses) if responses else f"All bulbs set to {command.value}%"
                current_state["mode"] = "manual"
            else:
                raise HTTPException(status_code=400, detail="Brightness must be 0-100")
                
        elif command.command == "start_effect" and command.effect:
            if command.effect.lower() == "strobe":
                response = send_to_arduino("START STROBE")
                current_state["mode"] = "strobe"
            else:
                raise HTTPException(status_code=400, detail="Only 'strobe' effect is supported")
                
        elif command.command == "stop_effects":
            response = send_to_arduino("STOP")
            current_state["mode"] = "manual"
        
        else:
            raise HTTPException(status_code=400, detail="Unknown voice command")
        
        return {
            "success": True if response else False,
            "command": command.command,
            "response": response,
            "state": current_state,
            "connected": current_state["connected"]
        }
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/test")
def test_connection():
    """Test Arduino connection"""
    response = send_to_arduino("PING")
    
    if response:
        return {
            "success": True,
            "message": "Arduino is connected and responding",
            "response": response,
            "connected": True
        }
    else:
        return {
            "success": False,
            "message": "Arduino not responding",
            "connected": False
        }

if __name__ == "__main__":
    import uvicorn
    
    print("=" * 60)
    print("🚀 Smart Bulb Control API v6.0")
    print("📌 Bulb 3 on pin 5 | Speaker on pin 3")
    print("📌 Voice feedback enabled")
    print("=" * 60)
    
    # Try to connect to Arduino
    if connect_arduino():
        print("✅ Arduino connected with voice support!")
    else:
        print("⚠️  Arduino not connected - check connection")
    
    print(f"🌐 API: http://localhost:5000")
    print(f"📖 Docs: http://localhost:5000/docs")
    print("=" * 60)
    
    uvicorn.run(app, host="0.0.0.0", port=5000, log_level="info")