import enum
from sqlalchemy import (
    Column,
    Integer,
    String,
    Boolean,
    DateTime,
    Enum as SQLAlchemyEnum,
    ForeignKey,
    Text,
    Float,
)
from sqlalchemy.orm import relationship
from .database import Base
from datetime import datetime

# Enums
class ProductType(enum.Enum):
    HOSTING = "hosting"
    DOMAIN = "domain"
    SERVER = "server"
    EMAIL = "email"
    SECURITY = "security"
    SSL = "ssl"
    OTHER = "other"

class BillingCycle(enum.Enum):
    MONTHLY = "monthly"
    QUARTERLY = "quarterly"
    SEMI_ANNUALLY = "semi_annually"
    ANNUALLY = "annually"
    BIENNIALLY = "biennially"
    TRIENNIALLY = "triennially"
    ONCE = "once"

# Models
class ProductCategory(Base):
    __tablename__ = "product_categories"

    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    name = Column(String(255), nullable=False)
    slug = Column(String(255), unique=True, nullable=False)
    description = Column(Text, nullable=True)
    icon = Column(String(255), nullable=True)
    sort_order = Column(Integer, default=0)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    products = relationship("Product", back_populates="category")

class Product(Base):
    __tablename__ = "products"

    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    name = Column(String(255), nullable=False)
    slug = Column(String(255), unique=True, nullable=False)
    description = Column(Text, nullable=True)
    short_description = Column(Text, nullable=True)
    product_type = Column(SQLAlchemyEnum(ProductType), nullable=False)
    billing_cycle = Column(SQLAlchemyEnum(BillingCycle), nullable=False)
    base_price = Column(Float, nullable=False)
    setup_fee = Column(Float, default=0.0)
    features = Column(Text, nullable=True)
    specifications = Column(Text, nullable=True)
    is_active = Column(Boolean, default=True)
    is_featured = Column(Boolean, default=False)
    requires_domain = Column(Boolean, default=False)
    sort_order = Column(Integer, default=0)
    category_id = Column(Integer, ForeignKey("product_categories.id"))
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    category = relationship("ProductCategory", back_populates="products")

class ProviderProductMapping(Base):
    __tablename__ = "provider_product_mappings"

    id = Column(Integer, primary_key=True, index=True)
    product_id = Column(Integer, ForeignKey("products.id"))
    provider_name = Column(String(255))
    provider_product_id = Column(String(255))

    product = relationship("Product", back_populates="provider_mappings")

Product.provider_mappings = relationship("ProviderProductMapping", back_populates="product")