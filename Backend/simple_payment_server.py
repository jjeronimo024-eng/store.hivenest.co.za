"""
Simplified PayPal Payment Server
This standalone server handles PayPal payments without database dependency
"""
from fastapi import FastAPI, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
from datetime import datetime
from decimal import Decimal
import logging
import os
import requests
import base64
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Create FastAPI app
app = FastAPI(
    title="HiveNest Payment Gateway",
    version="1.0.0",
    description="Simplified payment processing for HiveNest"
)

# CORS configuration - allow all origins for testing
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ============================================================================
# PAYPAL CONFIGURATION
# ============================================================================

PAYPAL_MODE = os.getenv("PAYPAL_MODE", "sandbox")
PAYPAL_CLIENT_ID = os.getenv("PAYPAL_CLIENT_ID", "")
PAYPAL_CLIENT_SECRET = os.getenv("PAYPAL_CLIENT_SECRET", "")

# SMTP Configuration
SMTP_HOST = os.getenv("SMTP_HOST")
SMTP_PORT = int(os.getenv("SMTP_PORT", 587))
SMTP_USER = os.getenv("SMTP_USER")
SMTP_PASSWORD = os.getenv("SMTP_PASSWORD")

if PAYPAL_MODE == "sandbox":
    PAYPAL_API_BASE = "https://api-m.sandbox.paypal.com"
else:
    PAYPAL_API_BASE = "https://api-m.paypal.com"

logger.info(f"🚀 PayPal Payment Server Starting...")
logger.info(f"📍 Mode: {PAYPAL_MODE}")
logger.info(f"🔑 Client ID: {PAYPAL_CLIENT_ID[:20]}..." if PAYPAL_CLIENT_ID else "❌ Client ID not set")

# ============================================================================
# PYDANTIC MODELS
# ============================================================================

class CreateOrderRequest(BaseModel):
    items: List[Dict[str, Any]]
    currency: str = "USD"
    return_url: Optional[str] = None
    cancel_url: Optional[str] = None

class CreateOrderResponse(BaseModel):
    paypal_order_id: str
    status: str
    approval_url: Optional[str] = None

class CaptureOrderResponse(BaseModel):
    success: bool
    status: str
    capture_id: Optional[str] = None
    order_id: Optional[str] = None
    message: str

# ============================================================================
# PAYPAL OAUTH
# ============================================================================

def get_paypal_access_token() -> str:
    """Get PayPal OAuth 2.0 access token"""
    if not PAYPAL_CLIENT_ID or not PAYPAL_CLIENT_SECRET:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="PayPal credentials not configured"
        )
    
    auth_string = f"{PAYPAL_CLIENT_ID}:{PAYPAL_CLIENT_SECRET}"
    auth_b64 = base64.b64encode(auth_string.encode()).decode()
    
    headers = {
        "Authorization": f"Basic {auth_b64}",
        "Content-Type": "application/x-www-form-urlencoded"
    }
    
    try:
        response = requests.post(
            f"{PAYPAL_API_BASE}/v1/oauth2/token",
            headers=headers,
            data="grant_type=client_credentials",
            timeout=10
        )
        
        if response.status_code != 200:
            logger.error(f"PayPal OAuth failed: {response.text}")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail=f"PayPal authentication failed: {response.text}"
            )
        
        token_data = response.json()
        return token_data.get("access_token")
        
    except requests.RequestException as e:
        logger.error(f"PayPal OAuth error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to connect to PayPal: {str(e)}"
        )

# ============================================================================
# API ENDPOINTS
# ============================================================================

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "service": "HiveNest Payment Gateway",
        "status": "operational",
        "paypal_configured": bool(PAYPAL_CLIENT_ID),
        "mode": PAYPAL_MODE
    }

@app.get("/health")
async def health():
    """Health check"""
    return {
        "status": "healthy",
        "timestamp": datetime.utcnow().isoformat()
    }

