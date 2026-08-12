"""
Database initialization script for HiveNest
This script will create all necessary tables once database connection is fixed
"""
import sys
from pathlib import Path

# Add parent directory to path to import models
sys.path.append(str(Path(__file__).parent.parent))

from models.database import Base, engine, test_connection
from models import customer, product, order, service, admin, support

def init_database():
    """Initialize all database tables"""
    print("🚀 Initializing HiveNest Database Tables...")
    
    # Test connection first
    if not test_connection():
        print("❌ Database connection failed. Cannot initialize tables.")
        return False
    
    try:
        # Create all tables
        print("📋 Creating database tables...")
        Base.metadata.create_all(bind=engine)
        print("✅ All database tables created successfully!")
        
        # List created tables
        from sqlalchemy import inspect
        inspector = inspect(engine)
        tables = inspector.get_table_names()
        
        print(f"📊 Created {len(tables)} tables:")
        for table in sorted(tables):
            print(f"   ✓ {table}")
            
        return True
        
    except Exception as e:
        print(f"❌ Error creating database tables: {e}")
        return False

def seed_sample_data():
    """Add sample data to test the system"""
    print("\n🌱 Seeding sample data...")
    
    try:
        from sqlalchemy.orm import sessionmaker
        from models.customer import Customer
        from models.admin import AdminUser
        from models.product import Product, ProductCategory
        from models.service import Service
        from core.auth import get_password_hash
        from datetime import datetime
        
        Session = sessionmaker(bind=engine)
        db = Session()
        
        # Create sample admin user
        admin_user = AdminUser(
            username="admin",
            email="admin@hivenest.co.za",
            password_hash=get_password_hash("admin123"),
            first_name="System",
            last_name="Administrator",
            role="super_admin",
            is_active=True,
            created_at=datetime.utcnow()
        )
        db.add(admin_user)
        
        # Create sample product categories
        categories = [
            ProductCategory(name="Web Hosting", description="Shared and managed hosting plans"),
            ProductCategory(name="Domain Registration", description="Domain name registration services"),
            ProductCategory(name="VPS Hosting", description="Virtual private server hosting"),
            ProductCategory(name="Email Services", description="Professional email services"),
            ProductCategory(name="SSL Certificates", description="Security certificates")
        ]
        
        for cat in categories:
            db.add(cat)
        
        db.commit()
        
        # Get category IDs
        hosting_cat = db.query(ProductCategory).filter_by(name="Web Hosting").first()
        domain_cat = db.query(ProductCategory).filter_by(name="Domain Registration").first()
        email_cat = db.query(ProductCategory).filter_by(name="Email Services").first()
        
        # Create sample products
        products = [
            Product(
                name="Cyber Initiate",
                description="Perfect for beginners - 10GB storage, unlimited bandwidth",
                category_id=hosting_cat.id,
                base_price=18.98,
                billing_cycle="monthly",
                is_active=True,
                features='{"storage": "10GB", "bandwidth": "Unlimited", "domains": "1", "ssl": "Free"}'
            ),
            Product(
                name="Digital Warrior", 
                description="For growing websites - 50GB storage, premium features",
                category_id=hosting_cat.id,
                base_price=45.98,
                billing_cycle="monthly", 
                is_active=True,
                features='{"storage": "50GB", "bandwidth": "Unlimited", "domains": "5", "ssl": "Free"}'
            ),
            Product(
                name=".COM Domain Registration",
                description="Register your .com domain name",
                category_id=domain_cat.id,
                base_price=15.99,
                billing_cycle="annually",
                is_active=True,
                features='{"tld": ".com", "whois_privacy": "Included", "dns_management": "Included"}'
            ),
            Product(
                name="Google Workspace",
                description="Professional email with Google Workspace",
                category_id=email_cat.id,
                base_price=6.00,
                billing_cycle="monthly",
                is_active=True,
                features='{"storage": "30GB", "users": "Per user", "apps": "Full Google Suite"}'
            )
        ]
        
        for product in products:
            db.add(product)
        
        # Create sample customer
        customer = Customer(
            email="john@example.com",
            password_hash=get_password_hash("customer123"),
            first_name="John",
            last_name="Doe",
            company_name="Tech Solutions Ltd",
            phone="+27123456789",
            status="active",
            created_at=datetime.utcnow()
        )
        db.add(customer)
        
        db.commit()
        db.close()
        
        print("✅ Sample data seeded successfully!")
        print("   👤 Admin user: admin@hivenest.co.za / admin123")
        print("   🏢 Customer: john@example.com / customer123")
        print("   📦 Products: Cyber Initiate, Digital Warrior, Domain Registration, Google Workspace")
        
        return True
        
    except Exception as e:
        print(f"❌ Error seeding sample data: {e}")
        return False

def main():
    """Main initialization function"""
    print("🌟 HiveNest Database Initialization")
    print("=" * 50)
    
    # Initialize database tables
    if init_database():
        # Seed sample data
        if seed_sample_data():
            print("\n🎉 Database initialization completed successfully!")
            print("🚀 HiveNest is ready to launch!")
            return True
    
    print("\n❌ Database initialization failed!")
    print("💡 Make sure the database connection is working first.")
    return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)