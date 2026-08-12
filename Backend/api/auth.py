"""
Authentication API endpoints for HiveNest
"""
from fastapi import APIRouter, Depends, HTTPException, Response, status
import os
from sqlalchemy.orm import Session
from sqlalchemy import text
from pydantic import BaseModel, EmailStr
from typing import Optional
from datetime import datetime

from models.database import get_db
from models.customer import Customer, CustomerType, CustomerStatus
from models.admin import AdminUser, AdminRole
from core.auth import (
    verify_password, 
    get_password_hash, 
    create_customer_token, 
    create_admin_token,
    Token,
    generate_uuid
)
from core.dependencies import get_current_user
from core.dependencies import get_current_admin
from core.two_factor import (
    decrypt_secret, encrypt_secret, generate_secret, normalise_recovery_code,
    recovery_codes, verify_totp
)
import hashlib
import secrets
from urllib.parse import quote

router = APIRouter()

def set_auth_cookie(response: Response, name: str, access_token: str) -> None:
    response.set_cookie(
        key=name,
        value=access_token,
        max_age=30 * 60,
        httponly=True,
        secure=os.getenv("COOKIE_SECURE", "true").lower() == "true",
        samesite="lax",
        domain=os.getenv("COOKIE_DOMAIN", ".hivenest.co.za"),
        path="/"
    )

# Request models
class CustomerRegister(BaseModel):
    email: EmailStr
    password: str
    first_name: str
    last_name: str
    phone: Optional[str] = None
    company_name: Optional[str] = None
    customer_type: CustomerType = CustomerType.INDIVIDUAL
    country: Optional[str] = "United States"
    country_code: Optional[str] = "US"

class CustomerLogin(BaseModel):
    email: EmailStr
    password: str

class AdminLogin(BaseModel):
    username: str
    password: str
    challenge_token: Optional[str] = None
    two_factor_code: Optional[str] = None

class AdminTwoFactorAction(BaseModel):
    action: str
    code: Optional[str] = None
    password: Optional[str] = None
    enrollment_token: Optional[str] = None

class PasswordReset(BaseModel):
    email: EmailStr

# Response models
class CustomerResponse(BaseModel):
    id: int
    uuid: str
    email: str
    first_name: str
    last_name: str
    company_name: Optional[str]
    customer_type: CustomerType
    status: CustomerStatus
    email_verified: bool
    created_at: datetime
    
    class Config:
        from_attributes = True

class AdminResponse(BaseModel):
    id: int
    uuid: str
    username: str
    email: str
    first_name: str
    last_name: str
    role: AdminRole
    is_active: bool
    created_at: datetime
    
    class Config:
        from_attributes = True

class AuthResponse(BaseModel):
    token: Token
    user: dict

@router.post("/register", response_model=AuthResponse)
async def register_customer(
    customer_data: CustomerRegister,
    response: Response,
    db: Session = Depends(get_db)
):
    """Retired: PHP customer auth is the single customer identity authority."""
    raise HTTPException(
        status_code=status.HTTP_410_GONE,
        detail="Use /api/customer-auth.php?action=register"
    )
    
    # Check if email already exists
    existing_customer = db.query(Customer).filter(Customer.email == customer_data.email).first()
    if existing_customer:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Email already registered"
        )
    
    # Create new customer
    hashed_password = get_password_hash(customer_data.password)
    customer = Customer(
        uuid=generate_uuid(),
        email=customer_data.email,
        password_hash=hashed_password,
        first_name=customer_data.first_name,
        last_name=customer_data.last_name,
        phone=customer_data.phone,
        company_name=customer_data.company_name,
        customer_type=customer_data.customer_type,
        country=customer_data.country,
        country_code=customer_data.country_code,
        status=CustomerStatus.ACTIVE,
        email_verified=False
    )
    
    db.add(customer)
    db.commit()
    db.refresh(customer)
    
    # Create token
    token = create_customer_token(customer.id, customer.email)
    set_auth_cookie(response, "hivenest_access_token", token.access_token)
    
    return AuthResponse(
        token=token,
        user=CustomerResponse.model_validate(customer).dict()
    )

@router.post("/login", response_model=AuthResponse)
async def login_customer(
    login_data: CustomerLogin,
    response: Response,
    db: Session = Depends(get_db)
):
    """Retired: prevents bypassing verification, rate limits and customer 2FA."""
    raise HTTPException(
        status_code=status.HTTP_410_GONE,
        detail="Use /api/customer-auth.php?action=login"
    )
    
    # Find customer by email
    customer = db.query(Customer).filter(Customer.email == login_data.email).first()
    if not customer:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid email or password"
        )
    
    # Verify password
    if not verify_password(login_data.password, customer.password_hash):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid email or password"
        )
    
    # Check if customer is active
    if customer.status != CustomerStatus.ACTIVE:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Account is not active"
        )
    
    # Update last login
    customer.last_login = datetime.utcnow()
    db.commit()
    
    # Create token
    token = create_customer_token(customer.id, customer.email)
    set_auth_cookie(response, "hivenest_access_token", token.access_token)
    
    return AuthResponse(
        token=token,
        user=CustomerResponse.model_validate(customer).dict()
    )

