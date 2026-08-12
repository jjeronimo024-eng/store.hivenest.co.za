"""
Customer model for HiveNest
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, Boolean, Enum, Numeric
from sqlalchemy.sql import func
from .database import Base
import enum

class CustomerType(str, enum.Enum):
    INDIVIDUAL = "individual"
    BUSINESS = "business"
    RESELLER = "reseller"

class CustomerStatus(str, enum.Enum):
    ACTIVE = "active"
    SUSPENDED = "suspended"
    PENDING = "pending"
    DISABLED = "disabled"

class Customer(Base):
    __tablename__ = "customers"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    customer_type = Column(Enum(CustomerType), default=CustomerType.INDIVIDUAL)
    email = Column(String(255), unique=True, index=True)
    password_hash = Column(String(255))
    auth_version = Column(Integer, default=1)
    first_name = Column(String(100))
    last_name = Column(String(100))
    company_name = Column(String(255))
    phone = Column(String(50))
    country_code = Column(String(3), default="US")
    address_line1 = Column(String(255))
    address_line2 = Column(String(255))
    city = Column(String(100))
    state = Column(String(100))
    postal_code = Column(String(20))
    country = Column(String(100))
    status = Column(Enum(CustomerStatus), default=CustomerStatus.ACTIVE)
    email_verified = Column(Boolean, default=False)
    phone_verified = Column(Boolean, default=False)
    two_factor_enabled = Column(Boolean, default=False)
    two_factor_secret = Column(String(768))
    two_factor_confirmed_at = Column(DateTime(timezone=True))
    preferred_currency = Column(String(3), default="USD")
    reseller_discount_percent = Column(Numeric(5, 2), default=0.00)
    credit_balance = Column(Numeric(12, 2), default=0.00)
    last_login = Column(DateTime(timezone=True))
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    def __repr__(self):
        return f"<Customer {self.email}>"
    
    @property
    def full_name(self):
        return f"{self.first_name} {self.last_name}".strip()
    
    @property
    def display_name(self):
        if self.company_name:
            return self.company_name
        return self.full_name
