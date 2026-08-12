"""
FastAPI dependencies for HiveNest
"""
from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.orm import Session
from typing import Optional

from models.database import get_db
from models.customer import Customer
from models.admin import AdminUser
from .auth import verify_token, TokenData

# Security scheme
security = HTTPBearer(auto_error=False)

def _token_from_request(request: Request, credentials: Optional[HTTPAuthorizationCredentials], cookie_name: str) -> str:
    token = credentials.credentials if credentials else request.cookies.get(cookie_name)
    if not token:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Authentication required")
    return token

def get_current_user(
    request: Request,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security),
    db: Session = Depends(get_db)
) -> dict:
    """Get current authenticated user (customer or admin)"""
    token = credentials.credentials if credentials else (
        request.cookies.get("hivenest_access_token") or request.cookies.get("hivenest_admin_access_token")
    )
    if not token:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Authentication required")
    token_data = verify_token(token)
    
    if token_data.user_type == "customer":
        user = db.query(Customer).filter(Customer.id == int(token_data.user_id)).first()
        if user is None:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="User not found"
            )
        return {"user": user, "user_type": "customer"}
    
    elif token_data.user_type == "admin":
        user = db.query(AdminUser).filter(AdminUser.id == int(token_data.user_id)).first()
        if user is None:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Admin not found"
            )
        return {"user": user, "user_type": "admin"}
    
    else:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid user type"
        )

def get_current_customer(
    request: Request,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security),
    db: Session = Depends(get_db)
) -> Customer:
    """Get current authenticated customer"""
    token = _token_from_request(request, credentials, "hivenest_access_token")
    token_data = verify_token(token)
    
    if token_data.user_type != "customer":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Customer access required"
        )
    
    customer = db.query(Customer).filter(Customer.id == int(token_data.user_id)).first()
    if customer is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Customer not found"
        )
    
    return customer

def get_current_admin(
    request: Request,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security),
    db: Session = Depends(get_db)
) -> AdminUser:
    """Get current authenticated admin"""
    token = _token_from_request(request, credentials, "hivenest_admin_access_token")
    token_data = verify_token(token)
    
    if token_data.user_type != "admin":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Admin access required"
        )
    
    admin = db.query(AdminUser).filter(AdminUser.id == int(token_data.user_id)).first()
    if admin is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Admin not found"
        )
    
    return admin

def require_admin_role(required_roles: list = None):
    """Decorator to require specific admin roles"""
    def role_checker(admin: AdminUser = Depends(get_current_admin)):
        if required_roles and admin.role not in required_roles:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Insufficient permissions"
            )
        return admin
    return role_checker
