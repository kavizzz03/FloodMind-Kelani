#include <Arduino.h>

// Configurations for 7 Stations
const int NUM_STATIONS = 7;
// Trigger and Echo pins for HC-SR04 sensors [Station 1 to 7]
const int trigPins[NUM_STATIONS] = {2, 4, 6, 8, 10, 12, A0};
const int echoPins[NUM_STATIONS] = {3, 5, 7, 9, 11, 13, A1};
// Status output pins for severity or connectivity tracking
const int statusPins[NUM_STATIONS] = {22, 23, 24, 25, 26, 27, 28};

unsigned long lastSampleTime = 0;
unsigned long sampleInterval = 600000; // Default: 10 minutes (600000 ms)
bool autoScheduling = true;

float readUltrasonic(int index) {
  digitalWrite(trigPins[index], LOW);
  delayMicroseconds(2);
  digitalWrite(trigPins[index], HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPins[index], LOW);
  
  long duration = pulseIn(echoPins[index], HIGH, 30000); // 30ms timeout
  if (duration == 0) return -1.0; // Error indicator
  
  // Convert to distance (cm) then parse to meters/feet based on your structural rules
  float distanceCm = (duration * 0.0343) / 2;
  return distanceCm; 
}

void transmitData(int stationId, float level) {
  // Sends data formatted cleanly over Serial interface to your gateway machine
  Serial.print("DATA:");
  Serial.print(stationId);
  Serial.print(",");
  Serial.println(level);
}

void pollStation(int stationIndex) {
  float currentLevel = readUltrasonic(stationIndex);
  if (currentLevel != -1.0) {
    transmitData(stationIndex + 1, currentLevel); // Station IDs are 1-indexed in DB
    digitalWrite(statusPins[stationIndex], HIGH);  // Visual confirmation blink
    delay(100);
    digitalWrite(statusPins[stationIndex], LOW);
  }
}

void setup() {
  Serial.begin(9600);
  for(int i = 0; i < NUM_STATIONS; i++) {
    pinMode(trigPins[i], OUTPUT);
    pinMode(echoPins[i], INPUT);
    pinMode(statusPins[i], OUTPUT);
  }
}

void loop() {
  // 1. Handle Automatic Scheduled Sampling
  if (autoScheduling && (millis() - lastSampleTime >= sampleInterval)) {
    for(int i = 0; i < NUM_STATIONS; i++) {
      pollStation(i);
      delay(500); // Small cooldown gap between adjacent sensor firings
    }
    lastSampleTime = millis();
  }

  // 2. Handle Manual Inbound Commands via Web UI Gateway
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    
    if (command == "POLL_ALL") {
      for(int i = 0; i < NUM_STATIONS; i++) { pollStation(i); delay(200); }
    } 
    else if (command.startsWith("POLL_STATION:")) {
      int targetStation = command.substring(13).toInt();
      if (targetStation >= 1 && targetStation <= NUM_STATIONS) {
        pollStation(targetStation - 1);
      }
    }
    else if (command.startsWith("SET_INTERVAL:")) {
      long newIntervalSec = command.substring(13).toInt();
      sampleInterval = newIntervalSec * 1000;
    }
  }
}