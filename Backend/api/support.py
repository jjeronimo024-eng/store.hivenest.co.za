"""
Support API endpoints for HiveNest
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import desc
from pydantic import BaseModel
from typing import List, Optional, Dict, Any
from datetime import datetime
import json

from models.database import get_db
from models.support import SupportTicket, SupportTicketReply, TicketStatus, TicketPriority, TicketCategory, ReplyType
from models.customer import Customer
from models.admin import AdminUser
from core.dependencies import get_current_customer, get_current_admin, get_current_user
from core.auth import generate_uuid

router = APIRouter()

# Response models
class TicketReplyResponse(BaseModel):
    id: int
    uuid: str
    reply_type: ReplyType
    author_id: int
    message: str
    attachments: Optional[List[Dict[str, Any]]]
    is_internal: bool
    created_at: datetime
    
    class Config:
        from_attributes = True

class TicketResponse(BaseModel):
    id: int
    uuid: str
    ticket_number: str
    subject: str
    priority: TicketPriority
    category: TicketCategory
    status: TicketStatus
    assigned_to: Optional[int]
    service_id: Optional[int]
    created_at: datetime
    updated_at: datetime
    replies: List[TicketReplyResponse]
    
    class Config:
        from_attributes = True

class TicketSummary(BaseModel):
    id: int
    uuid: str
    ticket_number: str
    subject: str
    priority: TicketPriority
    category: TicketCategory
    status: TicketStatus
    created_at: datetime
    
    class Config:
        from_attributes = True

# Request models
class TicketCreate(BaseModel):
    subject: str
    priority: TicketPriority = TicketPriority.MEDIUM
    category: TicketCategory = TicketCategory.GENERAL
    service_id: Optional[int] = None
    message: str
    attachments: Optional[List[Dict[str, Any]]] = None

class ReplyCreate(BaseModel):
    message: str
    attachments: Optional[List[Dict[str, Any]]] = None
    is_internal: bool = False

@router.get("/", response_model=List[TicketSummary])
async def get_tickets(
    user_data: dict = Depends(get_current_user),
    db: Session = Depends(get_db),
    status: Optional[TicketStatus] = None
):
    """Get tickets (customer or admin view)"""
    
    user = user_data["user"]
    user_type = user_data["user_type"]
    
    if user_type == "customer":
        query = db.query(SupportTicket).filter(SupportTicket.customer_id == user.id)
    else:  # admin
        query = db.query(SupportTicket)
    
    if status:
        query = query.filter(SupportTicket.status == status)
    
    tickets = query.order_by(desc(SupportTicket.created_at)).all()
    
    return [TicketSummary.model_validate(ticket) for ticket in tickets]

@router.get("/{ticket_id}", response_model=TicketResponse)
async def get_ticket(
    ticket_id: int,
    user_data: dict = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get specific ticket"""
    
    user = user_data["user"]
    user_type = user_data["user_type"]
    
    query = db.query(SupportTicket).filter(SupportTicket.id == ticket_id)
    
    # If customer, only show their tickets
    if user_type == "customer":
        query = query.filter(SupportTicket.customer_id == user.id)
    
    ticket = query.first()
    
    if not ticket:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Ticket not found"
        )
    
    return TicketResponse.model_validate(ticket)

@router.post("/", response_model=TicketResponse)
async def create_ticket(
    ticket_data: TicketCreate,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Create new support ticket"""
    
    # Generate ticket number
    ticket_count = db.query(SupportTicket).count() + 1
    ticket_number = f"TIC-{datetime.now().year}-{ticket_count:06d}"
    
    # Create ticket
    ticket = SupportTicket(
        uuid=generate_uuid(),
        customer_id=current_customer.id,
        ticket_number=ticket_number,
        subject=ticket_data.subject,
        priority=ticket_data.priority,
        category=ticket_data.category,
        service_id=ticket_data.service_id
    )
    
    db.add(ticket)
    db.flush()  # Get ticket ID
    
    # Create initial reply
    reply = SupportTicketReply(
        uuid=generate_uuid(),
        ticket_id=ticket.id,
        reply_type=ReplyType.CUSTOMER,
        author_id=current_customer.id,
        message=ticket_data.message,
        attachments=json.dumps(ticket_data.attachments) if ticket_data.attachments else None
    )
    
    db.add(reply)
    db.commit()
    db.refresh(ticket)
    
    return TicketResponse.model_validate(ticket)

@router.post("/{ticket_id}/replies", response_model=TicketReplyResponse)
async def add_reply(
    ticket_id: int,
    reply_data: ReplyCreate,
    user_data: dict = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Add reply to ticket"""
    
    user = user_data["user"]
    user_type = user_data["user_type"]
    
    # Get ticket
    query = db.query(SupportTicket).filter(SupportTicket.id == ticket_id)
    
    # If customer, only allow replies to their tickets
    if user_type == "customer":
        query = query.filter(SupportTicket.customer_id == user.id)
    
    ticket = query.first()
    
    if not ticket:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Ticket not found"
        )
    
    # Create reply
    reply = SupportTicketReply(
        uuid=generate_uuid(),
        ticket_id=ticket.id,
        reply_type=ReplyType.CUSTOMER if user_type == "customer" else ReplyType.STAFF,
        author_id=user.id,
        message=reply_data.message,
        attachments=json.dumps(reply_data.attachments) if reply_data.attachments else None,
        is_internal=reply_data.is_internal if user_type == "admin" else False
    )
    
    db.add(reply)
    
    # Update ticket status
    if user_type == "customer":
        ticket.status = TicketStatus.REPLIED
    else:
        ticket.status = TicketStatus.PENDING
    
    db.commit()
    db.refresh(reply)
    
    return TicketReplyResponse.model_validate(reply)

@router.put("/{ticket_id}/status")
async def update_ticket_status(
    ticket_id: int,
    status: TicketStatus,
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db)
):
    """Update ticket status (admin only)"""
    
    ticket = db.query(SupportTicket).filter(SupportTicket.id == ticket_id).first()
    
    if not ticket:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Ticket not found"
        )
    
    ticket.status = status
    if status in [TicketStatus.RESOLVED, TicketStatus.CLOSED]:
        ticket.assigned_to = admin.id
    
    db.commit()
    
    return {"message": f"Ticket status updated to {status}"}

@router.put("/{ticket_id}/assign")
async def assign_ticket(
    ticket_id: int,
    admin_id: int,
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db)
):
    """Assign ticket to admin"""
    
    ticket = db.query(SupportTicket).filter(SupportTicket.id == ticket_id).first()
    
    if not ticket:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Ticket not found"
        )
    
    # Verify admin exists
    assigned_admin = db.query(AdminUser).filter(AdminUser.id == admin_id).first()
    if not assigned_admin:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Admin not found"
        )
    
    ticket.assigned_to = admin_id
    db.commit()
    
    return {"message": f"Ticket assigned to {assigned_admin.full_name}"}