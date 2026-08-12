"""
Simplified Product API for HiveNest - Fixed version
"""
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from pydantic import BaseModel
from typing import List, Optional
from decimal import Decimal

from models.database import get_db
from models.product import Product, ProductCategory

router = APIRouter()

# Simplified Response models that match database structure
class CategoryResponse(BaseModel):
    id: int
    name: str
    description: Optional[str] = None
    
    class Config:
        from_attributes = True

class ProductResponse(BaseModel):
    id: int
    name: str
    description: Optional[str] = None
    base_price: float
    billing_cycle: str
    is_active: bool
    category_name: Optional[str] = None
    
    @classmethod
    def from_product(cls, product: Product):
        return cls(
            id=product.id,
            name=product.name,
            description=product.description,
            base_price=float(product.base_price) if product.base_price else 0.0,
            billing_cycle=product.billing_cycle.value if product.billing_cycle else "monthly",
            is_active=product.is_active or False,
            category_name=product.category.name if product.category else "Uncategorized"
        )

@router.get("/categories", response_model=List[CategoryResponse])
async def get_categories(db: Session = Depends(get_db)):
    """Get all product categories"""
    categories = db.query(ProductCategory).filter(ProductCategory.is_active == True).all()
    return [CategoryResponse.model_validate(cat) for cat in categories]

@router.get("/", response_model=List[ProductResponse]) 
async def get_products(db: Session = Depends(get_db)):
    """Get all products"""
    products = db.query(Product).filter(Product.is_active == True).all()
    return [ProductResponse.from_product(product) for product in products]

@router.get("/{product_id}", response_model=ProductResponse)
async def get_product(product_id: int, db: Session = Depends(get_db)):
    """Get a specific product"""
    product = db.query(Product).filter(Product.id == product_id).first()
    if not product:
        raise HTTPException(status_code=404, detail="Product not found")
    return ProductResponse.from_product(product)

@router.get("/category/{category_id}", response_model=List[ProductResponse])
async def get_products_by_category(category_id: int, db: Session = Depends(get_db)):
    """Get products by category"""
    products = db.query(Product).filter(
        Product.category_id == category_id,
        Product.is_active == True
    ).all()
    return [ProductResponse.from_product(product) for product in products]