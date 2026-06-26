import serial
import requests
import time
import socket
import threading
import mysql.connector # Run: pip install mysql-connector-python
from datetime import datetime

SERIAL_PORT = 'COM10'
BAUD_RATE = 9600
PHP_INSERT_URL = "http://localhost/floodAlert/insert_reading.php"

# DB credentials for direct background monitoring tracking
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'floodmind kelani'
}

# Runtime Automation Engine Controls
automation_enabled = True
evaluation_interval = 600 # Default to 10 minutes (600 seconds)
last_global_evaluation = 0

print("[*] Initializing FloodMind Advanced Matrix & Socket Engine...")
try:
    ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=1)
    time.sleep(2)
    print(f"[+] Serial Matrix Lock Confirmed on {SERIAL_PORT}")
except Exception as e:
    print(f"[-] Serial Failure: {e}")
    exit()

def send_arduino_command(cmd):
    try:
        ser.write((cmd + "\n").encode('utf-8'))
        print(f"[HARDWARE LINK OUT] -> {cmd}")
    except Exception as e:
        print(f"[-] Write Error: {e}")

# Thread 1: Listens for incoming UI socket directives from gateway_handler.php
def socket_server_loop():
    global automation_enabled, evaluation_interval
    
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        server.bind(('127.0.0.1', 65432))
        server.listen(5)
        print("[+] TCP Socket Server Listening on Port 65432...")
    except Exception as e:
        print(f"[-] Failed to bind socket server: {e}")
        return

    while True:
        try:
            conn, addr = server.accept()
            raw_data = conn.recv(1024).decode('utf-8').strip()
            if not raw_data:
                conn.close()
                continue
            
            print(f"[SOCKET IN] Command: {raw_data}")
            response_msg = "ACK"

            if raw_data == "POLL_ALL":
                send_arduino_command("POLL_ALL")
                response_msg = "Instruction routed: Polling all nodes."
                
            elif raw_data == "TOGGLE_AUTO":
                automation_enabled = not automation_enabled
                status_str = "ENABLED" if automation_enabled else "DISABLED"
                print(f"[SYSTEM STATE] Background automation interval tracking is now {status_str}")
                response_msg = f"Automation tracking set to {status_str}"
                
            elif raw_data.startswith("SET_INTERVAL:"):
                try:
                    seconds = int(raw_data.split(":")[1])
                    evaluation_interval = seconds
                    print(f"[SYSTEM STATE] Interval frequency dynamic shift: {evaluation_interval} seconds")
                    response_msg = f"System base interval shifted to {evaluation_interval}s"
                except ValueError:
                    response_msg = "Error: Invalid interval calculation integer value"
                    
            elif raw_data.startswith("POLL_STATION:"):
                station_id = raw_data.split(":")[1]
                send_arduino_command(f"POLL_STATION:{station_id}")
                response_msg = f"Instruction routed: Polling Node ID {station_id}"

            conn.send((response_msg + "\n").encode('utf-8'))
            conn.close()
        except Exception as socket_err:
            print(f"[-] Socket Connection Handling Runtime Exception: {socket_err}")

# Thread 2: Handles scheduled datetime matrix jobs and background interval cycles
def matrix_scheduler_loop():
    global last_global_evaluation, automation_enabled, evaluation_interval
    print("[+] Core Matrix Cron & Fixed Interval Thread Operational.")
    
    while True:
        current_time = time.time()
        
        # 1. Handle targeted datetime schedules from the database
        try:
            db = mysql.connector.connect(**DB_CONFIG)
            cursor = db.cursor(dictionary=True)
            
            now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            query = "SELECT * FROM scheduled_jobs WHERE target_execution <= %s AND is_executed = 0"
            cursor.execute(query, (now_str,))
            pending_jobs = cursor.fetchall()
            
            for job in pending_jobs:
                print(f"\n[CRON EVENT DETECTED] Processing Job #{job['id']} for targets: {job['station_ids']}")
                station_list = job['station_ids'].split(',')
                
                for s_id in station_list:
                    if s_id.strip():
                        send_arduino_command(f"POLL_STATION:{s_id.strip()}")
                        time.sleep(0.5) # Inter-hardware line rest interval
                
                update_query = "UPDATE scheduled_jobs SET is_executed = 1 WHERE id = %s"
                cursor.execute(update_query, (job['id'],))
                db.commit()
                
            cursor.close()
            db.close()
        except Exception as err:
            print(f"[-] Background Cron Error: {err}")

        # 2. Handle the dynamic background evaluation tracking loop (1 min, 5 min, etc.)
        if automation_enabled and (current_time - last_global_evaluation >= evaluation_interval):
            print(f"\n[INTERVAL TRIGGER] Executing automated scan pattern. Loop frame setting: {evaluation_interval}s")
            send_arduino_command("POLL_ALL")
            last_global_evaluation = current_time
            
        time.sleep(2) # Evaluates states every 2 seconds for pinpoint UI responsiveness

# Boot background operations threads
threading.Thread(target=socket_server_loop, daemon=True).start()
threading.Thread(target=matrix_scheduler_loop, daemon=True).start()

# Main Thread: Standard inbound Serial parsing engine structure
while True:
    try:
        if ser.in_waiting > 0:
            raw_line = ser.readline().decode('utf-8', errors='ignore').strip()
            if "DATA:STATION=" in raw_line:
                try:
                    parts = raw_line.split(",")
                    station_id = parts[0].split("=")[1]
                    water_level = parts[1].split("=")[1]
                    
                    print(f"[INCOMING PACKET] Station: {station_id} -> {water_level} cm")
                    res = requests.post(PHP_INSERT_URL, data={'station_id': station_id, 'water_level': water_level})
                except Exception as inner:
                    print(f"[-] Parse crash error: {inner}")
        time.sleep(0.02)
    except KeyboardInterrupt:
        print("\n[-] Shutting down Core Ingestion Engine safely...")
        ser.close()
        break