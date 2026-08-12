"""
Reseller API endpoints
Provides endpoints for domain and hosting operations using MyOrderBox API
"""
from fastapi import APIRouter, HTTPException, Query
from typing import List, Optional, Dict, Any
from pydantic import BaseModel, EmailStr
import logging

from core.myorderbox_client import myorderbox_client

logger = logging.getLogger(__name__)

router = APIRouter()

# ==================== REQUEST MODELS ====================

class DomainAvailabilityRequest(BaseModel):
    domain_names: List[str]
    tlds: List[str]

class DomainRegistrationRequest(BaseModel):
    domain_name: str
    years: int = 1
    customer_id: int
    contacts: Dict[str, int]
    nameservers: Optional[List[str]] = None

class DomainTransferRequest(BaseModel):
    domain_name: str
    auth_code: str
    customer_id: int
    contacts: Dict[str, int]
    nameservers: Optional[List[str]] = None

class CustomerCreateRequest(BaseModel):
    username: str
    password: str
    email: EmailStr
    name: str
    company: str
    address: Dict[str, str]
    phone: Dict[str, str]

class HostingOrderRequest(BaseModel):
    customer_id: int
    plan_id: int
    domain_name: str
    months: int = 12

class SSLOrderRequest(BaseModel):
    customer_id: int
    domain_name: str
    months: int
    plan_id: int

# ==================== DOMAIN ENDPOINTS ====================

@router.get("/domains/check-availability")
async def check_domain_availability(
    domain: str = Query(..., description="Domain name without TLD"),
    tlds: str = Query("com,net,org", description="Comma-separated list of TLDs")
):
    """
    Check availability of domain names
    
    Example: /api/reseller/domains/check-availability?domain=example&tlds=com,net,org
    """
    try:
        tld_list = [tld.strip() for tld in tlds.split(',')]
        
        result = myorderbox_client.check_domain_availability(
            domain_names=[domain],
            tlds=tld_list
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'API request failed'))
        
        # Transform the response for easier consumption
        availability_data = result.get('data', {})
        
        formatted_results = []
        for domain_key, status_info in availability_data.items():
            # Parse domain and tld from key (format: domain.tld)
            parts = domain_key.split('.')
            tld = parts[-1] if len(parts) > 1 else ''
            
            formatted_results.append({
                'domain': domain_key,
                'tld': tld,
                'available': status_info.get('status') == 'available',
                'status': status_info.get('status'),
                'is_premium': 'costHash' in status_info,
                'pricing': status_info.get('costHash', {}),
                'trademark_claims': status_info.get('tm-claims-key') is not None
            })
        
        return {
            'success': True,
            'domain': domain,
            'results': formatted_results,
            'total': len(formatted_results)
        }
        
    except Exception as e:
        logger.error(f"Domain availability check failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/domains/register")
