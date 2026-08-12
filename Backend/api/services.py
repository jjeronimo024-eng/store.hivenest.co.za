"""
Services API endpoints for HiveNest
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import desc
from pydantic import BaseModel, field_validator
from typing import List, Optional, Dict, Any
from datetime import datetime
import json

from models.database import get_db
from models.service import Service, ServiceStatus, ServiceType, HostingAccount, DomainRegistration
from models.customer import Customer
from core.dependencies import get_current_customer

router = APIRouter()


def redact_service_config(value: Any) -> Dict[str, Any]:
    if isinstance(value, str):
        try:
            value = json.loads(value)
        except (TypeError, ValueError):
            return {}
    if not isinstance(value, dict):
        return {}
    redacted: Dict[str, Any] = {}
    sensitive_fragments = ("password", "passwd", "secret", "token", "api_key", "api-key", "private_key", "auth_code")
    for key, item in value.items():
        normalized = str(key).lower()
        if any(fragment in normalized for fragment in sensitive_fragments):
            redacted[str(key)] = "[vault-protected]"
        elif isinstance(item, dict):
            redacted[str(key)] = redact_service_config(item)
        elif isinstance(item, list):
            redacted[str(key)] = [
                redact_service_config(entry) if isinstance(entry, dict) else entry
                for entry in item
            ]
        else:
            redacted[str(key)] = item
    return redacted

# Response models
class HostingAccountResponse(BaseModel):
    id: int
    uuid: str
    account_username: str
    hosting_type: str
    control_panel: str
    disk_quota_mb: int
    disk_usage_mb: int
    bandwidth_quota_mb: int
    bandwidth_usage_mb: int
    email_accounts_limit: int
    email_accounts_used: int
    ssl_enabled: bool
    ip_address: Optional[str]
    
    class Config:
        from_attributes = True

class DomainRegistrationResponse(BaseModel):
    id: int
    uuid: str
    domain_name: str
    extension: str
    registration_date: datetime
    expiry_date: datetime
    auto_renew: bool
    privacy_protection: bool
    lock_status: bool
    registrar_status: str
    
    class Config:
        from_attributes = True

class ServiceResponse(BaseModel):
    id: int
    uuid: str
    service_name: str
    domain_name: Optional[str]
    service_type: ServiceType
    service_status: ServiceStatus
    billing_cycle: str
    setup_date: Optional[datetime]
    expiry_date: Optional[datetime]
    next_due_date: Optional[datetime]
    auto_renew: bool
    service_config: Optional[Dict[str, Any]]
    usage_stats: Optional[Dict[str, Any]]
    hosting_account: Optional[HostingAccountResponse]
    domain_registration: Optional[DomainRegistrationResponse]
    created_at: datetime

    @field_validator("service_config", mode="before")
    @classmethod
    def protect_service_config(cls, value: Any) -> Dict[str, Any]:
        return redact_service_config(value)
    
    class Config:
        from_attributes = True

@router.get("/", response_model=List[ServiceResponse])
async def get_customer_services(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db),
    service_type: Optional[ServiceType] = None,
    status: Optional[ServiceStatus] = None
):
    """Get customer services"""
    
    query = db.query(Service).filter(Service.customer_id == current_customer.id)
    
    if service_type:
        query = query.filter(Service.service_type == service_type)
    
    if status:
        query = query.filter(Service.service_status == status)
    
    services = query.order_by(desc(Service.created_at)).all()
    
    return [ServiceResponse.model_validate(service) for service in services]

@router.get("/{service_id}", response_model=ServiceResponse)
async def get_service(
    service_id: int,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Get specific service"""
    
    service = db.query(Service).filter(
        Service.id == service_id,
        Service.customer_id == current_customer.id
    ).first()
    
    if not service:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Service not found"
        )
    
    return ServiceResponse.model_validate(service)

@router.get("/hosting", response_model=List[ServiceResponse])
async def get_hosting_services(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Get hosting services"""
    
    services = db.query(Service).filter(
        Service.customer_id == current_customer.id,
        Service.service_type == ServiceType.HOSTING
    ).order_by(desc(Service.created_at)).all()
    
    return [ServiceResponse.model_validate(service) for service in services]

@router.get("/domains", response_model=List[ServiceResponse])
async def get_domain_services(
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Get domain services"""
    
    services = db.query(Service).filter(
        Service.customer_id == current_customer.id,
        Service.service_type == ServiceType.DOMAIN
    ).order_by(desc(Service.created_at)).all()
    
    return [ServiceResponse.model_validate(service) for service in services]

@router.post("/{service_id}/suspend")
async def suspend_service(
    service_id: int,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Request service suspension"""
    
    service = db.query(Service).filter(
        Service.id == service_id,
        Service.customer_id == current_customer.id
    ).first()
    
    if not service:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Service not found"
        )
    
    if service.service_status != ServiceStatus.ACTIVE:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Service must be active to suspend"
        )
    
    service.service_status = ServiceStatus.SUSPENDED
    service.suspension_reason = "Customer requested suspension"
    db.commit()
    
    return {"message": "Service suspension requested"}

@router.post("/{service_id}/unsuspend")
async def unsuspend_service(
    service_id: int,
    current_customer: Customer = Depends(get_current_customer),
    db: Session = Depends(get_db)
):
    """Request service unsuspension"""
    
    service = db.query(Service).filter(
        Service.id == service_id,
        Service.customer_id == current_customer.id
    ).first()
    
    if not service:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Service not found"
        )
    
    if service.service_status != ServiceStatus.SUSPENDED:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Service must be suspended to unsuspend"
        )
    
    service.service_status = ServiceStatus.ACTIVE
    service.suspension_reason = None
    db.commit()
    
    return {"message": "Service reactivation requested"}
