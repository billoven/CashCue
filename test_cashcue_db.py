#!/usr/bin/python3
"""
CashCue DB Connection Tester
Reads /etc/cashcue/cashcue.conf and tests MariaDB/MySQL connection
"""

import os
import sys
from dotenv import dotenv_values
import mysql.connector
from mysql.connector import Error

# Config file path
CONFIG_FILE = "/etc/cashcue/cashcue.conf"

# Check if config exists
if not os.path.isfile(CONFIG_FILE):
    print(f"❌ Config file {CONFIG_FILE} not found!")
    sys.exit(1)

# Load config
config = dotenv_values(CONFIG_FILE)

# Required keys
required_keys = ["DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASS"]
missing_keys = [k for k in required_keys if k not in config]
if missing_keys:
    print(f"❌ Missing keys in config: {missing_keys}")
    sys.exit(1)

# Connect to database
try:
    connection = mysql.connector.connect(
        host=config["DB_HOST"],
        port=int(config["DB_PORT"]),
        database=config["DB_NAME"],
        user=config["DB_USER"],
        password=config["DB_PASS"]
    )
    if connection.is_connected():
        db_info = connection.get_server_info()
        print(f"✅ Successfully connected to MariaDB/MySQL server version {db_info}")
        cursor = connection.cursor()
        cursor.execute("SELECT DATABASE();")
        record = cursor.fetchone()
        print(f"✅ Connected to database: {record[0]}")
except Error as e:
    print(f"❌ Error while connecting to MySQL: {e}")
finally:
    if 'connection' in locals() and connection.is_connected():
        cursor.close()
        connection.close()
        print("✅ MySQL connection closed.")
