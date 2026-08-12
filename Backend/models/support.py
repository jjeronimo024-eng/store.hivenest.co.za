"""
Support models for HiveNest
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, Boolean, Enum, ForeignKey
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
from .database import Base
import enum

class TicketPriority(str, enum.Enum):
    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    URGENT = "urgent"

class TicketCategory(str, enum.Enum):
    TECHNICAL = "technical"
    BILLING = "billing"
    GENERAL = "general"
    SALES = "sales"
    ABUSE = "abuse"

class TicketStatus(str, enum.Enum):
    OPEN = "open"
    PENDING = "pending"
    REPLIED = "replied"
    RESOLVED = "resolved"
    CLOSED = "closed"

class ReplyType(str, enum.Enum):
    CUSTOMER = "customer"
    STAFF = "staff"

class SupportTicket(Base):
    __tablename__ = "support_tickets"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    customer_id = Column(Integer, ForeignKey("customers.id"))
    ticket_number = Column(String(50), unique=True, index=True)
    subject = Column(String(255))
    priority = Column(Enum(TicketPriority), default=TicketPriority.MEDIUM)
    category = Column(Enum(TicketCategory), default=TicketCategory.GENERAL)
    status = Column(Enum(TicketStatus), default=TicketStatus.OPEN)
    assigned_to = Column(Integer, ForeignKey("admin_users.id"))
    service_id = Column(Integer, ForeignKey("services.id"))
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    # Relationships
    customer = relationship("Customer")
    assigned_admin = relationship("AdminUser")
    service = relationship("Service")
    replies = relationship("SupportTicketReply", back_populates="ticket")
    
    def __repr__(self):
        return f"<SupportTicket {self.ticket_number}>"

class SupportTicketReply(Base):
    __tablename__ = "support_ticket_replies"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    ticket_id = Column(Integer, ForeignKey("support_tickets.id"))
    reply_type = Column(Enum(ReplyType))
    author_id = Column(Integer)  # Can be customer_id or admin_id
    message = Column(Text)
    attachments = Column(Text)  # JSON string
    is_internal = Column(Boolean, default=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    
    # Relationships
    ticket = relationship("SupportTicket", back_populates="replies")
    
    def __repr__(self):
        return f"<SupportTicketReply {self.ticket_id}>"