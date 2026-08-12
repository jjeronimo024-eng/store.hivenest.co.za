"""
PayPal Checkout Integration - Official Implementation
Following PayPal's official integration guide for Orders v2 API

This module provides:
1. PayPal order creation using Orders v2 API
2. Payment capture after buyer approval
3. Webhook handling for payment events
4. OAuth 2.0 token management

Reference: https://developer.paypal.com/docs/checkout/
"""
from fastapi import APIRouter, Depends, HTTPException, status, Request, Header
from sqlalchemy.orm import Session
from pydantic import BaseModel, Field
from typing import Dict, Any, Optional, List
from datetime import datetime
import logging
import os
import json
import requests
import base64
from decimal import Decimal

from models.database import get_db
from models.order import Order, OrderItem, OrderStatus, PaymentStatus, PaymentMethod
from models.customer import Customer
from core.dependencies import get_current_customer
from core.provisioning_service import provisioning_service
from core.email_service import email_service
from core.auth import generate_uuid

router = APIRouter()
logger = logging.getLogger(__name__)

# ============================================================================
# PAYPAL CONFIGURATION
# ============================================================================

PAYPAL_MODE = os.getenv("PAYPAL_MODE", "sandbox")  # sandbox or live
PAYPAL_CLIENT_ID = os.getenv("PAYPAL_CLIENT_ID", "")
PAYPAL_CLIENT_SECRET = os.getenv("PAYPAL_CLIENT_SECRET", "")

# Set API base URL based on mode
if PAYPAL_MODE == "sandbox":
    PAYPAL_API_BASE = "https://api-m.sandbox.paypal.com"
else:
    PAYPAL_API_BASE = "https://api-m.paypal.com"

logger.info(f"PayPal integration initialized - Mode: {PAYPAL_MODE}")

# ============================================================================
# PYDANTIC MODELS FOR REQUEST/RESPONSE
# ============================================================================

class PayPalItem(BaseModel):
    """Individual item in PayPal order"""
    name: str = Field(..., max_length=127)
    quantity: str
    unit_amount: Dict[str, str]
    description: Optional[str] = None

class PayPalAmount(BaseModel):
    """Amount details for PayPal order"""
    currency_code: str = "USD"
    value: str
    breakdown: Optional[Dict[str, Any]] = None

class CreateOrderRequest(BaseModel):
    """Request to create PayPal order"""
    items: List[Dict[str, Any]]
    currency: str = "USD"
    return_url: Optional[str] = None
    cancel_url: Optional[str] = None

class CreateOrderResponse(BaseModel):
    """Response from PayPal order creation"""
    paypal_order_id: str
    status: str
    approval_url: Optional[str] = None

class CaptureOrderRequest(BaseModel):
    """Request to capture PayPal payment"""
    paypal_order_id: str

class CaptureOrderResponse(BaseModel):
    """Response from payment capture"""
    success: bool
    status: str
    capture_id: Optional[str] = None
    order_id: Optional[str] = None
    message: str

class PaymentConfig(BaseModel):
    """Payment configuration for frontend"""
    paypal: Dict[str, Any]

# ============================================================================
# OAUTH 2.0 TOKEN MANAGEMENT
# ============================================================================

class PayPalAuthManager:
    """Manages PayPal OAuth 2.0 access tokens"""
    
    _access_token: Optional[str] = None
    _token_expiry: Optional[datetime] = None
    
    @classmethod
    def get_access_token(cls) -> str:
        """
        Get PayPal access token using OAuth 2.0 Client Credentials
        Automatically retrieves new token when expired
        """
        if not PAYPAL_CLIENT_ID or not PAYPAL_CLIENT_SECRET:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="PayPal credentials not configured. Please add PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET to .env file"
            )
        
        # Create Basic Auth header
        auth_string = f"{PAYPAL_CLIENT_ID}:{PAYPAL_CLIENT_SECRET}"
        auth_bytes = auth_string.encode('utf-8')
        auth_b64 = base64.b64encode(auth_bytes).decode('utf-8')
        
        headers = {
            "Authorization": f"Basic {auth_b64}",
            "Content-Type": "application/x-www-form-urlencoded"
        }
        
        data = "grant_type=client_credentials"
        
        try:
            response = requests.post(
                f"{PAYPAL_API_BASE}/v1/oauth2/token",
                headers=headers,
                data=data,
                timeout=10
            )
            
            if response.status_code != 200:
                logger.error(f"PayPal OAuth error: {response.status_code} - {response.text}")
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail=f"Failed to authenticate with PayPal: {response.text}"
                )
            
            token_data = response.json()
            access_token = token_data.get("access_token")
            
            if not access_token:
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail="No access token received from PayPal"
                )
            
            logger.info("PayPal access token obtained successfully")
            return access_token
            
        except requests.RequestException as e:
            logger.error(f"PayPal OAuth request failed: {str(e)}")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail=f"Failed to connect to PayPal: {str(e)}"
            )

