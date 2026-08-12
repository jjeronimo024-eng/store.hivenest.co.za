#!/usr/bin/env python3
"""
HiveNest Database Management Tool
Handles MySQL connection issues and provides SQLite fallback
"""
import os
import sys
import shutil
from pathlib import Path

# Add current directory to Python path
sys.path.append(str(Path(__file__).parent.parent))

def check_mysql_connection():
    """Check if MySQL connection is working"""
    try:
        from models.database import test_connection
        return test_connection()
    except Exception as e:
        print(f"❌ MySQL connection check failed: {e}")
        return False

def setup_sqlite_fallback():
    """Set up SQLite as fallback database"""
    try:
        print("🔄 Setting up SQLite fallback database...")
        
        # Backup original database.py
        db_file = Path(__file__).parent.parent / 'models' / 'database.py'
        backup_file = Path(__file__).parent.parent / 'models' / 'database_mysql_backup.py'
        
        if not backup_file.exists():
            shutil.copy(db_file, backup_file)
            print("💾 Backed up original MySQL database.py")
        
        # Create SQLite version of database.py
        sqlite_db_content = '''"""
SQLite Database connection for HiveNest (Development fallback)
"""
import os
from dotenv import load_dotenv
from sqlalchemy import create_engine, text
from sqlalchemy.orm import declarative_base, sessionmaker
from pathlib import Path

# Load environment variables
ROOT_DIR = Path(__file__).parent.parent
load_dotenv(ROOT_DIR / '.env')

# SQLite Database path
DB_PATH = ROOT_DIR / "hivenest_development.db"
DATABASE_URL = f"sqlite:///{DB_PATH}"

print(f"Using SQLite database: {DB_PATH}")

# Create SQLAlchemy engine for SQLite
engine = create_engine(
    DATABASE_URL,
    echo=False,
    connect_args={"check_same_thread": False}
)

# Create session factory
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Base class for models
Base = declarative_base()

def get_db():
    """
    Dependency to get database session
    """
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

def init_db():
    """
    Initialize database tables
    """
    try:
        # Import all models here to ensure they are registered
        from . import customer, product, order, service, admin, support
        
        # Create all tables
        Base.metadata.create_all(bind=engine)
        print("✅ SQLite database tables initialized")
        return True
    except Exception as e:
        print(f"❌ SQLite database initialization failed: {e}")
        return False
    
def test_connection():
    """
    Test database connection
    """
    try:
        db = SessionLocal()
        db.execute(text("SELECT 1"))
        db.close()
        return True
    except Exception as e:
        print(f"SQLite database connection failed: {e}")
        return False
'''
        
        # Write SQLite database.py
        db_file.write_text(sqlite_db_content)
        print("✅ Created SQLite database configuration")
        
        return True
        
    except Exception as e:
        print(f"❌ Failed to setup SQLite fallback: {e}")
        return False

def restore_mysql_config():
    """Restore MySQL database configuration"""
    try:
        db_file = Path(__file__).parent.parent / 'models' / 'database.py'
        backup_file = Path(__file__).parent.parent / 'models' / 'database_mysql_backup.py'
        
        if backup_file.exists():
            shutil.copy(backup_file, db_file)
            print("✅ Restored MySQL database configuration")
            return True
        else:
            print("❌ No MySQL backup found")
            return False
            
    except Exception as e:
        print(f"❌ Failed to restore MySQL config: {e}")
        return False

def test_database_setup():
    """Test the current database setup"""
    try:
        print("🧪 Testing database connection...")
        
        # Import after potential configuration changes
        import importlib
        import models.database
        importlib.reload(models.database)
        
        from models.database import test_connection, init_db
        
        if test_connection():
            print("✅ Database connection successful!")
            
            # Try to initialize database
            if init_db():
                print("✅ Database tables initialized!")
                return True
            else:
                print("⚠️  Database connected but table initialization failed")
                return False
        else:
            print("❌ Database connection failed")
            return False
            
    except Exception as e:
        print(f"❌ Database test failed: {e}")
        return False

def main():
    """Main database management function"""
    print("🛠️  HiveNest Database Management Tool")
    print("=" * 50)
    
    print("\n1️⃣  Testing MySQL connection...")
    
    if check_mysql_connection():
        print("🎉 MySQL connection is working!")
        print("✅ No action needed - using MySQL database")
        
        # Test full setup
        if test_database_setup():
            print("🚀 Database is ready for use!")
            return True
        else:
            print("⚠️  Database connection works but initialization failed")
            
    else:
        print("❌ MySQL connection failed")
        print("\n2️⃣  Setting up SQLite fallback...")
        
        if setup_sqlite_fallback():
            print("✅ SQLite fallback configured")
            
            print("\n3️⃣  Testing SQLite database...")
            if test_database_setup():
                print("🎉 SQLite database is working!")
                print("✅ You can now use HiveNest with local SQLite database")
                print("\n📝 Next steps:")
                print("   1. Start the backend server: python server.py")
                print("   2. The system will use SQLite for development")
                print("   3. When MySQL access is fixed, run: python scripts/db_manager.py --restore-mysql")
                return True
            else:
                print("❌ SQLite setup also failed")
        else:
            print("❌ Failed to setup SQLite fallback")
    
    print("\n" + "=" * 50)
    print("🚨 DATABASE CONNECTION SUMMARY")
    print("❌ Unable to establish database connection")
    print("\n🔧 REQUIRED ACTIONS:")
    print("1. Contact database administrator to fix MySQL permissions")
    print("2. Or provide alternative database credentials")
    print("\n📋 MySQL Permission Commands Needed:")
    print("   GRANT ALL PRIVILEGES ON hivenest_main.* TO 'hivenest_hostingsetup'@'%';")
    print("   FLUSH PRIVILEGES;")
    
    return False

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description='HiveNest Database Management')
    parser.add_argument('--restore-mysql', action='store_true', help='Restore MySQL configuration')
    parser.add_argument('--use-sqlite', action='store_true', help='Force SQLite setup')
    
    args = parser.parse_args()
    
    if args.restore_mysql:
        restore_mysql_config()
        sys.exit(0)
    elif args.use_sqlite:
        setup_sqlite_fallback()
        test_database_setup()
        sys.exit(0)
    else:
        success = main()
        sys.exit(0 if success else 1)