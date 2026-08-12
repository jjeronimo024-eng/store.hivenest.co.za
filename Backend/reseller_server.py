"""
Lightweight Reseller API Server
Only handles MyOrderBox domain operations - no database required
"""
import os
import sys
from pathlib import Path

# Add the current directory to the Python path
sys.path.append(str(Path(__file__).parent))

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import logging

# Import only the reseller API router
from api import reseller

# Load environment
from dotenv import load_dotenv
load_dotenv()

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Create FastAPI app
app = FastAPI(
    title="HiveNest Reseller API",
    version="1.0.0",
    description="Lightweight API for domain operations via MyOrderBox",
)

# CORS configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include reseller routes with /api prefix
app.include_router(reseller.router, prefix="/api/reseller", tags=["Reseller"])

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "app": "HiveNest Reseller API",
        "status": "running",
        "endpoints": {
            "domain_check": "/api/reseller/domains/check-availability",
            "domain_pricing": "/api/reseller/pricing/reseller"
        }
    }

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "service": "reseller-api"}

@app.on_event("startup")
async def startup_event():
    """Application startup event"""
    logger.info("🚀 Starting HiveNest Reseller API...")
    logger.info("✅ MyOrderBox integration active")
    logger.info("🌟 Reseller API is ready!")

@app.on_event("shutdown")
async def shutdown_event():
    """Application shutdown event"""
    logger.info("🛑 Shutting down Reseller API...")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "reseller_server:app", 
        host="0.0.0.0", 
        port=8005,
        reload=False
    )
