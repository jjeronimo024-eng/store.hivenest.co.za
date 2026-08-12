"""
Customer API endpoints for HiveNest
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import func, desc
from pydantic import BaseModel, EmailStr
from typing import List, Optional
from datetime import datetime, timedelta
import json

from models.database import get_db
from models.customer import Customer, CustomerType, CustomerStatus
from models.service import Service, ServiceStatus
from models.order import Order, OrderStatus
from models.support import SupportTicket, TicketStatus
from core.dependencies import get_current_customer
from core.auth import verify_password, get_password_hash

router = APIRouter()

# Response models
class CustomerProfile(BaseModel):
    id: int
    uuid: str
    email: str
    first_name: str
    last_name: str
    company_name: Optional[str]
    phone: Optional[str]
    customer_type: CustomerType
    status: CustomerStatus
    email_verified: bool
    phone_verified: bool
    two_factor_enabled: bool
    preferred_currency: str
    credit_balance: float
    address_line1: Optional[str]
    address_line2: Optional[str]
    city: Optional[str]
    state: Optional[str]
    postal_code: Optional[str]
    country: Optional[str]
    created_at: datetime
    
    class Config:
        from_attributes = True

class ServiceSummary(BaseModel):
    id: int
    uuid: str
    service_name: str
    domain_name: Optional[str]
    service_type: str
    service_status: ServiceStatus
    expiry_date: Optional[datetime]
    next_due_date: Optional[datetime]
    auto_renew: bool
    
    class Config:
        from_attributes = True

class OrderSummary(BaseModel):
    id: int
    uuid: str
    order_number: str
    order_status: OrderStatus
    total_amount: float
    currency: str
    created_at: datetime
    
    class Config:
        from_attributes = True

class TicketSummary(BaseModel):
    id: int
    uuid: str
    ticket_number: str
    subject: str
    status: TicketStatus
    priority: str
    created_at: datetime
    
    class Config:
        from_attributes = True

class DashboardStats(BaseModel):
    active_services: int
    total_orders: int
    open_tickets: int
    credit_balance: float
    next_payment_date: Optional[datetime]
    next_expiry_date: Optional[datetime]

class CustomerDashboard(BaseModel):
    profile: CustomerProfile
    stats: DashboardStats
    recent_services: List[ServiceSummary]
    recent_orders: List[OrderSummary]
    recent_tickets: List[TicketSummary]

# Request models
class UpdateProfile(BaseModel):
    first_name: Optional[str] = None
    last_name: Optional[str] = None
    phone: Optional[str] = None
    company_name: Optional[str] = None
    address_line1: Optional[str] = None
    address_line2: Optional[str] = None
    city: Optional[str] = None
    state: Optional[str] = None
    postal_code: Optional[str] = None
    country: Optional[str] = None

class ChangePassword(BaseModel):
    current_password: str
    new_password: str

@router.get("/dashboard", response_model=CustomerDashboard)
async def get_customer_dashboard(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Get customer dashboard with overview of services, orders, and tickets"""
    
    # Get customer profile
    profile = CustomerProfile.model_validate(current_customer)
    
    # Get statistics
    active_services = db.query(Service).filter(
        Service.customer_id == current_customer.id,
        Service.service_status == ServiceStatus.ACTIVE
    ).count()
    
    total_orders = db.query(Order).filter(
        Order.customer_id == current_customer.id
    ).count()
    
    open_tickets = db.query(SupportTicket).filter(
        SupportTicket.customer_id == current_customer.id,
        SupportTicket.status.in_([TicketStatus.OPEN, TicketStatus.PENDING])
    ).count()
    
    # Get next payment and expiry dates
    next_payment = db.query(Service).filter(
        Service.customer_id == current_customer.id,
        Service.service_status == ServiceStatus.ACTIVE,
        Service.next_due_date.isnot(None)
    ).order_by(Service.next_due_date.asc()).first()
    
    next_expiry = db.query(Service).filter(
        Service.customer_id == current_customer.id,
        Service.service_status == ServiceStatus.ACTIVE,
        Service.expiry_date.isnot(None)
    ).order_by(Service.expiry_date.asc()).first()
    
    stats = DashboardStats(
        active_services=active_services,
        total_orders=total_orders,
        open_tickets=open_tickets,
        credit_balance=float(current_customer.credit_balance),
        next_payment_date=next_payment.next_due_date if next_payment else None,
        next_expiry_date=next_expiry.expiry_date if next_expiry else None
    )
    
    # Get recent services
    recent_services = db.query(Service).filter(
        Service.customer_id == current_customer.id
    ).order_by(desc(Service.created_at)).limit(5).all()
    
    # Get recent orders
    recent_orders = db.query(Order).filter(
        Order.customer_id == current_customer.id
    ).order_by(desc(Order.created_at)).limit(5).all()
    
    # Get recent tickets
    recent_tickets = db.query(SupportTicket).filter(
        SupportTicket.customer_id == current_customer.id
    ).order_by(desc(SupportTicket.created_at)).limit(5).all()
    
    return CustomerDashboard(
        profile=profile,
        stats=stats,
        recent_services=[ServiceSummary.model_validate(s) for s in recent_services],
        recent_orders=[OrderSummary.model_validate(o) for o in recent_orders],
        recent_tickets=[TicketSummary.model_validate(t) for t in recent_tickets]
    )

