#!/usr/bin/env python3
"""
Test HiveNest API endpoints manually
"""
import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent.parent))

from sqlalchemy.orm import sessionmaker
from models.database import engine
from models.product import Product, ProductCategory

def test_database():
    """Test database queries"""
    print("🧪 Testing Database Queries...")
    
    Session = sessionmaker(bind=engine)
    db = Session()
    
    try:
        # Test product categories
        categories = db.query(ProductCategory).all()
        print(f"📂 Found {len(categories)} product categories:")
        for cat in categories:
            print(f"   - {cat.name}: {cat.description}")
        
        # Test products
        products = db.query(Product).all()
        print(f"📦 Found {len(products)} products:")
        for product in products:
            print(f"   - {product.name}: ${product.base_price} ({product.billing_cycle})")
            
        db.close()
        return True
        
    except Exception as e:
        print(f"❌ Database query failed: {e}")
        db.close()
        return False

def test_product_api():
    """Test product API response formatting"""
    print("\n🔧 Testing Product API Response...")
    
    try:
        from api.products import ProductResponse, ProductCategoryResponse
        from pydantic import BaseModel
        from sqlalchemy.orm import sessionmaker
        
        Session = sessionmaker(bind=engine)
        db = Session()
        
        # Get a product with category
        product = db.query(Product).first()
        if not product:
            print("❌ No products found in database")
            return False
            
        print(f"📦 Testing product: {product.name}")
        
        # Try to create category response
        if product.category:
            try:
                category_response = ProductCategoryResponse.model_validate(product.category)
                print(f"✅ Category response created: {category_response.name}")
            except Exception as e:
                print(f"❌ Category response failed: {e}")
                
        # Try to create product response  
        try:
            # We need to handle the category relationship manually
            product_dict = {
                'id': product.id,
                'uuid': product.uuid or 'test-uuid',
                'name': product.name,
                'slug': product.slug or 'test-slug',
                'description': product.description,
                'short_description': product.short_description,
                'product_type': product.product_type or 'hosting',
                'billing_cycle': product.billing_cycle,
                'base_price': float(product.base_price),
                'setup_fee': float(product.setup_fee or 0),
                'features': product.features or '{}',
                'is_active': product.is_active,
                'is_featured': product.is_featured or False,
                'category': {
                    'id': product.category.id if product.category else 0,
                    'uuid': product.category.uuid if product.category else 'cat-uuid',
                    'name': product.category.name if product.category else 'No Category',
                    'slug': product.category.slug if product.category else 'no-category',
                    'description': product.category.description if product.category else None,
                    'icon': product.category.icon if product.category else None,
                    'sort_order': product.category.sort_order if product.category else 0,
                    'is_active': product.category.is_active if product.category else True
                }
            }
            
            product_response = ProductResponse(**product_dict)
            print(f"✅ Product response created: {product_response.name}")
            print(f"💰 Price: ${product_response.base_price}")
            
            return True
            
        except Exception as e:
            print(f"❌ Product response failed: {e}")
            import traceback
            traceback.print_exc()
            return False
            
        finally:
            db.close()
            
    except Exception as e:
        print(f"❌ Product API test failed: {e}")
        import traceback
        traceback.print_exc()
        return False

if __name__ == "__main__":
    print("🌟 HiveNest API Testing")
    print("=" * 50)
    
    if test_database():
        test_product_api()
    else:
        print("❌ Database test failed, skipping API tests")