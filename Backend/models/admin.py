"""
Admin user model for HiveNest
"""
from sqlalchemy import Column, Integer, String, DateTime, Boolean, Text, Enum
from sqlalchemy.sql import func
from .database import Base
import enum

class AdminRole(str, enum.Enum):
    SUPER_ADMIN = "super_admin"
    ADMIN = "admin"
    STAFF = "staff"
    SUPPORT = "support"

class AdminUser(Base):
    __tablename__ = "admin_users"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    username = Column(String(100), unique=True, index=True)
    email = Column(String(255), unique=True, index=True)
    password_hash = Column(String(255))
    auth_version = Column(Integer, default=1)
    two_factor_enabled = Column(Boolean, default=False)
    two_factor_secret = Column(String(768))
    two_factor_confirmed_at = Column(DateTime(timezone=True))
    first_name = Column(String(100))
    last_name = Column(String(100))
    role = Column(Enum(AdminRole), default=AdminRole.STAFF)
    permissions = Column(Text)  # JSON string
    is_active = Column(Boolean, default=True)
    last_login = Column(DateTime(timezone=True))
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    def __repr__(self):
        return f"<AdminUser {self.username}>"
    
    @property
    def full_name(self):
        return f"{self.first_name} {self.last_name}".strip()
    
    @property
    def is_super_admin(self):
        return self.role == AdminRole.SUPER_ADMIN
