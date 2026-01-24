from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, validator
from typing import Optional
import serial
import time
import threading
import glob
import os

app = FastAPI(title="Smart Bulb Control API")

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global state
current_state = {
    "bulb1": {"state": "off", "brightness": 0, "pin": 9},
    "bulb2": {"state": "off", "brightness": 0, "pin": 10},
    "mode": "manual",
    "strobe_speed": 2,
    "connected": False
}

# Serial connection
arduino = None
effect_thread = None
stop_effects = threading.Event()

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

def get_available_ports():
    """Find all available serial ports"""
    ports = []
    
    # Linux patterns
    patterns = ['/dev/ttyACM*', '/dev/ttyUSB*', '/dev/ttyS*', '/dev/ttyAMA*']
    
    for pattern in patterns:
        ports.extend(glob.glob(pattern))
    
    # Filter out duplicates and sort
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
            
            # Clear any existing data
            arduino.flushInput()
            arduino.flushOutput()
            
            # Test 1: Send a newline to trigger response
            arduino.write(b"\n")
            arduino.flush()
            time.sleep(0.5)
            
            # Read response
            if arduino.in_waiting:
                response = arduino.readline().decode('utf-8', errors='ignore').strip()
                print(f"   📨 Response to newline: {response}")
                
                # Check if this looks like an Arduino response
                if response and any(keyword in response.upper() for keyword in ['SMART_BULBS', 'STATUS:', 'OK:', 'READY', 'ARDUINO', 'PONG']):
                    print(f"✅✅✅ Arduino identified on {port}")
                    current_state["connected"] = True
                    return True
            
            # Test 2: Send STATUS command
            arduino.write(b"STATUS\n")
            arduino.flush()
            time.sleep(0.5)
            
            if arduino.in_waiting:
                response = arduino.readline().decode('utf-8', errors='ignore').strip()
                print(f"   📨 STATUS response: {response}")
                
                if response and ("STATUS:" in response or "OK:" in response):
                    print(f"✅✅✅ Arduino identified on {port}")
                    current_state["connected"] = True
                    return True
            
            # Test 3: Try reading anything
            time.sleep(0.5)
            if arduino.in_waiting:
                response = arduino.readline().decode('utf-8', errors='ignore').strip()
                print(f"   📨 Spontaneous data: {response}")
                
                if response:
                    print(f"✅✅✅ Arduino responding on {port}")
                    current_state["connected"] = True
                    return True
            
            # If we get here, close and try next port
            arduino.close()
            arduino = None
            
        except PermissionError:
            print(f"   ❌ Permission denied on {port}")
            print(f"   💡 Try: sudo chmod 666 {port}")
            continue
            
        except serial.SerialException as e:
            print(f"   ❌ Serial error on {port}: {e}")
            continue
            
        except Exception as e:
            print(f"   ❌ Error on {port}: {e}")
            continue
    
    print("❌ Could not connect to Arduino on any available port")
    print("💡 Check:")
    print("   1. Is Arduino connected and powered?")
    print("   2. Is the correct sketch uploaded?")
    print("   3. Try: sudo chmod 666 /dev/ttyACM*")
    current_state["connected"] = False
    arduino = None
    return False

def get_arduino_connection():
    """Get or create Arduino connection with retry"""
    global arduino
    
    # If we have a working connection, return it
    if arduino is not None and current_state["connected"]:
        try:
            # Quick test to see if connection is still alive
            arduino.write(b"\n")
            time.sleep(0.1)
            return arduino
        except:
            # Connection lost
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
                    time.sleep(1)  # Wait before retry
                    continue
                return None
            
            print(f"📨 Sending: {cmd}")
            arduino_conn.write(f"{cmd}\n".encode())
            arduino_conn.flush()
            
            # Wait for response (adjust based on command)
            if cmd.upper() == "STATUS":
                time.sleep(0.3)
            else:
                time.sleep(0.1)
            
            # Try to read response
            if arduino_conn.in_waiting:
                response = arduino_conn.readline().decode('utf-8', errors='ignore').strip()
                print(f"📨 Response: {response}")
                
                # Update state if STATUS command
                if cmd.upper() == "STATUS":
                    parse_status(response)
                
                return response
            else:
                # No response, but command might have succeeded
                print(f"📨 No response received for: {cmd}")
                
                # For simple commands, assume success
                simple_commands = ["B1 ON", "B1 OFF", "B2 ON", "B2 OFF", "BOTH ON", "BOTH OFF"]
                if cmd.upper() in simple_commands:
                    return f"OK:{cmd}"
                
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

