"""
Product API endpoints for HiveNest
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session, joinedload
from sqlalchemy import desc
from pydantic import BaseModel
from typing import List, Optional
from datetime import datetime
import json

from models.database import get_db
from models.product import Product, ProductCategory, ProductType, BillingCycle
from core.dependencies import get_current_customer

router = APIRouter()

# Response models
class ProductCategoryResponse(BaseModel):
    id: int
    uuid: str
    name: str
    slug: str
    description: Optional[str]
    icon: Optional[str]
    sort_order: int
    is_active: bool
    
    class Config:
        from_attributes = True

class ProviderProductMappingResponse(BaseModel):
    provider_name: str
    provider_product_id: str

    class Config:
        from_attributes = True

class ProductResponse(BaseModel):
    id: int
    uuid: str
    name: str
    slug: str
    description: Optional[str]
    short_description: Optional[str]
    product_type: ProductType
    billing_cycle: BillingCycle
    base_price: float
    setup_fee: float
    features: Optional[str]
    specifications: Optional[str]
    is_active: bool
    is_featured: bool
    requires_domain: bool
    category: ProductCategoryResponse
    provider_mappings: List[ProviderProductMappingResponse]
    
    class Config:
        from_attributes = True

class ProductListResponse(BaseModel):
    id: int
    uuid: str
    name: str
    slug: str
    short_description: Optional[str]
    product_type: ProductType
    billing_cycle: BillingCycle
    base_price: float
    setup_fee: float
    is_featured: bool
    category_name: str
    
    class Config:
        from_attributes = True

@router.get("/categories", response_model=List[ProductCategoryResponse])
async def get_product_categories(
    db: Session = Depends(get_db),
    active_only: bool = True
):
    """Get all product categories"""
    
    query = db.query(ProductCategory)
    
    if active_only:
        query = query.filter(ProductCategory.is_active == True)
    
    categories = query.order_by(ProductCategory.sort_order).all()
    
    return [ProductCategoryResponse.model_validate(category) for category in categories]

@router.get("/", response_model=List[ProductResponse])
async def get_products(
    db: Session = Depends(get_db),
    category_id: Optional[int] = None,
    product_type: Optional[ProductType] = None,
    featured_only: bool = False,
    active_only: bool = True
):
    """Get products with optional filtering"""
    
    query = db.query(Product)
    
    if active_only:
        query = query.filter(Product.is_active == True)
    
    if category_id:
        query = query.filter(Product.category_id == category_id)
    
    if product_type:
        query = query.filter(Product.product_type == product_type)
    
    if featured_only:
        query = query.filter(Product.is_featured == True)
    
    products = query.options(joinedload(Product.provider_mappings)).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]

@router.get("/featured", response_model=List[ProductResponse])
async def get_featured_products(
    db: Session = Depends(get_db)
):
    """Get featured products"""
    
    products = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.is_featured == True,
        Product.is_active == True
    ).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]

@router.get("/hosting", response_model=List[ProductResponse])
async def get_hosting_products(
    db: Session = Depends(get_db)
):
    """Get hosting products"""
    
    products = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.product_type == ProductType.HOSTING,
        Product.is_active == True
    ).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]

@router.get("/domains", response_model=List[ProductResponse])
async def get_domain_products(
    db: Session = Depends(get_db)
):
    """Get domain products"""
    
    products = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.product_type == ProductType.DOMAIN,
        Product.is_active == True
    ).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]

@router.get("/servers", response_model=List[ProductResponse])
async def get_server_products(
    db: Session = Depends(get_db)
):
    """Get server products"""
    
    products = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.product_type == ProductType.SERVER,
        Product.is_active == True
    ).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]

@router.get("/email", response_model=List[ProductResponse])
async def get_email_products(
    db: Session = Depends(get_db)
):
    """Get email products"""
    
    products = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.product_type == ProductType.EMAIL,
        Product.is_active == True
    ).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]

@router.get("/security", response_model=List[ProductResponse])
async def get_security_products(
    db: Session = Depends(get_db)
):
    """Get security products (SSL, etc.)"""
    
    products = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.product_type.in_([ProductType.SECURITY, ProductType.SSL]),
        Product.is_active == True
    ).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]

@router.get("/{product_id}", response_model=ProductResponse)
async def get_product(
    product_id: int,
    db: Session = Depends(get_db)
):
    """Get a specific product by ID"""
    
    product = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.id == product_id,
        Product.is_active == True
    ).first()
    
    if not product:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Product not found"
        )
    
    return ProductResponse.model_validate(product)

@router.get("/slug/{slug}", response_model=ProductResponse)
async def get_product_by_slug(
    slug: str,
    db: Session = Depends(get_db)
):
    """Get a specific product by slug"""
    
    product = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.slug == slug,
        Product.is_active == True
    ).first()
    
    if not product:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Product not found"
        )
    
    return ProductResponse.model_validate(product)

@router.get("/category/{category_slug}", response_model=List[ProductResponse])
async def get_products_by_category(
    category_slug: str,
    db: Session = Depends(get_db)
):
    """Get products by category slug"""
    
    category = db.query(ProductCategory).filter(
        ProductCategory.slug == category_slug,
        ProductCategory.is_active == True
    ).first()
    
    if not category:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Category not found"
        )
    
    products = db.query(Product).options(joinedload(Product.provider_mappings)).filter(
        Product.category_id == category.id,
        Product.is_active == True
    ).order_by(Product.sort_order).all()
    
    return [ProductResponse.model_validate(product) for product in products]