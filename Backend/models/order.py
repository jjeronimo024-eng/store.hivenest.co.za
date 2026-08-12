"""
Order models for HiveNest
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, Enum, ForeignKey, Numeric
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
from .database import Base
import enum

class OrderStatus(str, enum.Enum):
    PENDING = "pending"
    PROCESSING = "processing"
    COMPLETED = "completed"
    CANCELLED = "cancelled"
    REFUNDED = "refunded"
    FAILED = "failed"

class PaymentStatus(str, enum.Enum):
    PENDING = "pending"
    PAID = "paid"
    FAILED = "failed"
    REFUNDED = "refunded"
    PARTIALLY_REFUNDED = "partially_refunded"

class PaymentMethod(str, enum.Enum):
    PAYPAL = "paypal"
    STRIPE = "stripe"
    CREDIT_BALANCE = "credit_balance"
    BANK_TRANSFER = "bank_transfer"

class BillingCycle(str, enum.Enum):
    MONTHLY = "monthly"
    QUARTERLY = "quarterly"
    SEMI_ANNUALLY = "semi_annually"
    ANNUALLY = "annually"
    BIENNIALLY = "biennially"
    TRIENNIALLY = "triennially"

class Order(Base):
    __tablename__ = "orders"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    customer_id = Column(Integer, ForeignKey("customers.id"))
    order_number = Column(String(50), unique=True, index=True)
    order_status = Column(Enum(OrderStatus), default=OrderStatus.PENDING)
    payment_status = Column(Enum(PaymentStatus), default=PaymentStatus.PENDING)
    subtotal = Column(Numeric(12, 2))
    tax_amount = Column(Numeric(12, 2), default=0.00)
    discount_amount = Column(Numeric(12, 2), default=0.00)
    total_amount = Column(Numeric(12, 2))
    currency = Column(String(3), default="USD")
    payment_method = Column(Enum(PaymentMethod))
    payment_reference = Column(String(255))
    billing_address = Column(Text)  # JSON string
    order_notes = Column(Text)
    admin_notes = Column(Text)
    processed_at = Column(DateTime(timezone=True))
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    # Relationships
    customer = relationship("Customer")
    items = relationship("OrderItem", back_populates="order")
    
    def __repr__(self):
        return f"<Order {self.order_number}>"

class OrderItem(Base):
    __tablename__ = "order_items"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    order_id = Column(Integer, ForeignKey("orders.id"))
    product_id = Column(Integer, ForeignKey("products.id"))
    product_name = Column(String(255))
    domain_name = Column(String(255))
    quantity = Column(Integer, default=1)
    unit_price = Column(Numeric(10, 2))
    setup_fee = Column(Numeric(10, 2), default=0.00)
    billing_cycle = Column(Enum(BillingCycle))
    line_total = Column(Numeric(12, 2))
    product_config = Column(Text)  # JSON string
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    
    # Relationships
    order = relationship("Order", back_populates="items")
    product = relationship("Product")
    
    def __repr__(self):
        return f"<OrderItem {self.product_name}>"