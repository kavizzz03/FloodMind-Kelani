#include <Arduino.h>

const int NUM_STATIONS = 7;
// Trigger and Echo pins for HC-SR04 sensors
const int trigPins[NUM_STATIONS] = {2, 4, 6, 8, 10, 12, A0};
const int echoPins[NUM_STATIONS] = {3, 5, 7, 9, 11, 13, A1};
// Status output pins for threshold indication
const int statusPins[NUM_STATIONS] = {22, 23, 24, 25, 26, 27, 28};

float readUltrasonic(int index) {
  digitalWrite(trigPins[index], LOW);
  delayMicroseconds(2);
  digitalWrite(trigPins[index], HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPins[index], LOW);
  
  long duration = pulseIn(echoPins[index], HIGH, 30000); // 30ms timeout
  if (duration == 0) return -1.0; 
  
  // Distance calculation in centimeters
  float distanceCm = (duration * 0.0343) / 2;
  return distanceCm; 
}

void pollStation(int stationIndex) {
  float currentLevel = readUltrasonic(stationIndex);
  
  // Print structured output for Python to read easily
  Serial.print("DATA:STATION=");
  Serial.print(stationIndex + 1);
  Serial.print(",LEVEL=");
  Serial.println(currentLevel);
  
  // Blink status pin to confirm the action physically
  digitalWrite(statusPins[stationIndex], HIGH);
  delay(150);
  digitalWrite(statusPins[stationIndex], LOW);
}

void setup() {
  Serial.begin(9600);
  for(int i = 0; i < NUM_STATIONS; i++) {
    pinMode(trigPins[i], OUTPUT);
    pinMode(echoPins[i], INPUT);
    pinMode(statusPins[i], OUTPUT);
  }
  Serial.println("[SYSTEM] Arduino Telemetry Board Online on COM10.");
}

void loop() {
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
  }
}