async def register_domain(request: DomainRegistrationRequest):
    """
    Register a new domain
    """
    try:
        result = myorderbox_client.register_domain(
            domain_name=request.domain_name,
            years=request.years,
            customer_id=request.customer_id,
            contacts=request.contacts,
            nameservers=request.nameservers
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Registration failed'))
        
        return {
            'success': True,
            'message': 'Domain registered successfully',
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Domain registration failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/domains/transfer")
async def transfer_domain(request: DomainTransferRequest):
    """
    Transfer a domain to this registrar
    """
    try:
        result = myorderbox_client.transfer_domain(
            domain_name=request.domain_name,
            auth_code=request.auth_code,
            customer_id=request.customer_id,
            contacts=request.contacts,
            nameservers=request.nameservers
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Transfer failed'))
        
        return {
            'success': True,
            'message': 'Domain transfer initiated successfully',
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Domain transfer failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/domains/search")
async def search_domains(
    customer_id: Optional[int] = None,
    status: Optional[str] = None,
    no_of_records: int = Query(50, le=500),
    page_no: int = Query(1, ge=1)
):
    """
    Search for domains
    """
    try:
        result = myorderbox_client.search_domains(
            customer_id=customer_id,
            status=status,
            no_of_records=no_of_records,
            page_no=page_no
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Search failed'))
        
        return {
            'success': True,
            'data': result.get('data'),
            'page': page_no,
            'records_per_page': no_of_records
        }
        
    except Exception as e:
        logger.error(f"Domain search failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/domains/details/{domain_name}")
async def get_domain_details(domain_name: str):
    """
    Get details of a specific domain
    """
    try:
        result = myorderbox_client.get_domain_details(domain_name)
        
        if not result.get('success'):
            raise HTTPException(status_code=404, detail=result.get('error', 'Domain not found'))
        
        return {
            'success': True,
            'domain': domain_name,
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Get domain details failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

# ==================== CUSTOMER ENDPOINTS ====================

@router.post("/customers/create")
async def create_customer(request: CustomerCreateRequest):
    """
    Create a new customer account in MyOrderBox
    """
    try:
        result = myorderbox_client.create_customer(
            username=request.username,
            password=request.password,
            email=request.email,
            name=request.name,
            company=request.company,
            address=request.address,
            phone=request.phone
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Customer creation failed'))
        
        return {
            'success': True,
            'message': 'Customer created successfully',
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Customer creation failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/customers/{customer_id}")
async def get_customer(customer_id: int):
    """
    Get customer details
    """
    try:
        result = myorderbox_client.get_customer_details(customer_id)
        
        if not result.get('success'):
            raise HTTPException(status_code=404, detail=result.get('error', 'Customer not found'))
        
        return {
            'success': True,
            'customer_id': customer_id,
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Get customer failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

# ==================== PRICING ENDPOINTS ====================

@router.get("/pricing/customer/{customer_id}")
async def get_customer_pricing(customer_id: int):
    """
    Get pricing for a specific customer
    """
    try:
        result = myorderbox_client.get_customer_pricing(customer_id)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Failed to get pricing'))
        
        return {
            'success': True,
            'customer_id': customer_id,
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Get customer pricing failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/pricing/reseller")
async def get_reseller_pricing(reseller_id: Optional[int] = None):
    """
    Get reseller pricing
    """
    try:
        result = myorderbox_client.get_reseller_pricing(reseller_id)
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Failed to get pricing'))
        
        return {
            'success': True,
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Get reseller pricing failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

# ==================== HOSTING ENDPOINTS ====================

@router.post("/hosting/add")
async def add_hosting(request: HostingOrderRequest):
    """
    Add a hosting order
    """
    try:
        result = myorderbox_client.add_hosting(
            customer_id=request.customer_id,
            plan_id=request.plan_id,
            domain_name=request.domain_name,
            months=request.months
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Hosting order failed'))
        
        return {
            'success': True,
            'message': 'Hosting order created successfully',
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"Hosting order failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/hosting/search")
async def search_hosting(
    customer_id: Optional[int] = None,
    no_of_records: int = Query(50, le=500),
    page_no: int = Query(1, ge=1)
):
    """
    Search hosting orders
    """
    try:
        result = myorderbox_client.search_hosting_orders(
            customer_id=customer_id,
            no_of_records=no_of_records,
            page_no=page_no
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'Search failed'))
        
        return {
            'success': True,
            'data': result.get('data'),
            'page': page_no,
            'records_per_page': no_of_records
        }
        
    except Exception as e:
        logger.error(f"Hosting search failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

# ==================== SSL ENDPOINTS ====================

@router.post("/ssl/add")
async def add_ssl(request: SSLOrderRequest):
    """
    Add SSL certificate order
    """
    try:
        result = myorderbox_client.add_ssl(
            customer_id=request.customer_id,
            domain_name=request.domain_name,
            months=request.months,
            plan_id=request.plan_id
        )
        
        if not result.get('success'):
            raise HTTPException(status_code=500, detail=result.get('error', 'SSL order failed'))
        
        return {
            'success': True,
            'message': 'SSL order created successfully',
            'data': result.get('data')
        }
        
    except Exception as e:
        logger.error(f"SSL order failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

# ==================== HEALTH CHECK ====================

@router.get("/health")
async def health_check():
    """
    Test MyOrderBox API connection
    """
    try:
        is_connected = myorderbox_client.test_connection()
        
        return {
            'success': is_connected,
            'status': 'connected' if is_connected else 'disconnected',
            'message': 'MyOrderBox API is accessible' if is_connected else 'MyOrderBox API connection failed'
        }
        
    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        return {
            'success': False,
            'status': 'error',
            'message': str(e)
        }
