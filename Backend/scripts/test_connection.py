"""
Enhanced database connection script with better error handling and retry logic
"""
import os
import time
import sys
from urllib.parse import quote_plus
from dotenv import load_dotenv
from sqlalchemy import create_engine, text
from sqlalchemy.orm import declarative_base
from sqlalchemy.orm import sessionmaker
from pathlib import Path

# Load environment variables
ROOT_DIR = Path(__file__).parent.parent
load_dotenv(ROOT_DIR / '.env')

# Database connection details
DB_HOST = os.getenv('DB_HOST', '198.251.89.205')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'hivenest_main')
DB_USER = os.getenv('DB_USER', 'hivenest_hostingsetup')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')
DB_CHARSET = os.getenv('DB_CHARSET', 'utf8mb4')

class DatabaseConnection:
    def __init__(self):
        self.engine = None
        self.SessionLocal = None
        self.Base = declarative_base()
        
    def create_connection(self, retry_attempts=3):
        """Create database connection with retry logic"""
        # URL-encode the password to handle special characters
        encoded_password = quote_plus(DB_PASSWORD)
        
        # Create database URL
        database_url = f"mysql+pymysql://{DB_USER}:{encoded_password}@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset={DB_CHARSET}"
        
        print(f"🔗 Attempting to connect to: mysql+pymysql://{DB_USER}:***@{DB_HOST}:{DB_PORT}/{DB_NAME}")
        
        for attempt in range(retry_attempts):
            try:
                # Create SQLAlchemy engine
                self.engine = create_engine(
                    database_url,
                    pool_size=10,
                    max_overflow=20,
                    pool_pre_ping=True,
                    pool_recycle=3600,
                    echo=False,
                    connect_args={
                        "connect_timeout": 10,
                        "read_timeout": 30,
                        "write_timeout": 30,
                    }
                )
                
                # Create session factory
                self.SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=self.engine)
                
                # Test connection
                if self.test_connection():
                    print(f"✅ Database connection successful on attempt {attempt + 1}")
                    return True
                    
            except Exception as e:
                print(f"❌ Connection attempt {attempt + 1} failed: {str(e)}")
                if attempt < retry_attempts - 1:
                    print(f"🔄 Retrying in 5 seconds...")
                    time.sleep(5)
        
        print(f"❌ All {retry_attempts} connection attempts failed")
        self._print_troubleshooting_info()
        return False
        
    def test_connection(self):
        """Test database connection"""
        try:
            if not self.engine:
                return False
                
            db = self.SessionLocal()
            result = db.execute(text("SELECT 1 as test"))
            row = result.fetchone()
            db.close()
            
            if row and row[0] == 1:
                print("✅ Database query test passed")
                return True
            else:
                print("❌ Database query test failed")
                return False
                
        except Exception as e:
            print(f"❌ Database connection test failed: {e}")
            return False
    
    def get_connection_info(self):
        """Get current connection information"""
        try:
            import requests
            current_ip = requests.get('https://ifconfig.me', timeout=5).text.strip()
        except:
            current_ip = "Unable to detect"
            
        return {
            'host': DB_HOST,
            'port': DB_PORT,
            'database': DB_NAME,
            'user': DB_USER,
            'current_ip': current_ip
        }
    
    def _print_troubleshooting_info(self):
        """Print troubleshooting information"""
        info = self.get_connection_info()
        
        print(f"""
🚨 DATABASE CONNECTION FAILED
===============================

Connection Details:
- Host: {info['host']}
- Port: {info['port']}
- Database: {info['database']}
- User: {info['user']}
- Current IP: {info['current_ip']}

🔧 REQUIRED MYSQL COMMANDS (Run on MySQL server):

1. Connect to MySQL as root:
   mysql -u root -p

2. Grant access for current IP:
   GRANT ALL PRIVILEGES ON {info['database']}.* TO '{info['user']}'@'{info['current_ip']}' IDENTIFIED BY 'YOUR_PASSWORD';

3. Or grant access from any IP (recommended):
   GRANT ALL PRIVILEGES ON {info['database']}.* TO '{info['user']}'@'%' IDENTIFIED BY 'YOUR_PASSWORD';

4. Flush privileges:
   FLUSH PRIVILEGES;

5. Verify user exists and has permissions:
   SELECT User, Host FROM mysql.user WHERE User = '{info['user']}';
   SHOW GRANTS FOR '{info['user']}'@'%';

📋 COMMON SOLUTIONS:

A) Update existing user to allow any IP:
   UPDATE mysql.user SET Host = '%' WHERE User = '{info['user']}';
   FLUSH PRIVILEGES;

B) Check if MySQL allows remote connections:
   SHOW VARIABLES LIKE 'bind-address';
   (Should not be 127.0.0.1 for remote access)

C) Test connection from command line:
   mysql -h {info['host']} -u {info['user']} -p {info['database']}

❗ ACTION REQUIRED:
Contact your database administrator to run these MySQL commands.
""")

def main():
    """Main function to test database connection"""
    print("🚀 HiveNest Database Connection Test")
    print("=" * 50)
    
    db = DatabaseConnection()
    
    if db.create_connection():
        print("🎉 Database connection successful!")
        print("✅ Ready to initialize database tables")
        
        # Test a few more operations
        try:
            info = db.get_connection_info()
            print(f"📊 Connected to {info['database']} on {info['host']}")
            
            # Try to run a simple query
            session = db.SessionLocal()
            result = session.execute(text("SHOW TABLES"))
            tables = result.fetchall()
            session.close()
            
            print(f"📋 Found {len(tables)} existing tables in database")
            if tables:
                print("   Tables:", [table[0] for table in tables])
            
        except Exception as e:
            print(f"⚠️  Warning: Could not query database info: {e}")
            
        return True
    else:
        print("❌ Database connection failed!")
        return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)