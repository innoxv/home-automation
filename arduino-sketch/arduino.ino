// SMART BULB CONTROLLER with voice feedback
#include <Arduino.h>
#include "Talkie.h"
#include "Vocab_US_Large.h"

Talkie voice;

// Pin definitions for 3 bulbs
const int BULB1 = 9;
const int BULB2 = 10;
const int BULB3 = 5;

// Current state tracking
int bulb1Brightness = 0;
int bulb2Brightness = 0;
int bulb3Brightness = 0;
String currentMode = "MANUAL";
bool effectRunning = false;

// Voice feedback function
void speakBulbAction(int bulb, String action) {
    if (action == "ON") {
        if (bulb == 0) {
            // "All lights on" - better natural order
            voice.say(sp2_ALL);     // "all"
            delay(70);
            voice.say(sp2_LIGHT);   // "light" 
            delay(70);
            voice.say(sp2_ON);      // "on"
        } else {
            // "Light X on"
            voice.say(sp2_LIGHT);   // "light"
            delay(70);
            
            // Which bulb number
            if (bulb == 1) voice.say(sp2_ONE);
            else if (bulb == 2) voice.say(sp2_TWO);
            else if (bulb == 3) voice.say(sp2_THREE);
            
            delay(70);
            voice.say(sp2_ON);      // "on"
        }
    }
    else if (action == "OFF") {
        if (bulb == 0) {
            // "All lights off"
            voice.say(sp2_ALL);     // "all"
            delay(70);
            voice.say(sp2_LIGHT);   // "light"
            delay(70);
            voice.say(sp2_OFF);     // "off"
        } else {
            // "Light X off"
            voice.say(sp2_LIGHT);
            delay(70);
            
            if (bulb == 1) voice.say(sp2_ONE);
            else if (bulb == 2) voice.say(sp2_TWO);
            else if (bulb == 3) voice.say(sp2_THREE);
            
            delay(70);
            voice.say(sp2_OFF);
        }
    }
    else if (action == "ACK") {
        voice.say(sp2_TEST);  // short beep for acknowledgment
    }
    else if (action == "START") {
        voice.say(sp2_START);  // "start" for effects
    }
    else if (action == "STOP") {
        voice.say(sp2_STOP);   // "stop" for effects
    }
    delay(200);  // Allow speech to complete
}

void setup() {
    Serial.begin(9600);
    
    // Initialize pins
    pinMode(BULB1, OUTPUT);
    pinMode(BULB2, OUTPUT);
    pinMode(BULB3, OUTPUT);
    
    // Start with all bulbs off
    analogWrite(BULB1, 0);
    analogWrite(BULB2, 0);
    analogWrite(BULB3, 0);
    
    delay(1000);  // Wait for system to stabilize
    
    // Startup voice message
    voice.say(sp2_LIGHT);
    delay(100);
    voice.say(sp2_CONTROL);
    delay(100);
    voice.say(sp2_READY);
    
    // Signal ready to bridge
    Serial.println("SMART_BULBS_VOICE_READY");
}

