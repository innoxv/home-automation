// SMART BULB CONTROLLER - 3 Bulbs with Voice Support
// Pins: 9, 10, 11

const int BULB1 = 9;
const int BULB2 = 10;
const int BULB3 = 11;

int bulb1Brightness = 0;
int bulb2Brightness = 0;
int bulb3Brightness = 0;
String currentMode = "MANUAL";

void setup() {
  Serial.begin(9600);
  
  // Initialize pins
  pinMode(BULB1, OUTPUT);
  pinMode(BULB2, OUTPUT);
  pinMode(BULB3, OUTPUT);
  
  // Start with bulbs off
  analogWrite(BULB1, 0);
  analogWrite(BULB2, 0);
  analogWrite(BULB3, 0);
  
  // Small delay for USB
  delay(100);
  
  // Send ready message
  Serial.println("SMART_BULBS_VOICE_READY");
}

void loop() {
  // Check for commands
  if (Serial.available() > 0) {
    processSerialCommand();
  }
  
  // Small delay to prevent CPU overload
  delay(5);
}

void processSerialCommand() {
  String cmd = Serial.readStringUntil('\n');
  cmd.trim();
  
  if (cmd.length() == 0) {
    // Empty line - send ready status
    Serial.println("VOICE_READY");
    return;
  }
  
  // Convert to uppercase for command matching
  String cmdUpper = cmd;
  cmdUpper.toUpperCase();
  
  // ========== VOICE-FRIENDLY COMMANDS ==========
  
  // Voice command: "turn on bulb one"
  if (cmdUpper == "TURN ON BULB ONE" || cmdUpper == "BULB ONE ON" || cmdUpper == "LIGHT ONE ON") {
    bulb1Brightness = 255;
    analogWrite(BULB1, 255);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:B1:ON");
  }
  
  // Voice command: "turn off bulb one"
  else if (cmdUpper == "TURN OFF BULB ONE" || cmdUpper == "BULB ONE OFF" || cmdUpper == "LIGHT ONE OFF") {
    bulb1Brightness = 0;
    analogWrite(BULB1, 0);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:B1:OFF");
  }
  
  // Voice command: "turn on bulb two"
  else if (cmdUpper == "TURN ON BULB TWO" || cmdUpper == "BULB TWO ON" || cmdUpper == "LIGHT TWO ON") {
    bulb2Brightness = 255;
    analogWrite(BULB2, 255);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:B2:ON");
  }
  
  // Voice command: "turn off bulb two"
  else if (cmdUpper == "TURN OFF BULB TWO" || cmdUpper == "BULB TWO OFF" || cmdUpper == "LIGHT TWO OFF") {
    bulb2Brightness = 0;
    analogWrite(BULB2, 0);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:B2:OFF");
  }
  
  // Voice command: "turn on bulb three"
  else if (cmdUpper == "TURN ON BULB THREE" || cmdUpper == "BULB THREE ON" || cmdUpper == "LIGHT THREE ON") {
    bulb3Brightness = 255;
    analogWrite(BULB3, 255);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:B3:ON");
  }
  
  // Voice command: "turn off bulb three"
  else if (cmdUpper == "TURN OFF BULB THREE" || cmdUpper == "BULB THREE OFF" || cmdUpper == "LIGHT THREE OFF") {
    bulb3Brightness = 0;
    analogWrite(BULB3, 0);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:B3:OFF");
  }
  
  // Voice command: "all lights on"
  else if (cmdUpper == "ALL LIGHTS ON" || cmdUpper == "ALL BULBS ON" || cmdUpper == "TURN ON ALL LIGHTS") {
    bulb1Brightness = 255;
    bulb2Brightness = 255;
    bulb3Brightness = 255;
    analogWrite(BULB1, 255);
    analogWrite(BULB2, 255);
    analogWrite(BULB3, 255);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:ALL:ON");
  }
  
  // Voice command: "all lights off"
  else if (cmdUpper == "ALL LIGHTS OFF" || cmdUpper == "ALL BULBS OFF" || cmdUpper == "TURN OFF ALL LIGHTS") {
    bulb1Brightness = 0;
    bulb2Brightness = 0;
    bulb3Brightness = 0;
    analogWrite(BULB1, 0);
    analogWrite(BULB2, 0);
    analogWrite(BULB3, 0);
    currentMode = "MANUAL";
    Serial.println("VOICE_OK:ALL:OFF");
  }
  
  // ========== STANDARD COMMANDS (for API compatibility) ==========
  
  // Bulb 1
  else if (cmdUpper == "B1 ON") {
    bulb1Brightness = 255;
    analogWrite(BULB1, 255);
    currentMode = "MANUAL";
    Serial.println("OK:B1:ON:255");
  }
  else if (cmdUpper == "B1 OFF") {
    bulb1Brightness = 0;
    analogWrite(BULB1, 0);
    currentMode = "MANUAL";
    Serial.println("OK:B1:OFF:0");
  }
  
  // Bulb 2
  else if (cmdUpper == "B2 ON") {
    bulb2Brightness = 255;
    analogWrite(BULB2, 255);
    currentMode = "MANUAL";
    Serial.println("OK:B2:ON:255");
  }
  else if (cmdUpper == "B2 OFF") {
    bulb2Brightness = 0;
    analogWrite(BULB2, 0);
    currentMode = "MANUAL";
    Serial.println("OK:B2:OFF:0");
  }
  
  // Bulb 3
  else if (cmdUpper == "B3 ON") {
    bulb3Brightness = 255;
    analogWrite(BULB3, 255);
    currentMode = "MANUAL";
    Serial.println("OK:B3:ON:255");
  }
  else if (cmdUpper == "B3 OFF") {
    bulb3Brightness = 0;
    analogWrite(BULB3, 0);
    currentMode = "MANUAL";
    Serial.println("OK:B3:OFF:0");
  }
  
  // ========== BRIGHTNESS CONTROL (0-255) ==========
  
  else if (cmdUpper.startsWith("B1 ")) {
    String valStr = cmd.substring(3);
    valStr.trim();
    
    if (valStr.length() > 0) {
      int val = valStr.toInt();
      val = constrain(val, 0, 255);
      bulb1Brightness = val;
      analogWrite(BULB1, val);
      currentMode = "MANUAL";
      Serial.print("OK:B1:");
      Serial.println(val);
    } else {
      Serial.println("ERROR:B1:INVALID_VALUE");
    }
  }
  else if (cmdUpper.startsWith("B2 ")) {
    String valStr = cmd.substring(3);
    valStr.trim();
    
    if (valStr.length() > 0) {
      int val = valStr.toInt();
      val = constrain(val, 0, 255);
      bulb2Brightness = val;
      analogWrite(BULB2, val);
      currentMode = "MANUAL";
      Serial.print("OK:B2:");
      Serial.println(val);
    } else {
      Serial.println("ERROR:B2:INVALID_VALUE");
    }
  }
  else if (cmdUpper.startsWith("B3 ")) {
    String valStr = cmd.substring(3);
    valStr.trim();
    
    if (valStr.length() > 0) {
      int val = valStr.toInt();
      val = constrain(val, 0, 255);
      bulb3Brightness = val;
      analogWrite(BULB3, val);
      currentMode = "MANUAL";
      Serial.print("OK:B3:");
      Serial.println(val);
    } else {
      Serial.println("ERROR:B3:INVALID_VALUE");
    }
  }
  
  // ========== GROUP CONTROL ==========
  
  else if (cmdUpper == "ALL ON") {
    bulb1Brightness = 255;
    bulb2Brightness = 255;
    bulb3Brightness = 255;
    analogWrite(BULB1, 255);
    analogWrite(BULB2, 255);
    analogWrite(BULB3, 255);
    currentMode = "MANUAL";
    Serial.println("OK:ALL:ON");
  }
  
  else if (cmdUpper == "ALL OFF") {
    bulb1Brightness = 0;
    bulb2Brightness = 0;
    bulb3Brightness = 0;
    analogWrite(BULB1, 0);
    analogWrite(BULB2, 0);
    analogWrite(BULB3, 0);
    currentMode = "MANUAL";
    Serial.println("OK:ALL:OFF");
  }
  
  // ========== STATUS COMMAND ==========
  
  else if (cmdUpper == "STATUS") {
    Serial.print("STATUS:B1:");
    Serial.print(bulb1Brightness);
    Serial.print(":B2:");
    Serial.print(bulb2Brightness);
    Serial.print(":B3:");
    Serial.print(bulb3Brightness);
    Serial.print(":MODE:");
    Serial.println(currentMode);
  }
  
  // ========== VOICE STATUS ==========
  
  else if (cmdUpper == "VOICE STATUS") {
    Serial.println("VOICE_ACTIVE:3_BULBS");
  }
  
  // ========== TEST/HELP ==========
  
  else if (cmdUpper == "PING" || cmdUpper == "TEST") {
    Serial.println("PONG:VOICE_ACTIVE");
  }
  else if (cmdUpper == "HELP" || cmdUpper == "?") {
    Serial.println("=== VOICE COMMANDS SUPPORTED ===");
    Serial.println("TURN ON BULB ONE/TWO/THREE");
    Serial.println("TURN OFF BULB ONE/TWO/THREE");
    Serial.println("ALL LIGHTS ON/OFF");
    Serial.println("=== API COMMANDS ===");
    Serial.println("B1/B2/B3 ON/OFF");
    Serial.println("B1/B2/B3 <0-255>");
    Serial.println("ALL ON/OFF");
    Serial.println("STATUS");
    Serial.println("================================");
  }
  
  // ========== UNKNOWN COMMAND ==========
  
  else {
    Serial.print("ERROR:UNKNOWN:");
    Serial.println(cmd);
  }
}