def parse_status(response):
    """Parse Arduino status response"""
    if response and ("STATUS:" in response or "SMART_BULBS" in response or "OK:" in response):
        try:
            if "STATUS:" in response:
                parts = response.split(":")
                if len(parts) >= 7:
                    b1_val = int(parts[2])
                    b2_val = int(parts[4])
                    current_state["bulb1"]["brightness"] = int(b1_val / 2.55)
                    current_state["bulb1"]["state"] = "on" if b1_val > 0 else "off"
                    current_state["bulb2"]["brightness"] = int(b2_val / 2.55)
                    current_state["bulb2"]["state"] = "on" if b2_val > 0 else "off"
                    current_state["mode"] = parts[6].lower() if len(parts) > 6 else "manual"
            elif "SMART_BULBS" in response or "ARDUINO" in response:
                # Initial connection message
                current_state["connected"] = True
        except Exception as e:
            print(f"⚠️ Error parsing status: {e}")

# ========== API ENDPOINTS ==========

@app.get("/")
def root():
    return {
        "service": "Smart Bulb Control",
        "version": "3.0",
        "endpoints": {
            "bulb_control": "POST /api/bulb",
            "effect": "POST /api/effect",
            "group": "POST /api/group",
            "status": "GET /api/status",
            "command": "POST /api/command"
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
    """Control individual bulb"""
    # Stop any running effects first
    if effect_thread and effect_thread.is_alive():
        stop_effects.set()
        effect_thread.join(timeout=1)
        stop_effects.clear()
    
    if command.bulb not in [1, 2]:
        raise HTTPException(status_code=400, detail="Bulb must be 1 or 2")
    
    bulb_key = f"bulb{command.bulb}"
    response = None
    
    if command.action == "on":
        response = send_to_arduino(f"B{command.bulb} ON")
        current_state[bulb_key]["state"] = "on"
        current_state[bulb_key]["brightness"] = 100
        current_state["mode"] = "manual"
        
    elif command.action == "off":
        response = send_to_arduino(f"B{command.bulb} OFF")
        current_state[bulb_key]["state"] = "off"
        current_state[bulb_key]["brightness"] = 0
        current_state["mode"] = "manual"
        
    elif command.action == "brightness":
        if command.value is None or not 0 <= command.value <= 100:
            raise HTTPException(status_code=400, detail="Brightness must be 0-100")
        
        # Convert to PWM (0-255)
        pwm_value = int(command.value * 2.55)
        response = send_to_arduino(f"B{command.bulb} {pwm_value}")
        current_state[bulb_key]["brightness"] = command.value
        current_state[bulb_key]["state"] = "on" if command.value > 0 else "off"
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
    global effect_thread
    
    # Stop any running effects
    if effect_thread and effect_thread.is_alive():
        stop_effects.set()
        effect_thread.join(timeout=1)
        effect_thread = None
    
    stop_effects.clear()
    current_state["mode"] = command.effect
    
    if command.effect == "strobe":
        # Start strobe effect thread
        effect_thread = threading.Thread(
            target=strobe_effect, 
            args=(command.speed or 2,),
            daemon=True
        )
        effect_thread.start()
        response = "Strobe effect started"
        
    elif command.effect == "fade":
        # Start fade effect thread
        effect_thread = threading.Thread(
            target=fade_effect,
            daemon=True
        )
        effect_thread.start()
        response = "Fade effect started"
        
    elif command.effect == "pulse":
        # Start pulse effect thread
        effect_thread = threading.Thread(
            target=pulse_effect,
            daemon=True
        )
        effect_thread.start()
        response = "Pulse effect started"
        
    elif command.effect == "alternate":
        # Start alternate effect thread
        effect_thread = threading.Thread(
            target=alternate_effect,
            daemon=True
        )
        effect_thread.start()
        response = "Alternate effect started"
        
    elif command.effect == "rainbow":
        # Start rainbow effect thread
        effect_thread = threading.Thread(
            target=rainbow_effect,
            daemon=True
        )
        effect_thread.start()
        response = "Rainbow effect started"
        
    elif command.effect == "stop":
        # Send stop command to Arduino
        response = send_to_arduino("BOTH OFF")
        current_state["mode"] = "manual"
        current_state["bulb1"]["state"] = "off"
        current_state["bulb1"]["brightness"] = 0
        current_state["bulb2"]["state"] = "off"
        current_state["bulb2"]["brightness"] = 0
        stop_effects.set()
        response = "All effects stopped"
        
    else:
        raise HTTPException(status_code=400, detail="Invalid effect")
    
    return {
        "success": True,
        "effect": command.effect,
        "speed": command.speed,
        "response": response,
        "mode": current_state["mode"],
        "connected": current_state["connected"]
    }

@app.post("/api/group")
def group_control(command: GroupCommand):
    """Control both bulbs together"""
    # Stop any running effects
    if effect_thread and effect_thread.is_alive():
        stop_effects.set()
        effect_thread.join(timeout=1)
        stop_effects.clear()
    
    response = None
    
    if command.action == "on":
        response = send_to_arduino("BOTH ON")
        current_state["bulb1"]["state"] = "on"
        current_state["bulb1"]["brightness"] = 100
        current_state["bulb2"]["state"] = "on"
        current_state["bulb2"]["brightness"] = 100
        
    elif command.action == "off":
        response = send_to_arduino("BOTH OFF")
        current_state["bulb1"]["state"] = "off"
        current_state["bulb1"]["brightness"] = 0
        current_state["bulb2"]["state"] = "off"
        current_state["bulb2"]["brightness"] = 0
        
    elif command.action == "brightness":
        if command.brightness is None or not 0 <= command.brightness <= 100:
            raise HTTPException(status_code=400, detail="Brightness must be 0-100")
        
        pwm_value = int(command.brightness * 2.55)
        # Set both bulbs to same brightness
        response1 = send_to_arduino(f"B1 {pwm_value}")
        response2 = send_to_arduino(f"B2 {pwm_value}")
        
        current_state["bulb1"]["brightness"] = command.brightness
        current_state["bulb2"]["brightness"] = command.brightness
        current_state["bulb1"]["state"] = "on" if command.brightness > 0 else "off"
        current_state["bulb2"]["state"] = "on" if command.brightness > 0 else "off"
        response = f"Both set to {command.brightness}%"
    
    else:
        raise HTTPException(status_code=400, detail="Action must be 'on', 'off', or 'brightness'")
    
    current_state["mode"] = "manual"
    
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

# ========== EFFECT FUNCTIONS ==========

def strobe_effect(speed: int = 2):
    """Continuous strobe effect"""
    speed_map = {1: 0.5, 2: 0.25, 3: 0.1, 4: 0.05, 5: 0.025}
    delay = speed_map.get(speed, 0.25)
    
    while not stop_effects.is_set():
        send_to_arduino("BOTH ON")
        time.sleep(delay)
        if stop_effects.is_set():
            break
        send_to_arduino("BOTH OFF")
        time.sleep(delay)
    
    # Clean up
    send_to_arduino("BOTH OFF")

def fade_effect():
    """Continuous fade effect"""
    step = 5
    delay = 0.05
    
    while not stop_effects.is_set():
        # Fade bulb1 up, bulb2 down
        for i in range(0, 256, step):
            if stop_effects.is_set():
                break
            send_to_arduino(f"B1 {i}")
            send_to_arduino(f"B2 {255 - i}")
            time.sleep(delay)
        
        # Fade bulb1 down, bulb2 up
        for i in range(0, 256, step):
            if stop_effects.is_set():
                break
            send_to_arduino(f"B1 {255 - i}")
            send_to_arduino(f"B2 {i}")
            time.sleep(delay)
    
    # Clean up
    send_to_arduino("BOTH OFF")

def pulse_effect():
    """Continuous pulse/breathe effect"""
    step = 3
    delay = 0.03
    
    while not stop_effects.is_set():
        # Fade in
        for i in range(0, 256, step):
            if stop_effects.is_set():
                break
            send_to_arduino(f"B1 {i}")
            send_to_arduino(f"B2 {i}")
            time.sleep(delay)
        
        # Fade out
        for i in range(255, -1, -step):
            if stop_effects.is_set():
                break
            send_to_arduino(f"B1 {i}")
            send_to_arduino(f"B2 {i}")
            time.sleep(delay)
    
    # Clean up
    send_to_arduino("BOTH OFF")

def alternate_effect():
    """Continuous alternate blinking effect"""
    delay = 0.3
    
    while not stop_effects.is_set():
        send_to_arduino("B1 ON")
        send_to_arduino("B2 OFF")
        time.sleep(delay)
        
        if stop_effects.is_set():
            break
            
        send_to_arduino("B1 OFF")
        send_to_arduino("B2 ON")
        time.sleep(delay)
    
    # Clean up
    send_to_arduino("BOTH OFF")

def rainbow_effect():
    """Continuous rainbow color cycle effect"""
    colors = [
        (255, 0, 0),    # Red
        (255, 127, 0),  # Orange
        (255, 255, 0),  # Yellow
        (0, 255, 0),    # Green
        (0, 0, 255),    # Blue
        (75, 0, 130),   # Indigo
        (148, 0, 211)   # Violet
    ]
    
    while not stop_effects.is_set():
        for r, g, b in colors:
            if stop_effects.is_set():
                break
            
            # Use red component for bulb1, green for bulb2
            send_to_arduino(f"B1 {r}")
            send_to_arduino(f"B2 {g}")
            time.sleep(0.5)
    
    # Clean up
    send_to_arduino("BOTH OFF")

if __name__ == "__main__":
    import uvicorn
    print("🚀 Starting Smart Bulb Control API v3.0...")
    print("=" * 50)
    
    # Try to connect to Arduino
    connect_arduino()
    
    if current_state["connected"]:
        print("✅ System ready - Arduino connected!")
    else:
        print("⚠️  Arduino not connected - basic functions available")
    
    print("🌐 API available at: http://localhost:5000")
    print("=" * 50)
    
    uvicorn.run(app, host="0.0.0.0", port=5000, log_level="info")