@router.post("/admin/login")
async def login_admin(
    login_data: AdminLogin,
    response: Response,
    db: Session = Depends(get_db)
):
    """Admin login"""
    
    # Find admin by username
    admin = db.query(AdminUser).filter(AdminUser.username == login_data.username).first()
    if not admin:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid username or password"
        )
    
    # Verify password
    if not verify_password(login_data.password, admin.password_hash):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid username or password"
        )
    
    # Check if admin is active
    if not admin.is_active:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Account is not active"
        )
    
    if admin.two_factor_enabled:
        if not login_data.challenge_token or not login_data.two_factor_code:
            raw_token = secrets.token_hex(32)
            db.execute(text(
                "UPDATE two_factor_challenges SET consumed_at=NOW() "
                "WHERE account_type='admin' AND account_id=:admin_id AND consumed_at IS NULL"
            ), {"admin_id": admin.id})
            db.execute(text(
                "INSERT INTO two_factor_challenges "
                "(account_type,account_id,token_hash,expires_at) "
                "VALUES ('admin',:admin_id,:token_hash,DATE_ADD(NOW(), INTERVAL 5 MINUTE))"
            ), {"admin_id": admin.id, "token_hash": hashlib.sha256(raw_token.encode()).hexdigest()})
            db.commit()
            return {
                "authenticated": False,
                "two_factor_required": True,
                "challenge_token": raw_token,
                "message": "Enter the code from your authenticator app or a recovery code."
            }

        token_hash = hashlib.sha256(login_data.challenge_token.encode()).hexdigest()
        challenge = db.execute(text(
            "SELECT id,attempts FROM two_factor_challenges "
            "WHERE account_type='admin' AND account_id=:admin_id AND token_hash=:token_hash "
            "AND consumed_at IS NULL AND expires_at>NOW() AND attempts<5 LIMIT 1"
        ), {"admin_id": admin.id, "token_hash": token_hash}).mappings().first()
        if not challenge:
            raise HTTPException(status_code=401, detail="Verification session expired. Sign in again.")
        code = login_data.two_factor_code.strip()
        valid = verify_totp(decrypt_secret(admin.two_factor_secret or ""), code)
        if not valid:
            recovery_hash = hashlib.sha256(normalise_recovery_code(code).encode()).hexdigest()
            recovery = db.execute(text(
                "SELECT id FROM two_factor_recovery_codes "
                "WHERE account_type='admin' AND account_id=:admin_id AND code_hash=:code_hash "
                "AND used_at IS NULL LIMIT 1"
            ), {"admin_id": admin.id, "code_hash": recovery_hash}).mappings().first()
            if recovery:
                db.execute(text("UPDATE two_factor_recovery_codes SET used_at=NOW() WHERE id=:id"),
                           {"id": recovery["id"]})
                valid = True
        if not valid:
            db.execute(text("UPDATE two_factor_challenges SET attempts=attempts+1 WHERE id=:id"),
                       {"id": challenge["id"]})
            db.commit()
            raise HTTPException(status_code=401, detail="Authenticator or recovery code is invalid.")
        db.execute(text(
            "UPDATE two_factor_challenges SET consumed_at=NOW() WHERE id=:id AND consumed_at IS NULL"
        ), {"id": challenge["id"]})

    # Update last login
    admin.last_login = datetime.utcnow()
    db.commit()
    
    # Create token
    token = create_admin_token(admin.id, admin.email, admin.role)
    set_auth_cookie(response, "hivenest_admin_access_token", token.access_token)
    
    return AuthResponse(
        token=token,
        user=AdminResponse.model_validate(admin).dict()
    )

@router.get("/admin/two-factor")
async def admin_two_factor_status(admin: AdminUser = Depends(get_current_admin)):
    return {"enabled": bool(admin.two_factor_enabled)}