# ============================================================================
# ORDERS CONTROLLER - Create Order (Step 2 from official guide)
# ============================================================================

@router.post("/paypal/orders", response_model=CreateOrderResponse)
async def create_paypal_order(
    order_request: CreateOrderRequest,
    db: Session = Depends(get_db)
):
    """
    Create PayPal order for checkout
    
    This endpoint:
    1. Creates a PayPal order with purchase units
    2. Returns order ID and approval URL
    3. Buyer is redirected to PayPal for approval
    
    Reference: https://developer.paypal.com/docs/api/orders/v2/#orders_create
    """
    try:
        # Get OAuth access token
        access_token = PayPalAuthManager.get_access_token()
        
        # Calculate totals
        subtotal = sum(
            Decimal(str(item.get('price', 0))) * int(item.get('quantity', 1)) 
            for item in order_request.items
        )
        
        # Prepare items for PayPal
        paypal_items = []
        for item in order_request.items:
            paypal_items.append({
                "name": item.get('name', 'Product')[:127],  # PayPal limit 127 chars
                "description": item.get('description', '')[:127],
                "quantity": str(item.get('quantity', 1)),
                "unit_amount": {
                    "currency_code": order_request.currency,
                    "value": f"{Decimal(str(item.get('price', 0))):.2f}"
                }
            })
        
        # Build PayPal order payload following official structure
        order_payload = {
            "intent": "CAPTURE",  # CAPTURE for immediate payment
            "purchase_units": [{
                "reference_id": f"HIVENEST_{datetime.utcnow().strftime('%Y%m%d_%H%M%S')}",
                "description": "HiveNest Digital Services",
                "amount": {
                    "currency_code": order_request.currency,
                    "value": f"{subtotal:.2f}",
                    "breakdown": {
                        "item_total": {
                            "currency_code": order_request.currency,
                            "value": f"{subtotal:.2f}"
                        }
                    }
                },
                "items": paypal_items
            }],
            "application_context": {
                "brand_name": "HiveNest Matrix",
                "landing_page": "BILLING",  # NO_PREFERENCE, LOGIN, or BILLING
                "user_action": "PAY_NOW",  # Shows "Pay Now" button
                "return_url": order_request.return_url or "https://hivenest.co.za/order-success.php",
                "cancel_url": order_request.cancel_url or "https://hivenest.co.za/checkout.php"
            }
        }
        
        # Add App Switch configuration for mobile
        order_payload["payment_source"] = {
            "paypal": {
                "experience_context": {
                    "payment_method_preference": "IMMEDIATE_PAYMENT_REQUIRED",
                    "brand_name": "HiveNest Matrix",
                    "locale": "en-US",
                    "landing_page": "BILLING",
                    "user_action": "PAY_NOW",
                    "return_url": order_request.return_url or "https://hivenest.co.za/order-success.php",
                    "cancel_url": order_request.cancel_url or "https://hivenest.co.za/checkout.php"
                }
            }
        }
        
        # Make API request to PayPal
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {access_token}",
            "PayPal-Request-Id": f"HIVENEST-{datetime.utcnow().timestamp()}"  # Idempotency
        }
        
        logger.info(f"Creating PayPal order with payload: {json.dumps(order_payload, indent=2)}")
        
        response = requests.post(
            f"{PAYPAL_API_BASE}/v2/checkout/orders",
            headers=headers,
            json=order_payload,
            timeout=15
        )
        
        if response.status_code not in [200, 201]:
            logger.error(f"PayPal order creation failed: {response.status_code} - {response.text}")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail=f"PayPal order creation failed: {response.text}"
            )
        
        paypal_order = response.json()
        paypal_order_id = paypal_order.get("id")
        order_status = paypal_order.get("status")
        
        # Extract approval URL
        approval_url = None
        for link in paypal_order.get("links", []):
            if link.get("rel") == "approve":
                approval_url = link.get("href")
                break
        
        logger.info(f"PayPal order created successfully: {paypal_order_id} - Status: {order_status}")
        
        return CreateOrderResponse(
            paypal_order_id=paypal_order_id,
            status=order_status,
            approval_url=approval_url
        )
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Unexpected error creating PayPal order: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to create PayPal order: {str(e)}"
        )

# ============================================================================
# PAYMENTS CONTROLLER - Capture Payment (Step 3 from official guide)
# ============================================================================

