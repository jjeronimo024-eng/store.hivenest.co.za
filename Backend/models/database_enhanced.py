"""
Enhanced database connection with SQLite fallback support
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
DB_TYPE = os.getenv('DB_TYPE', 'mysql')
DB_HOST = os.getenv('DB_HOST', '198.251.89.205')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_NAME', 'hivenest_main')
DB_USER = os.getenv('DB_USER', 'hivenest_hostingsetup')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')
DB_CHARSET = os.getenv('DB_CHARSET', 'utf8mb4')

def create_database_url():
    """Create database URL based on DB_TYPE"""
    if DB_TYPE.lower() == 'sqlite':
        # SQLite for local development
        db_path = ROOT_DIR / DB_NAME
        return f"sqlite:///{db_path}"
    else:
        # MySQL for production
        encoded_password = quote_plus(DB_PASSWORD)
        return f"mysql+pymysql://{DB_USER}:{encoded_password}@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset={DB_CHARSET}"

# Create database URL
DATABASE_URL = create_database_url()

print(f"Database Type: {DB_TYPE}")
if DB_TYPE.lower() == 'sqlite':
    print(f"SQLite Database: {ROOT_DIR}/{DB_NAME}")
else:
    print(f"MySQL Connection: mysql+pymysql://{DB_USER}:***@{DB_HOST}:{DB_PORT}/{DB_NAME}")

# Create SQLAlchemy engine
if DB_TYPE.lower() == 'sqlite':
    engine = create_engine(
        DATABASE_URL,
        echo=False,
        connect_args={"check_same_thread": False}  # Required for SQLite with FastAPI
    )
else:
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
        print("✅ Database tables initialized successfully")
        return True
    except Exception as e:
        print(f"❌ Database initialization failed: {e}")
        return False
    
def test_connection():
    """
    Test database connection
    """
    try:
        db = SessionLocal()
        db.execute(text("SELECT 1"))
        db.close()
        print("✅ Database connection successful")
        return True
    except Exception as e:
        print(f"❌ Database connection failed: {e}")
        return False

def switch_to_sqlite():
    """Switch to SQLite for local development"""
    env_file = ROOT_DIR / '.env'
    env_content = env_file.read_text()
    
    # Comment out MySQL settings and enable SQLite
    new_content = env_content.replace('DB_TYPE=mysql', '# DB_TYPE=mysql')
    new_content = new_content.replace('# DB_TYPE=sqlite', 'DB_TYPE=sqlite')
    new_content = new_content.replace('DB_NAME=hivenest_main', '# DB_NAME=hivenest_main')
    new_content += '\nDB_TYPE=sqlite\nDB_NAME=hivenest_local.db\n'
    
    env_file.write_text(new_content)
    print("✅ Switched to SQLite database for local development")

def switch_to_mysql():
    """Switch back to MySQL for production"""
    env_file = ROOT_DIR / '.env'
    env_content = env_file.read_text()
    
    # Enable MySQL and disable SQLite
    new_content = env_content.replace('DB_TYPE=sqlite', '# DB_TYPE=sqlite') 
    new_content = new_content.replace('# DB_TYPE=mysql', 'DB_TYPE=mysql')
    
    env_file.write_text(new_content)
    print("✅ Switched back to MySQL database")