@router.post("/admin/two-factor")
async def admin_two_factor_manage(
    request: AdminTwoFactorAction,
    admin: AdminUser = Depends(get_current_admin),
    db: Session = Depends(get_db)
):
    action = request.action.strip().lower()
    if action == "start":
        if admin.two_factor_enabled:
            raise HTTPException(status_code=409, detail="Two-factor authentication is already enabled.")
        secret = generate_secret()
        encrypted = encrypt_secret(secret)
        # A pending secret is short-lived and remains encrypted in a challenge row token.
        pending_token = secrets.token_hex(32)
        db.execute(text(
            "INSERT INTO two_factor_challenges "
            "(account_type,account_id,token_hash,expires_at) "
            "VALUES ('admin',:admin_id,:token_hash,DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
        ), {"admin_id": admin.id, "token_hash": hashlib.sha256(("enroll:" + pending_token).encode()).hexdigest()})
        # Store the encrypted pending secret on the disabled account. It cannot be used at login until confirmed.
        admin.two_factor_secret = encrypted
        db.commit()
        label = quote(f"HiveNest:{admin.email}")
        return {
            "secret": secret,
            "enrollment_token": pending_token,
            "otpauth_uri": f"otpauth://totp/{label}?secret={secret}&issuer=HiveNest&algorithm=SHA1&digits=6&period=30"
        }

    if action == "confirm":
        enrollment_token = (request.enrollment_token or "").strip()
        challenge_hash = hashlib.sha256(("enroll:" + enrollment_token).encode()).hexdigest()
        challenge = db.execute(text(
            "SELECT id FROM two_factor_challenges WHERE account_type='admin' AND account_id=:admin_id "
            "AND token_hash=:token_hash AND consumed_at IS NULL AND expires_at>NOW() LIMIT 1"
        ), {"admin_id": admin.id, "token_hash": challenge_hash}).mappings().first()
        if not challenge or not admin.two_factor_secret:
            raise HTTPException(status_code=410, detail="Enrollment expired. Start again.")
        if not verify_totp(decrypt_secret(admin.two_factor_secret), request.code or ""):
            raise HTTPException(status_code=422, detail="Authenticator code is invalid.")
        codes = recovery_codes()
        db.execute(text(
            "DELETE FROM two_factor_recovery_codes WHERE account_type='admin' AND account_id=:admin_id"
        ), {"admin_id": admin.id})
        for code in codes:
            db.execute(text(
                "INSERT INTO two_factor_recovery_codes (account_type,account_id,code_hash) "
                "VALUES ('admin',:admin_id,:code_hash)"
            ), {"admin_id": admin.id,
                "code_hash": hashlib.sha256(normalise_recovery_code(code).encode()).hexdigest()})
        admin.two_factor_enabled = True
        admin.two_factor_confirmed_at = datetime.utcnow()
        admin.auth_version = (admin.auth_version or 1) + 1
        db.execute(text("UPDATE two_factor_challenges SET consumed_at=NOW() WHERE id=:id"),
                   {"id": challenge["id"]})
        db.commit()
        return {"enabled": True, "recovery_codes": codes,
                "message": "Two-factor authentication enabled. Store these recovery codes securely."}

    if action == "disable":
        if not verify_password(request.password or "", admin.password_hash):
            raise HTTPException(status_code=422, detail="Current password is incorrect.")
        code = (request.code or "").strip()
        valid = bool(admin.two_factor_secret) and verify_totp(decrypt_secret(admin.two_factor_secret), code)
        if not valid:
            code_hash = hashlib.sha256(normalise_recovery_code(code).encode()).hexdigest()
            recovery = db.execute(text(
                "SELECT id FROM two_factor_recovery_codes WHERE account_type='admin' "
                "AND account_id=:admin_id AND code_hash=:code_hash AND used_at IS NULL LIMIT 1"
            ), {"admin_id": admin.id, "code_hash": code_hash}).mappings().first()
            valid = recovery is not None
        if not valid:
            raise HTTPException(status_code=422, detail="Authenticator or recovery code is invalid.")
        admin.two_factor_enabled = False
        admin.two_factor_secret = None
        admin.two_factor_confirmed_at = None
        admin.auth_version = (admin.auth_version or 1) + 1
        db.execute(text(
            "DELETE FROM two_factor_recovery_codes WHERE account_type='admin' AND account_id=:admin_id"
        ), {"admin_id": admin.id})
        db.commit()
        return {"enabled": False, "message": "Two-factor authentication disabled."}

    raise HTTPException(status_code=400, detail="Unknown two-factor action.")

@router.post("/forgot-password")
async def forgot_password(
    reset_data: PasswordReset,
    db: Session = Depends(get_db)
):
    """Request password reset"""
    
    # Find customer by email
    customer = db.query(Customer).filter(Customer.email == reset_data.email).first()
    if not customer:
        # Don't reveal whether email exists or not
        return {"message": "If the email exists, you will receive a password reset link"}
    
    # TODO: Implement password reset email sending
    # For now, just return success message
    return {"message": "If the email exists, you will receive a password reset link"}

@router.get("/verify-email/{token}")
async def verify_email(token: str, db: Session = Depends(get_db)):
    """Verify customer email"""
    
    # TODO: Implement email verification
    # For now, just return success
    return {"message": "Email verified successfully"}

@router.get("/me")
async def get_current_user_info(
    current_user: dict = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get current authenticated user information"""
    
    user = current_user["user"]
    user_type = current_user["user_type"]
    
    if user_type == "customer":
        return {
            "user_type": "customer",
            "user": CustomerResponse.model_validate(user).dict()
        }
    else:
        return {
            "user_type": "admin",
            "user": AdminResponse.model_validate(user).dict()
        }