@router.post("/paypal/orders/{order_id}/capture", response_model=CaptureOrderResponse)
async def capture_paypal_payment(
    order_id: str,
    db: Session = Depends(get_db)
):
    """
    Capture payment for approved PayPal order
    
    This endpoint:
    1. Captures the payment after buyer approval
    2. Moves money from buyer to merchant
    3. Returns capture details and status
    
    Reference: https://developer.paypal.com/docs/api/orders/v2/#orders_capture
    """
    try:
        # Get OAuth access token
        access_token = PayPalAuthManager.get_access_token()
        
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {access_token}",
            "PayPal-Request-Id": f"CAPTURE-{order_id}-{datetime.utcnow().timestamp()}"
        }
        
        logger.info(f"Capturing payment for PayPal order: {order_id}")
        
        response = requests.post(
            f"{PAYPAL_API_BASE}/v2/checkout/orders/{order_id}/capture",
            headers=headers,
            timeout=15
        )
        
        if response.status_code not in [200, 201]:
            logger.error(f"PayPal capture failed: {response.status_code} - {response.text}")
            
            # Parse error for better user feedback
            error_data = response.json() if response.text else {}
            error_name = error_data.get("name", "UNKNOWN_ERROR")
            error_message = error_data.get("message", "Payment capture failed")
            
            return CaptureOrderResponse(
                success=False,
                status="FAILED",
                capture_id=None,
                order_id=order_id,
                message=f"{error_name}: {error_message}"
            )
        
        capture_data = response.json()
        capture_status = capture_data.get("status")
        
        # Extract capture ID
        capture_id = None
        if capture_data.get("purchase_units"):
            payments = capture_data["purchase_units"][0].get("payments", {})
            captures = payments.get("captures", [])
            if captures:
                capture_id = captures[0].get("id")
        
        if capture_status == "COMPLETED":
            logger.info(f"Payment captured successfully: {capture_id} for order {order_id}")
            
            #  Create order in database and trigger provisioning
            try:
                # Extract order details from PayPal response
                purchase_unit = capture_data.get("purchase_units", [{}])[0]
                items = purchase_unit.get("items", [])
                amount_info = purchase_unit.get("amount", {})
                
                # Get payer information
                payer = capture_data.get("payer", {})
                payer_name = payer.get("name", {})
                customer_name = f"{payer_name.get('given_name', '')} {payer_name.get('surname', '')}".strip() or "Customer"
                customer_email = payer.get("email_address", "unknown@email.com")
                
                # Find or create customer
                customer = db.query(Customer).filter(Customer.email == customer_email).first()
                
                if not customer:
                    # Create new customer from PayPal data
                    customer = Customer(
                        uuid=generate_uuid(),
                        email=customer_email,
                        full_name=customer_name,
                        status="active"
                    )
                    db.add(customer)
                    db.flush()
                    logger.info(f"Created new customer: {customer_email}")
                
                # Generate order number
                order_count = db.query(Order).count() + 1
                order_number = f"ORD-{datetime.now().year}-{order_count:06d}"
                
                # Create order
                order = Order(
                    uuid=generate_uuid(),
                    customer_id=customer.id,
                    order_number=order_number,
                    order_status=OrderStatus.PROCESSING,
                    payment_status=PaymentStatus.PAID,
                    payment_method=PaymentMethod.PAYPAL,
                    payment_reference=capture_id,
                    paypal_order_id=order_id,
                    subtotal=float(amount_info.get("value", 0)),
                    tax_amount=0.00,
                    discount_amount=0.00,
                    total_amount=float(amount_info.get("value", 0)),
                    currency=amount_info.get("currency_code", "USD"),
                    paid_at=datetime.utcnow()
                )
                
                db.add(order)
                db.flush()  # Get order ID
                
                # Create order items
                for idx, item in enumerate(items):
                    order_item = OrderItem(
                        uuid=generate_uuid(),
                        order_id=order.id,
                        product_name=item.get("name", f"Product {idx+1}"),
                        domain_name=item.get("description", ""),  # Domain might be in description
                        quantity=int(item.get("quantity", 1)),
                        unit_price=float(item.get("unit_amount", {}).get("value", 0)),
                        setup_fee=0.00,
                        billing_cycle="yearly",
                        line_total=float(item.get("unit_amount", {}).get("value", 0)) * int(item.get("quantity", 1))
                    )
                    db.add(order_item)
                
                db.commit()
                db.refresh(order)
                
                logger.info(f"✅ Order created: {order.order_number}")
                
                # Trigger service provisioning in background
                try:
                    provisioning_result = provisioning_service.provision_order(order, db)
                    logger.info(f"Provisioning completed: {provisioning_result}")
                except Exception as prov_error:
                    logger.error(f"Provisioning failed but order created: {prov_error}")
                    # Don't fail the payment - order is created, we can provision manually
                
            except Exception as e:
                logger.error(f"Error creating order after payment: {e}", exc_info=True)
                # Payment succeeded but order creation failed - log for manual intervention
                db.rollback()
            
            return CaptureOrderResponse(
                success=True,
                status=capture_status,
                capture_id=capture_id,
                order_id=order_id,
                message="Payment successful! Your order is being processed."
            )
        else:
            logger.warning(f"Payment capture incomplete: {capture_status}")
            return CaptureOrderResponse(
                success=False,
                status=capture_status,
                capture_id=capture_id,
                order_id=order_id,
                message=f"Payment status: {capture_status}. Please contact support."
            )
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Unexpected error capturing payment: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to capture payment: {str(e)}"
        )