// Process incoming serial commands
void processCommand(String cmd) {
    cmd.toUpperCase();
    Serial.print("CMD: ");
    Serial.println(cmd);
    
    // Individual bulb control
    if (cmd == "B1 ON") {
        analogWrite(BULB1, 255);
        bulb1Brightness = 255;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(1, "ON");
        Serial.println("OK:B1:ON");
    }
    else if (cmd == "B1 OFF") {
        analogWrite(BULB1, 0);
        bulb1Brightness = 0;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(1, "OFF");
        Serial.println("OK:B1:OFF");
    }
    else if (cmd == "B2 ON") {
        analogWrite(BULB2, 255);
        bulb2Brightness = 255;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(2, "ON");
        Serial.println("OK:B2:ON");
    }
    else if (cmd == "B2 OFF") {
        analogWrite(BULB2, 0);
        bulb2Brightness = 0;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(2, "OFF");
        Serial.println("OK:B2:OFF");
    }
    else if (cmd == "B3 ON") {
        analogWrite(BULB3, 255);
        bulb3Brightness = 255;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(3, "ON");
        Serial.println("OK:B3:ON");
    }
    else if (cmd == "B3 OFF") {
        analogWrite(BULB3, 0);
        bulb3Brightness = 0;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(3, "OFF");
        Serial.println("OK:B3:OFF");
    }
    
    // Group control
    else if (cmd == "ALL ON") {
        analogWrite(BULB1, 255);
        analogWrite(BULB2, 255);
        analogWrite(BULB3, 255);
        bulb1Brightness = 255;
        bulb2Brightness = 255;
        bulb3Brightness = 255;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(0, "ON");
        Serial.println("OK:ALL:ON");
    }
    else if (cmd == "ALL OFF") {
        analogWrite(BULB1, 0);
        analogWrite(BULB2, 0);
        analogWrite(BULB3, 0);
        bulb1Brightness = 0;
        bulb2Brightness = 0;
        bulb3Brightness = 0;
        currentMode = "MANUAL";
        effectRunning = false;
        speakBulbAction(0, "OFF");
        Serial.println("OK:ALL:OFF");
    }
    
    // Effects
    else if (cmd == "START STROBE") {
        currentMode = "STROBE";
        effectRunning = true;
        speakBulbAction(0, "START");
        Serial.println("EFFECT:STROBE:STARTED");
    }
    else if (cmd == "STOP") {
        currentMode = "MANUAL";
        effectRunning = false;
        analogWrite(BULB1, 0);
        analogWrite(BULB2, 0);
        analogWrite(BULB3, 0);
        speakBulbAction(0, "STOP");
        Serial.println("EFFECT:STOPPED");
    }
    
    // Brightness control
    else if (cmd.startsWith("B1 ")) {
        String valStr = cmd.substring(3);
        valStr.trim();
        if (valStr.length() > 0) {
            int val = valStr.toInt();
            val = constrain(val, 0, 255);
            bulb1Brightness = val;
            analogWrite(BULB1, val);
            currentMode = "MANUAL";
            effectRunning = false;
            speakBulbAction(1, "ACK");
            Serial.print("OK:B1:");
            Serial.println(val);
        }
    }
    else if (cmd.startsWith("B2 ")) {
        String valStr = cmd.substring(3);
        valStr.trim();
        if (valStr.length() > 0) {
            int val = valStr.toInt();
            val = constrain(val, 0, 255);
            bulb2Brightness = val;
            analogWrite(BULB2, val);
            currentMode = "MANUAL";
            effectRunning = false;
            speakBulbAction(2, "ACK");
            Serial.print("OK:B2:");
            Serial.println(val);
        }
    }
    else if (cmd.startsWith("B3 ")) {
        String valStr = cmd.substring(3);
        valStr.trim();
        if (valStr.length() > 0) {
            int val = valStr.toInt();
            val = constrain(val, 0, 255);
            bulb3Brightness = val;
            analogWrite(BULB3, val);
            currentMode = "MANUAL";
            effectRunning = false;
            speakBulbAction(3, "ACK");
            Serial.print("OK:B3:");
            Serial.println(val);
        }
    }
    
    // System commands
    else if (cmd == "STATUS") {
        Serial.print("STATUS:B1:");
        Serial.print(bulb1Brightness);
        Serial.print(":B2:");
        Serial.print(bulb2Brightness);
        Serial.print(":B3:");
        Serial.print(bulb3Brightness);
        Serial.print(":MODE:");
        Serial.println(currentMode);
    }
    else if (cmd == "PING" || cmd == "TEST") {
        speakBulbAction(0, "ACK");
        Serial.println("PONG:VOICE_ACTIVE");
    }
    
    // Unknown command
    else {
        Serial.print("ERROR:UNKNOWN:");
        Serial.println(cmd);
    }
}

// Simple strobe effect
void runStrobeEffect() {
    static unsigned long lastStrobe = 0;
    static bool strobeState = false;
    
    if (millis() - lastStrobe > 250) {  // 4Hz strobe
        strobeState = !strobeState;
        if (strobeState) {
            analogWrite(BULB1, 255);
            analogWrite(BULB2, 255);
            analogWrite(BULB3, 255);
        } else {
            analogWrite(BULB1, 0);
            analogWrite(BULB2, 0);
            analogWrite(BULB3, 0);
        }
        lastStrobe = millis();
    }
}

void loop() {
    // Run effect if active
    if (effectRunning && currentMode == "STROBE") {
        runStrobeEffect();
    }
    
    // Check for incoming commands
    if (Serial.available()) {
        String cmd = Serial.readStringUntil('\n');
        cmd.trim();
        if (cmd.length() > 0) {
            processCommand(cmd);
        }
    }
    
    delay(10);  // Small delay for stability
}

// external mic test - future update
// ignore this for now