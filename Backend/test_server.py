"""
HiveNest Backend Test Script
"""
import os
import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent))

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Create FastAPI app
app = FastAPI(
    title="HiveNest Matrix API",
    version="1.0.0",
    description="HiveNest Hosting Provider API - Cyberpunk Matrix Edition",
)

# CORS configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "message": "🌟 Welcome to HiveNest Matrix - Cyberpunk Hosting Provider",
        "version": "1.0.0",
        "status": "operational",
        "features": [
            "Customer Portal with Dashboard",
            "Product Catalog (Hosting, Domains, Servers)",
            "Order Management System", 
            "Service Management",
            "Support Ticket System",
            "Admin CRM",
            "JWT Authentication",
            "Complete MySQL Schema"
        ]
    }

@app.get("/api/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "api": "operational",
        "database": "configured (pending connection)",
        "version": "1.0.0"
    }

@app.get("/api/info")
async def api_info():
    """API information"""
    return {
        "api_name": "HiveNest Matrix",
        "version": "1.0.0",
        "endpoints": {
            "authentication": "/api/auth/*",
            "customers": "/api/customers/*", 
            "products": "/api/products/*",
            "orders": "/api/orders/*",
            "services": "/api/services/*",
            "support": "/api/support/*",
            "admin": "/api/admin/*"
        },
        "database_schema": {
            "tables": [
                "customers", "products", "product_categories",
                "orders", "order_items", "services", 
                "domain_registrations", "hosting_accounts",
                "support_tickets", "support_ticket_replies",
                "admin_users", "api_integration_logs",
                "system_settings", "website_themes",
                "promotion_codes", "domain_extensions"
            ]
        }
    }

if __name__ == "__main__":
    import uvicorn
    logger.info("🚀 Starting HiveNest Matrix API Test Server...")
    uvicorn.run(app, host="0.0.0.0", port=8002)