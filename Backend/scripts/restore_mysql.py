#!/usr/bin/env python3
"""
MySQL Restoration Script for HiveNest
Run this script when MySQL database access is fixed
"""
import os
import shutil
import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent.parent))

def restore_mysql_database():
    """Restore MySQL database configuration"""
    print("🔄 Restoring MySQL Database Configuration...")
    
    # Restore original database.py
    db_file = Path(__file__).parent.parent / 'models' / 'database.py'
    backup_file = Path(__file__).parent.parent / 'models' / 'database_mysql_backup.py'
    
    if backup_file.exists():
        shutil.copy(backup_file, db_file)
        print("✅ MySQL database.py restored")
    else:
        print("❌ MySQL backup not found, recreating...")
        mysql_config = '''"""
MySQL Database connection for HiveNest
"""
import os
from urllib.parse import quote_plus
from dotenv import load_dotenv
from sqlalchemy import create_engine, text
from sqlalchemy.orm import declarative_base, sessionmaker
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

# URL-encode the password to handle special characters
encoded_password = quote_plus(DB_PASSWORD)

# Create database URL
DATABASE_URL = f"mysql+pymysql://{DB_USER}:{encoded_password}@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset={DB_CHARSET}"

print(f"Connecting to database: mysql+pymysql://{DB_USER}:***@{DB_HOST}:{DB_PORT}/{DB_NAME}")

# Create SQLAlchemy engine
engine = create_engine(
    DATABASE_URL,
    pool_size=20,
    max_overflow=30,
    pool_pre_ping=True,
    pool_recycle=3600,
    echo=False
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
        print("✅ MySQL database tables initialized")
        return True
    except Exception as e:
        print(f"❌ MySQL database initialization failed: {e}")
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
        print(f"MySQL database connection failed: {e}")
        return False
'''
        db_file.write_text(mysql_config)
        print("✅ MySQL database.py recreated")

def test_mysql_connection():
    """Test MySQL connection"""
    print("\n🧪 Testing MySQL Connection...")
    
    try:
        # Reload database module
        import importlib
        import models.database
        importlib.reload(models.database)
        
        from models.database import test_connection, init_db
        
        if test_connection():
            print("✅ MySQL connection successful!")
            
            # Initialize database
            if init_db():
                print("✅ MySQL database initialized!")
                
                # Migrate data from SQLite if needed
                migrate_from_sqlite()
                return True
            else:
                print("⚠️  MySQL connected but initialization failed")
        else:
            print("❌ MySQL connection failed")
            print("\n🔧 Required MySQL commands:")
            print("   GRANT ALL PRIVILEGES ON hivenest_main.* TO 'hivenest_hostingsetup'@'%';")
            print("   FLUSH PRIVILEGES;")
            
    except Exception as e:
        print(f"❌ MySQL test failed: {e}")
        
    return False

def migrate_from_sqlite():
    """Migrate data from SQLite to MySQL"""
    print("\n📦 Migrating Data from SQLite to MySQL...")
    
    try:
        import sqlite3
        from sqlalchemy.orm import sessionmaker
        from models.database import engine
        from models.customer import Customer
        from models.admin import AdminUser  
        from models.product import Product, ProductCategory
        
        # Connect to SQLite
        sqlite_path = Path(__file__).parent.parent / 'hivenest_development.db'
        if not sqlite_path.exists():
            print("ℹ️  No SQLite database found to migrate")
            return
            
        sqlite_conn = sqlite3.connect(sqlite_path)
        sqlite_conn.row_factory = sqlite3.Row
        
        # Connect to MySQL
        Session = sessionmaker(bind=engine)
        mysql_db = Session()
        
        print("🔄 Migrating product categories...")
        categories = sqlite_conn.execute("SELECT * FROM product_categories").fetchall()
        for cat in categories:
            mysql_cat = ProductCategory(
                name=cat['name'],
                description=cat['description'],
                is_active=cat['is_active']
            )
            mysql_db.add(mysql_cat)
        
        print("🔄 Migrating products...")
        products = sqlite_conn.execute("SELECT * FROM products").fetchall()  
        for prod in products:
            mysql_prod = Product(
                category_id=prod['category_id'],
                name=prod['name'],
                description=prod['description'],
                base_price=prod['base_price'],
                billing_cycle=prod['billing_cycle'],
                is_active=prod['is_active'],
                features=prod['features']
            )
            mysql_db.add(mysql_prod)
            
        print("🔄 Migrating admin users...")
        admins = sqlite_conn.execute("SELECT * FROM admin_users").fetchall()
        for admin in admins:
            mysql_admin = AdminUser(
                username=admin['username'],
                email=admin['email'],
                password_hash=admin['password_hash'],
                first_name=admin['first_name'],
                last_name=admin['last_name'],
                role=admin['role'],
                is_active=admin['is_active']
            )
            mysql_db.add(mysql_admin)
            
        print("🔄 Migrating customers...")
        customers = sqlite_conn.execute("SELECT * FROM customers").fetchall()
        for cust in customers:
            mysql_cust = Customer(
                email=cust['email'],
                password_hash=cust['password_hash'],
                first_name=cust['first_name'],
                last_name=cust['last_name'],
                company_name=cust['company_name'],
                phone=cust['phone'],
                status=cust['status']
            )
            mysql_db.add(mysql_cust)
        
        mysql_db.commit()
        mysql_db.close()
        sqlite_conn.close()
        
        print("✅ Data migration completed successfully!")
        
    except Exception as e:
        print(f"⚠️  Data migration failed: {e}")
        print("💡 You may need to manually recreate sample data")

def main():
    """Main restoration function"""
    print("🛠️  HiveNest MySQL Restoration")
    print("=" * 50)
    
    restore_mysql_database()
    
    if test_mysql_connection():
        print("\n🎉 MySQL restoration completed successfully!")
        print("🚀 HiveNest is now using MySQL database!")
        print("\n📝 Next steps:")
        print("   1. Restart the backend server")  
        print("   2. Test all API endpoints")
        print("   3. Verify data integrity")
        return True
    else:
        print("\n❌ MySQL restoration failed!")
        print("💡 Check MySQL server permissions and try again")
        return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)