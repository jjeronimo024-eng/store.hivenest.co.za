"""
Service models for HiveNest
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, Boolean, Enum, ForeignKey, Numeric
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
from .database import Base
import enum

class ServiceType(str, enum.Enum):
    DOMAIN = "domain"
    HOSTING = "hosting"
    EMAIL = "email"
    SECURITY = "security"
    DESIGN = "design"
    SERVER = "server"
    SSL = "ssl"
    BACKUP = "backup"

class ServiceStatus(str, enum.Enum):
    ACTIVE = "active"
    SUSPENDED = "suspended"
    PENDING = "pending"
    TERMINATED = "terminated"
    EXPIRED = "expired"

class BillingCycle(str, enum.Enum):
    MONTHLY = "monthly"
    QUARTERLY = "quarterly"
    SEMI_ANNUALLY = "semi_annually"
    ANNUALLY = "annually"
    BIENNIALLY = "biennially"
    TRIENNIALLY = "triennially"

class HostingType(str, enum.Enum):
    SHARED = "shared"
    VPS = "vps"
    DEDICATED = "dedicated"
    CLOUD = "cloud"

class ControlPanel(str, enum.Enum):
    CPANEL = "cpanel"
    PLESK = "plesk"
    DIRECTADMIN = "directadmin"
    CUSTOM = "custom"

class RegistrarStatus(str, enum.Enum):
    ACTIVE = "active"
    EXPIRED = "expired"
    SUSPENDED = "suspended"
    PENDING_TRANSFER = "pending_transfer"

class Service(Base):
    __tablename__ = "services"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    customer_id = Column(Integer, ForeignKey("customers.id"))
    product_id = Column(Integer, ForeignKey("products.id"))
    order_id = Column(Integer, ForeignKey("orders.id"))
    service_name = Column(String(255))
    domain_name = Column(String(255))
    service_type = Column(Enum(ServiceType))
    service_status = Column(Enum(ServiceStatus), default=ServiceStatus.PENDING)
    billing_cycle = Column(Enum(BillingCycle))
    setup_date = Column(DateTime(timezone=True))
    expiry_date = Column(DateTime(timezone=True))
    next_due_date = Column(DateTime(timezone=True))
    auto_renew = Column(Boolean, default=True)
    service_config = Column(Text)  # JSON string
    usage_stats = Column(Text)  # JSON string
    suspension_reason = Column(Text)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    # Relationships
    customer = relationship("Customer")
    product = relationship("Product")
    order = relationship("Order")
    hosting_account = relationship("HostingAccount", uselist=False, back_populates="service")
    domain_registration = relationship("DomainRegistration", uselist=False, back_populates="service")
    
    def __repr__(self):
        return f"<Service {self.service_name}>"

class HostingAccount(Base):
    __tablename__ = "hosting_accounts"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    service_id = Column(Integer, ForeignKey("services.id"))
    customer_id = Column(Integer, ForeignKey("customers.id"))
    account_username = Column(String(100))
    # Legacy plaintext password storage is intentionally not mapped. Secrets
    # live in the AES-GCM service_credentials vault and are revealed only by
    # audited, re-authenticated endpoints.
    hosting_type = Column(Enum(HostingType))
    server_id = Column(String(100))
    control_panel = Column(Enum(ControlPanel), default=ControlPanel.CPANEL)
    disk_quota_mb = Column(Integer, default=0)
    disk_usage_mb = Column(Integer, default=0)
    bandwidth_quota_mb = Column(Integer, default=0)
    bandwidth_usage_mb = Column(Integer, default=0)
    email_accounts_limit = Column(Integer, default=0)
    email_accounts_used = Column(Integer, default=0)
    databases_limit = Column(Integer, default=0)
    databases_used = Column(Integer, default=0)
    subdomains_limit = Column(Integer, default=0)
    subdomains_used = Column(Integer, default=0)
    backup_enabled = Column(Boolean, default=True)
    ssl_enabled = Column(Boolean, default=False)
    ip_address = Column(String(45))
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    # Relationships
    service = relationship("Service", back_populates="hosting_account")
    customer = relationship("Customer")
    
    def __repr__(self):
        return f"<HostingAccount {self.account_username}>"

class DomainRegistration(Base):
    __tablename__ = "domain_registrations"
    
    id = Column(Integer, primary_key=True, index=True)
    uuid = Column(String(36), unique=True, index=True)
    service_id = Column(Integer, ForeignKey("services.id"))
    customer_id = Column(Integer, ForeignKey("customers.id"))
    domain_name = Column(String(255))
    extension = Column(String(20))
    registration_date = Column(DateTime(timezone=True), server_default=func.now())
    expiry_date = Column(DateTime(timezone=True))
    auto_renew = Column(Boolean, default=True)
    nameservers = Column(Text)  # JSON string
    dns_records = Column(Text)  # JSON string
    privacy_protection = Column(Boolean, default=False)
    lock_status = Column(Boolean, default=True)
    registrar_status = Column(Enum(RegistrarStatus), default=RegistrarStatus.ACTIVE)
    auth_code = Column(String(255))
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    # Relationships
    service = relationship("Service", back_populates="domain_registration")
    customer = relationship("Customer")
    
    def __repr__(self):
        return f"<DomainRegistration {self.domain_name}>"