# ============================================================================
# WEBHOOK HANDLER - Payment Events
# ============================================================================

@router.post("/paypal/webhook")
async def handle_paypal_webhook(
    request: Request,
    db: Session = Depends(get_db)
):
    """
    Handle PayPal webhook events (IPN - Instant Payment Notification)
    
    PayPal sends webhooks for various events:
    - PAYMENT.CAPTURE.COMPLETED
    - PAYMENT.CAPTURE.DENIED  
    - PAYMENT.CAPTURE.REFUNDED
    
    Reference: https://developer.paypal.com/docs/api-basics/notifications/webhooks/
    """
    try:
        body = await request.json()
        event_type = body.get("event_type")
        resource = body.get("resource", {})
        
        logger.info(f"PayPal webhook received: {event_type}")
        logger.debug(f"Webhook payload: {json.dumps(body, indent=2)}")
        
        # Handle different event types
        if event_type == "PAYMENT.CAPTURE.COMPLETED":
            # Payment was captured successfully
            logger.info("Payment capture completed via webhook")
            # TODO: Update order status in database
            # TODO: Trigger service provisioning
            
        elif event_type == "PAYMENT.CAPTURE.DENIED":
            # Payment was denied
            logger.warning(f"Payment denied via webhook: {resource}")
            # TODO: Update order status to failed
            
        elif event_type == "PAYMENT.CAPTURE.REFUNDED":
            # Payment was refunded
            logger.info("Payment refunded via webhook")
            # TODO: Update order status to refunded
            # TODO: Disable services
        
        return {"status": "success", "event_type": event_type}
    
    except Exception as e:
        logger.error(f"Webhook processing error: {str(e)}", exc_info=True)
        # Return 200 to prevent PayPal from retrying
        return {"status": "error", "message": str(e)}

# ============================================================================
# CONFIGURATION ENDPOINT - For Frontend
# ============================================================================

@router.get("/config", response_model=PaymentConfig)
async def get_payment_config():
    """
    Get payment configuration for frontend
    Returns PayPal Client ID and mode for JavaScript SDK
    """
    return PaymentConfig(
        paypal={
            "enabled": bool(PAYPAL_CLIENT_ID),
            "client_id": PAYPAL_CLIENT_ID,
            "mode": PAYPAL_MODE,
            "currency": "USD"
        }
    )

# ============================================================================
# REFUND ENDPOINT - Refund Captured Payment
# ============================================================================

@router.post("/paypal/captures/{capture_id}/refund")
async def refund_payment(
    capture_id: str,
    amount: Optional[str] = None,
    note: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """
    Refund a captured payment
    
    Reference: https://developer.paypal.com/docs/api/payments/v2/#captures_refund
    """
    try:
        access_token = PayPalAuthManager.get_access_token()
        
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {access_token}"
        }
        
        payload = {}
        if amount:
            payload["amount"] = {
                "currency_code": "USD",
                "value": amount
            }
        if note:
            payload["note_to_payer"] = note
        
        logger.info(f"Initiating refund for capture: {capture_id}")
        
        response = requests.post(
            f"{PAYPAL_API_BASE}/v2/payments/captures/{capture_id}/refund",
            headers=headers,
            json=payload if payload else None,
            timeout=15
        )
        
        if response.status_code not in [200, 201]:
            logger.error(f"Refund failed: {response.status_code} - {response.text}")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail=f"Refund failed: {response.text}"
            )
        
        refund_data = response.json()
        logger.info(f"Refund processed: {refund_data.get('id')}")
        
        return {
            "success": True,
            "refund_id": refund_data.get("id"),
            "status": refund_data.get("status")
        }
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Refund error: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Refund failed: {str(e)}"
        )
