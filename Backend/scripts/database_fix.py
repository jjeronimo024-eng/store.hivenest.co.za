#!/usr/bin/env python3
"""
Database Connection Fix Script for HiveNest
This script provides MySQL commands and troubleshooting steps to fix database access issues.
"""
import os
import sys
from dotenv import load_dotenv
sys.path.append(os.path.dirname(os.path.dirname(__file__)))

# Load environment variables
load_dotenv('../.env')

def get_current_ip():
    """Get the current public IP address"""
    import requests
    try:
        response = requests.get('https://ifconfig.me', timeout=5)
        return response.text.strip()
    except:
        return "Unable to detect IP"

def generate_mysql_commands():
    """Generate MySQL commands to fix database access"""
    current_ip = get_current_ip()
    db_user = os.getenv('DB_USER')
    db_name = os.getenv('DB_NAME')
    db_host = os.getenv('DB_HOST')
    
    print(f"""
🔧 HIVENEST DATABASE CONNECTION FIX
=====================================

❌ CURRENT ISSUE:
Access denied for user '{db_user}'@'{current_ip}' (using password: YES)

📋 MYSQL COMMANDS TO FIX (Run on MySQL server {db_host}):

1. Connect to MySQL as root or admin user:
   mysql -u root -p
   
2. Grant access for current IP ({current_ip}):
   GRANT ALL PRIVILEGES ON {db_name}.* TO '{db_user}'@'{current_ip}' IDENTIFIED BY 'YOUR_PASSWORD';
   
3. Or grant access from any IP (less secure but simpler for development):
   GRANT ALL PRIVILEGES ON {db_name}.* TO '{db_user}'@'%' IDENTIFIED BY 'YOUR_PASSWORD';
   
4. Flush privileges to apply changes:
   FLUSH PRIVILEGES;
   
5. Verify the user permissions:
   SELECT User, Host FROM mysql.user WHERE User = '{db_user}';
   SHOW GRANTS FOR '{db_user}'@'{current_ip}';

🛠️ ALTERNATIVE SOLUTIONS:

Option A: Update existing user permissions
   UPDATE mysql.user SET Host = '%' WHERE User = '{db_user}';
   FLUSH PRIVILEGES;

Option B: Create new user with proper permissions
   CREATE USER '{db_user}'@'{current_ip}' IDENTIFIED BY 'YOUR_PASSWORD';
   GRANT ALL PRIVILEGES ON {db_name}.* TO '{db_user}'@'{current_ip}';
   FLUSH PRIVILEGES;

Option C: Temporary allow all IPs (for testing only)
   GRANT ALL PRIVILEGES ON {db_name}.* TO '{db_user}'@'%';
   FLUSH PRIVILEGES;

🔍 TROUBLESHOOTING STEPS:

1. Check if user exists:
   SELECT User, Host FROM mysql.user WHERE User = '{db_user}';

2. Check current user permissions:
   SHOW GRANTS FOR '{db_user}'@'%';
   SHOW GRANTS FOR '{db_user}'@'localhost';

3. Check if MySQL allows remote connections:
   SHOW VARIABLES LIKE 'bind-address';
   
4. Check MySQL firewall/security settings

5. Test connection from command line:
   mysql -h {db_host} -u {db_user} -p {db_name}

📞 CONTACT DATABASE ADMINISTRATOR:
   Server: {db_host}
   Database: {db_name}  
   User: {db_user}
   Current IP: {current_ip}
   
   Request: Grant database access for IP {current_ip}
""")

if __name__ == "__main__":
    generate_mysql_commands()