@router.get("/profile", response_model=CustomerProfile)
async def get_customer_profile(
    current_customer: Customer = Depends(get_current_customer)
):
    """Get customer profile information"""
    return CustomerProfile.model_validate(current_customer)

@router.put("/profile", response_model=CustomerProfile)
async def update_customer_profile(
    profile_data: UpdateProfile,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Update customer profile"""
    
    # Update fields that are provided
    for field, value in profile_data.dict(exclude_unset=True).items():
        setattr(current_customer, field, value)
    
    db.commit()
    db.refresh(current_customer)
    
    return CustomerProfile.model_validate(current_customer)

@router.post("/change-password")
async def change_password(
    password_data: ChangePassword,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Change customer password"""
    
    # Verify current password
    if not verify_password(password_data.current_password, current_customer.password_hash):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Current password is incorrect"
        )
    
    # Update password
    current_customer.password_hash = get_password_hash(password_data.new_password)
    db.commit()
    
    return {"message": "Password updated successfully"}

@router.get("/services", response_model=List[ServiceSummary])
async def get_customer_services(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db),
    status: Optional[ServiceStatus] = None,
    service_type: Optional[str] = None
):
    """Get customer's services with optional filtering"""
    
    query = db.query(Service).filter(Service.customer_id == current_customer.id)
    
    if status:
        query = query.filter(Service.service_status == status)
    
    if service_type:
        query = query.filter(Service.service_type == service_type)
    
    services = query.order_by(desc(Service.created_at)).all()
    
    return [ServiceSummary.model_validate(service) for service in services]

@router.get("/orders", response_model=List[OrderSummary])
async def get_customer_orders(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db),
    status: Optional[OrderStatus] = None
):
    """Get customer's orders with optional filtering"""
    
    query = db.query(Order).filter(Order.customer_id == current_customer.id)
    
    if status:
        query = query.filter(Order.order_status == status)
    
    orders = query.order_by(desc(Order.created_at)).all()
    
    return [OrderSummary.model_validate(order) for order in orders]

@router.get("/tickets", response_model=List[TicketSummary])
async def get_customer_tickets(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db),
    status: Optional[TicketStatus] = None
):
    """Get customer's support tickets with optional filtering"""
    
    query = db.query(SupportTicket).filter(SupportTicket.customer_id == current_customer.id)
    
    if status:
        query = query.filter(SupportTicket.status == status)
    
    tickets = query.order_by(desc(SupportTicket.created_at)).all()
    
    return [TicketSummary.model_validate(ticket) for ticket in tickets]