"""
Main FastAPI application for HiveNest
"""
import os
import sys
from pathlib import Path

# Add the current directory to the Python path
sys.path.append(str(Path(__file__).parent))

from fastapi import FastAPI, APIRouter, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
import logging

# Import database and models
from models.database import init_db, test_connection
from models import customer, product, order, service, admin, support

# Import API routers
from api import auth, customers, orders, services, admin as admin_api, support as support_api
from api import products_simple as products, reseller, payments

# Load environment
from dotenv import load_dotenv
load_dotenv()

allowed_origins = [
    origin.strip().rstrip("/")
    for origin in os.getenv(
        "CORS_ALLOWED_ORIGINS",
        "https://hivenest.co.za,https://cp.hivenest.co.za,https://crm.hivenest.co.za"
    ).split(",")
    if origin.strip()
]

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Create FastAPI app
app = FastAPI(
    title=os.getenv("APP_NAME", "HiveNest Matrix"),
    version=os.getenv("APP_VERSION", "1.0.0"),
    description="HiveNest Hosting Provider API - Cyberpunk Matrix Edition",
)

# CORS configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Create main API router with prefix
api_router = APIRouter(prefix="/api")

# Include API routes
api_router.include_router(auth.router, prefix="/auth", tags=["Authentication"])
api_router.include_router(customers.router, prefix="/customers", tags=["Customers"])
api_router.include_router(products.router, prefix="/products", tags=["Products"])
api_router.include_router(orders.router, prefix="/orders", tags=["Orders"])
api_router.include_router(services.router, prefix="/services", tags=["Services"])
api_router.include_router(admin_api.router, prefix="/admin", tags=["Admin"])
api_router.include_router(support_api.router, prefix="/support", tags=["Support"])
api_router.include_router(reseller.router, prefix="/reseller", tags=["MyOrderBox Reseller"])
api_router.include_router(payments.router, prefix="/payments", tags=["Payments"])

# Include the API router
app.include_router(api_router)

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "message": "Welcome to HiveNest Matrix - Cyberpunk Hosting Provider",
        "version": os.getenv("APP_VERSION", "1.0.0"),
        "status": "operational"
    }

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    db_status = test_connection()
    return {
        "status": "healthy" if db_status else "unhealthy",
        "database": "connected" if db_status else "disconnected",
        "version": os.getenv("APP_VERSION", "1.0.0")
    }

@app.on_event("startup")
async def startup_event():
    """Application startup event"""
    logger.info("🚀 Starting HiveNest Matrix API...")
    
    # Test database connection
    if test_connection():
        logger.info("✅ Database connection successful")
        
        # Initialize database tables
        try:
            init_db()
            logger.info("✅ Database tables initialized")
        except Exception as e:
            logger.error(f"❌ Database initialization failed: {e}")
    else:
        logger.error("❌ Database connection failed")
    
    logger.info("🌟 HiveNest Matrix API is ready!")

@app.on_event("shutdown")
async def shutdown_event():
    """Application shutdown event"""
    logger.info("🛑 Shutting down HiveNest Matrix API...")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "server:app", 
        host="0.0.0.0", 
        port=8005,  # Use port 8005 to avoid conflicts
        reload=True if os.getenv("DEBUG") == "true" else False
    )
