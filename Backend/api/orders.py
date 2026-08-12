"""
Orders API endpoints for HiveNest
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import desc
from pydantic import BaseModel, validator
from typing import List, Optional, Dict, Any
from datetime import datetime
import json
import uuid

from models.database import get_db
from models.order import Order, OrderItem, OrderStatus, PaymentStatus, PaymentMethod, BillingCycle
from models.customer import Customer
from models.product import Product
from core.dependencies import get_current_customer
from core.auth import generate_uuid

router = APIRouter()

# Response models
class OrderItemResponse(BaseModel):
    id: int
    uuid: str
    product_name: str
    domain_name: Optional[str]
    quantity: int
    unit_price: float
    setup_fee: float
    billing_cycle: BillingCycle
    line_total: float
    product_config: Optional[Dict[str, Any]]
    
    class Config:
        from_attributes = True

class OrderResponse(BaseModel):
    id: int
    uuid: str
    order_number: str
    order_status: OrderStatus
    payment_status: PaymentStatus
    subtotal: float
    tax_amount: float
    discount_amount: float
    total_amount: float
    currency: str
    payment_method: PaymentMethod
    payment_reference: Optional[str]
    billing_address: Optional[Dict[str, Any]]
    order_notes: Optional[str]
    created_at: datetime
    items: List[OrderItemResponse]
    
    class Config:
        from_attributes = True

# Request models
class OrderItemCreate(BaseModel):
    product_id: int
    domain_name: Optional[str]
    quantity: int = 1
    billing_cycle: BillingCycle
    product_config: Optional[Dict[str, Any]] = None

class OrderCreate(BaseModel):
    items: List[OrderItemCreate]
    payment_method: PaymentMethod
    billing_address: Optional[Dict[str, Any]] = None
    order_notes: Optional[str] = None

@router.get("/", response_model=List[OrderResponse])
async def get_customer_orders(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db),
    status: Optional[OrderStatus] = None
):
    """Get customer orders"""
    
    query = db.query(Order).filter(Order.customer_id == current_customer.id)
    
    if status:
        query = query.filter(Order.order_status == status)
    
    orders = query.order_by(desc(Order.created_at)).all()
    
    return [OrderResponse.model_validate(order) for order in orders]

@router.get("/{order_id}", response_model=OrderResponse)
async def get_order(
    order_id: int,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Get specific order"""
    
    order = db.query(Order).filter(
        Order.id == order_id,
        Order.customer_id == current_customer.id
    ).first()
    
    if not order:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Order not found"
        )
    
    return OrderResponse.model_validate(order)

@router.post("/", response_model=OrderResponse)
async def create_order(
    order_data: OrderCreate,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Create new order"""
    
    # Calculate order totals
    subtotal = 0
    order_items = []
    
    for item_data in order_data.items:
        # Get product
        product = db.query(Product).filter(Product.id == item_data.product_id).first()
        if not product:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Product {item_data.product_id} not found"
            )
        
        # Calculate line total
        line_total = float(product.base_price + product.setup_fee) * item_data.quantity
        subtotal += line_total
        
        # Create order item
        order_item = OrderItem(
            uuid=generate_uuid(),
            product_id=product.id,
            product_name=product.name,
            domain_name=item_data.domain_name,
            quantity=item_data.quantity,
            unit_price=product.base_price,
            setup_fee=product.setup_fee,
            billing_cycle=item_data.billing_cycle,
            line_total=line_total,
            product_config=json.dumps(item_data.product_config) if item_data.product_config else None
        )
        order_items.append(order_item)
    
    # Generate order number
    order_count = db.query(Order).count() + 1
    order_number = f"ORD-{datetime.now().year}-{order_count:06d}"
    
    # Create order
    order = Order(
        uuid=generate_uuid(),
        customer_id=current_customer.id,
        order_number=order_number,
        subtotal=subtotal,
        tax_amount=0.00,  # TODO: Calculate tax
        discount_amount=0.00,  # TODO: Apply discounts
        total_amount=subtotal,
        currency=current_customer.preferred_currency,
        payment_method=order_data.payment_method,
        billing_address=json.dumps(order_data.billing_address) if order_data.billing_address else None,
        order_notes=order_data.order_notes
    )
    
    db.add(order)
    db.flush()  # Get order ID
    
    # Add order items
    for item in order_items:
        item.order_id = order.id
        db.add(item)
    
    db.commit()
    db.refresh(order)
    
    return OrderResponse.model_validate(order)