@app.post("/api/payments/paypal/orders", response_model=CreateOrderResponse)
async def create_paypal_order(order_request: CreateOrderRequest):
    """
    Create PayPal order for checkout
    """
    try:
        logger.info("📝 Creating PayPal order...")
        
        # Get OAuth access token
        access_token = get_paypal_access_token()
        
        # Calculate totals
        subtotal = sum(
            Decimal(str(item.get('price', 0))) * int(item.get('quantity', 1))
            for item in order_request.items
        )
        
        # Prepare items for PayPal
        paypal_items = []
        for item in order_request.items:
            paypal_items.append({
                "name": item.get('name', 'Product')[:127],
                "description": item.get('description', '')[:127] or item.get('name', 'Product')[:127],
                "quantity": str(item.get('quantity', 1)),
                "unit_amount": {
                    "currency_code": order_request.currency,
                    "value": f"{Decimal(str(item.get('price', 0))):.2f}"
                }
            })
        
        # Build PayPal order payload
        order_payload = {
            "intent": "CAPTURE",
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
                "landing_page": "BILLING",
                "user_action": "PAY_NOW",
                "return_url": order_request.return_url or "https://hivenest.co.za/order-success.php",
                "cancel_url": order_request.cancel_url or "https://hivenest.co.za/checkout.php"
            }
        }
        
        # Make API request to PayPal
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {access_token}",
            "PayPal-Request-Id": f"HIVENEST-{datetime.utcnow().timestamp()}"
        }
        
        logger.info(f"💳 Sending order to PayPal: ${subtotal:.2f}")
        
        response = requests.post(
            f"{PAYPAL_API_BASE}/v2/checkout/orders",
            headers=headers,
            json=order_payload,
            timeout=15
        )
        
        if response.status_code not in [200, 201]:
            logger.error(f"PayPal order failed: {response.status_code} - {response.text}")
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
        
        logger.info(f"✅ PayPal order created: {paypal_order_id}")
        
        return CreateOrderResponse(
            paypal_order_id=paypal_order_id,
            status=order_status,
            approval_url=approval_url
        )
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error creating order: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to create order: {str(e)}"
        )

@app.post("/api/payments/paypal/orders/{order_id}/capture", response_model=CaptureOrderResponse)
async def capture_paypal_payment(order_id: str):
    """
    Capture payment for approved PayPal order
    """
    try:
        logger.info(f"💰 Capturing payment for order: {order_id}")
        
        # Get OAuth access token
        access_token = get_paypal_access_token()
        
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {access_token}",
            "PayPal-Request-Id": f"CAPTURE-{order_id}-{datetime.utcnow().timestamp()}"
        }
        
        response = requests.post(
            f"{PAYPAL_API_BASE}/v2/checkout/orders/{order_id}/capture",
            headers=headers,
            timeout=15
        )
        
        if response.status_code not in [200, 201]:
            logger.error(f"Capture failed: {response.status_code} - {response.text}")
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
            logger.info(f"✅ Payment captured: {capture_id}")
            
            return CaptureOrderResponse(
                success=True,
                status=capture_status,
                capture_id=capture_id,
                order_id=order_id,
                message="Payment successful! Your order is being processed."
            )
        else:
            logger.warning(f"⚠️ Payment incomplete: {capture_status}")
            return CaptureOrderResponse(
                success=False,
                status=capture_status,
                capture_id=capture_id,
                order_id=order_id,
                message=f"Payment status: {capture_status}"
            )
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Capture error: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to capture payment: {str(e)}"
        )

@app.get("/api/payments/config")
async def get_payment_config():
    """Get payment configuration for frontend"""
    return {
        "paypal": {
            "enabled": bool(PAYPAL_CLIENT_ID),
            "client_id": PAYPAL_CLIENT_ID,
            "mode": PAYPAL_MODE,
            "currency": "USD"
        }
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "simple_payment_server:app",
        host="0.0.0.0",
        port=8006,
        reload=True
    )
