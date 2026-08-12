"""
Admin API endpoints for HiveNest
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import desc, func
from pydantic import BaseModel, EmailStr
from typing import List, Optional, Dict, Any
from datetime import datetime, timedelta
import json

from models.database import get_db
from models.admin import AdminUser, AdminRole
from models.customer import Customer, CustomerStatus
from models.service import Service, ServiceStatus
from models.order import Order, OrderStatus
from models.support import SupportTicket, TicketStatus
from core.dependencies import get_current_admin, require_admin_role
from core.auth import get_password_hash, generate_uuid

router = APIRouter()

# Response models
class AdminDashboardStats(BaseModel):
    total_customers: int
    active_customers: int
    total_services: int
    active_services: int
    total_orders: int
    pending_orders: int
    open_tickets: int
    urgent_tickets: int
    monthly_revenue: float

class CustomerSummary(BaseModel):
    id: int
    uuid: str
    email: str
    full_name: str
    customer_type: str
    status: CustomerStatus
    active_services: int
    total_orders: int
    credit_balance: float
    created_at: datetime
    
    class Config:
        from_attributes = True

class ServiceSummary(BaseModel):
    id: int
    uuid: str
    service_name: str
    customer_email: str
    service_type: str
    service_status: ServiceStatus
    expiry_date: Optional[datetime]
    next_due_date: Optional[datetime]
    
    class Config:
        from_attributes = True

class OrderSummary(BaseModel):
    id: int
    uuid: str
    order_number: str
    customer_email: str
    order_status: OrderStatus
    total_amount: float
    created_at: datetime
    
    class Config:
        from_attributes = True

# Request models
class CustomerStatusUpdate(BaseModel):
    status: CustomerStatus
    reason: Optional[str] = None

@router.get("/dashboard", response_model=AdminDashboardStats)
async def get_admin_dashboard(
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db)
):
    """Get admin dashboard statistics"""
    
    # Customer stats
    total_customers = db.query(Customer).count()
    active_customers = db.query(Customer).filter(Customer.status == CustomerStatus.ACTIVE).count()
    
    # Service stats
    total_services = db.query(Service).count()
    active_services = db.query(Service).filter(Service.service_status == ServiceStatus.ACTIVE).count()
    
    # Order stats
    total_orders = db.query(Order).count()
    pending_orders = db.query(Order).filter(Order.order_status == OrderStatus.PENDING).count()
    
    # Support stats
    open_tickets = db.query(SupportTicket).filter(
        SupportTicket.status.in_([TicketStatus.OPEN, TicketStatus.PENDING])
    ).count()
    urgent_tickets = db.query(SupportTicket).filter(
        SupportTicket.status.in_([TicketStatus.OPEN, TicketStatus.PENDING]),
        SupportTicket.priority == "urgent"
    ).count()
    
    # Revenue stats (current month)
    current_month = datetime.now().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
    monthly_revenue = db.query(func.sum(Order.total_amount)).filter(
        Order.order_status == OrderStatus.COMPLETED,
        Order.created_at >= current_month
    ).scalar() or 0
    
    return AdminDashboardStats(
        total_customers=total_customers,
        active_customers=active_customers,
        total_services=total_services,
        active_services=active_services,
        total_orders=total_orders,
        pending_orders=pending_orders,
        open_tickets=open_tickets,
        urgent_tickets=urgent_tickets,
        monthly_revenue=float(monthly_revenue)
    )

@router.get("/customers", response_model=List[CustomerSummary])
async def get_customers(
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db),
    status: Optional[CustomerStatus] = None,
    limit: int = 50,
    offset: int = 0
):
    """Get customers list"""
    
    query = db.query(Customer)
    
    if status:
        query = query.filter(Customer.status == status)
    
    customers = query.order_by(desc(Customer.created_at)).offset(offset).limit(limit).all()
    
    # Add service and order counts
    customer_summaries = []
    for customer in customers:
        active_services = db.query(Service).filter(
            Service.customer_id == customer.id,
            Service.service_status == ServiceStatus.ACTIVE
        ).count()
        
        total_orders = db.query(Order).filter(Order.customer_id == customer.id).count()
        
        summary = CustomerSummary.model_validate(customer)
        summary.active_services = active_services
        summary.total_orders = total_orders
        summary.full_name = customer.full_name
        customer_summaries.append(summary)
    
    return customer_summaries

@router.get("/customers/{customer_id}", response_model=CustomerSummary)
async def get_customer(
    customer_id: int,
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db)
):
    """Get specific customer"""
    
    customer = db.query(Customer).filter(Customer.id == customer_id).first()
    
    if not customer:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Customer not found"
        )
    
    # Add service and order counts
    active_services = db.query(Service).filter(
        Service.customer_id == customer.id,
        Service.service_status == ServiceStatus.ACTIVE
    ).count()
    
    total_orders = db.query(Order).filter(Order.customer_id == customer.id).count()
    
    summary = CustomerSummary.model_validate(customer)
    summary.active_services = active_services
    summary.total_orders = total_orders
    summary.full_name = customer.full_name
    
    return summary

@router.put("/customers/{customer_id}/status")
async def update_customer_status(
    customer_id: int,
    status_data: CustomerStatusUpdate,
    admin: AdminUser = Depends(require_admin_role(["super_admin", "admin"])),
    db: Session = Depends(get_db)
):
    """Update customer status"""
    
    customer = db.query(Customer).filter(Customer.id == customer_id).first()
    
    if not customer:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Customer not found"
        )
    
    customer.status = status_data.status
    db.commit()
    
    return {"message": f"Customer status updated to {status_data.status}"}

@router.get("/services", response_model=List[ServiceSummary])
async def get_services(
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db),
    status: Optional[ServiceStatus] = None,
    limit: int = 50,
    offset: int = 0
):
    """Get services list"""
    
    query = db.query(Service).join(Customer)
    
    if status:
        query = query.filter(Service.service_status == status)
    
    services = query.order_by(desc(Service.created_at)).offset(offset).limit(limit).all()
    
    service_summaries = []
    for service in services:
        summary = ServiceSummary.model_validate(service)
        summary.customer_email = service.customer.email
        service_summaries.append(summary)
    
    return service_summaries

@router.get("/orders", response_model=List[OrderSummary])
async def get_orders(
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db),
    status: Optional[OrderStatus] = None,
    limit: int = 50,
    offset: int = 0
):
    """Get orders list"""
    
    query = db.query(Order).join(Customer)
    
    if status:
        query = query.filter(Order.order_status == status)
    
    orders = query.order_by(desc(Order.created_at)).offset(offset).limit(limit).all()
    
    order_summaries = []
    for order in orders:
        summary = OrderSummary.model_validate(order)
        summary.customer_email = order.customer.email
        order_summaries.append(summary)
    
    return order_summaries