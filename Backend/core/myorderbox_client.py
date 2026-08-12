"""
MyOrderBox Reseller API Client
Provides integration with MyOrderBox (ResellerClub/LogicBoxes) API
"""
import os
import requests
import logging
from typing import Dict, List, Optional, Any
from urllib.parse import urlencode

logger = logging.getLogger(__name__)

class MyOrderBoxClient:
    """
    Client for MyOrderBox Reseller API
    Handles authentication and API requests
    """
    
    def __init__(self):
        self.reseller_id = os.getenv('MYORDERBOX_RESELLER_ID')
        self.api_key = os.getenv('MYORDERBOX_API_KEY')
        self.base_url = os.getenv('MYORDERBOX_BASE_URL', 'https://httpapi.com')
        self.domain_check_url = os.getenv('MYORDERBOX_DOMAIN_CHECK_URL', 'https://domaincheck.httpapi.com')
        self.test_url = os.getenv('MYORDERBOX_TEST_URL', 'https://test.httpapi.com')
        self.env = os.getenv('MYORDERBOX_ENV', 'production')
        
        if not self.reseller_id or not self.api_key:
            logger.error("MyOrderBox credentials not configured")
            raise ValueError("MyOrderBox API credentials missing in environment")
    
    def _get_auth_params(self) -> Dict[str, str]:
        """Get authentication parameters for API requests"""
        return {
            'auth-userid': self.reseller_id,
            'api-key': self.api_key
        }
    
    def _make_request(self, method: str, endpoint: str, params: Dict = None, data: Dict = None, 
                      use_domain_check_url: bool = False) -> Dict:
        """
        Make HTTP request to MyOrderBox API
        
        Args:
            method: HTTP method (GET, POST)
            endpoint: API endpoint path
            params: Query parameters
            data: Request body data
            use_domain_check_url: Use domain check URL instead of base URL
        
        Returns:
            API response as dictionary
        """
        base = self.domain_check_url if use_domain_check_url else self.base_url
        url = f"{base}{endpoint}"
        
        # Add authentication
        if params is None:
            params = {}
        params.update(self._get_auth_params())
        
        try:
            logger.info(f"MyOrderBox API Request: {method} {url}")
            
            if method.upper() == 'GET':
                response = requests.get(url, params=params, timeout=30)
            elif method.upper() == 'POST':
                response = requests.post(url, params=params, data=data, timeout=30)
            else:
                raise ValueError(f"Unsupported HTTP method: {method}")
            
            # Log response status
            logger.info(f"MyOrderBox API Response: {response.status_code}")
            
            # Handle errors
            if response.status_code >= 400:
                logger.error(f"API Error: {response.status_code} - {response.text}")
                return {
                    'success': False,
                    'error': response.text,
                    'status_code': response.status_code
                }
            
            # Parse JSON response
            try:
                result = response.json()
                return {'success': True, 'data': result, 'status_code': response.status_code}
            except ValueError:
                # Not JSON response
                return {'success': True, 'data': response.text, 'status_code': response.status_code}
                
        except requests.exceptions.RequestException as e:
            logger.error(f"Request failed: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    # ==================== DOMAIN OPERATIONS ====================
    
    def check_domain_availability(self, domain_names: List[str], tlds: List[str]) -> Dict:
        """
        Check availability of domain names
        
        Args:
            domain_names: List of domain names (without TLD)
            tlds: List of TLDs to check (e.g., ['com', 'net', 'org'])
        
        Returns:
            Dictionary with availability status for each domain+TLD combination
        """
        params = {}
        
        # Add domain names (can be multiple)
        for domain in domain_names:
            params[f'domain-name'] = domain
        
        # Add TLDs (can be multiple)
        for tld in tlds:
            params[f'tlds'] = tld
        
        return self._make_request(
            'GET',
            '/api/domains/available.json',
            params=params,
            use_domain_check_url=True
        )
    
    def register_domain(self, domain_name: str, years: int, customer_id: int, 
                       contacts: Dict, nameservers: List[str] = None) -> Dict:
        """
        Register a new domain
        
        Args:
            domain_name: Full domain name (e.g., 'example.com')
            years: Registration period in years
            customer_id: Customer ID in MyOrderBox
            contacts: Contact information (registrant, admin, tech, billing)
            nameservers: List of nameserver hostnames
        
        Returns:
            Registration result
        """
        params = {
            'domain-name': domain_name,
            'years': years,
            'customer-id': customer_id,
            'reg-contact-id': contacts.get('registrant'),
            'admin-contact-id': contacts.get('admin'),
            'tech-contact-id': contacts.get('tech'),
            'billing-contact-id': contacts.get('billing'),
            'invoice-option': 'NoInvoice',
            'purchase-privacy': 'false'
        }
        
        # Add nameservers if provided
        if nameservers:
            for i, ns in enumerate(nameservers, 1):
                params[f'ns{i}'] = ns
        
        return self._make_request('POST', '/api/domains/register.json', params=params)
    
    def transfer_domain(self, domain_name: str, auth_code: str, customer_id: int,
                       contacts: Dict, nameservers: List[str] = None) -> Dict:
        """
        Transfer a domain to this registrar
        
        Args:
            domain_name: Full domain name
            auth_code: Authorization/EPP code from current registrar
            customer_id: Customer ID in MyOrderBox
            contacts: Contact information
            nameservers: List of nameserver hostnames
        
        Returns:
            Transfer initiation result
        """
        params = {
            'domain-name': domain_name,
            'auth-code': auth_code,
            'customer-id': customer_id,
            'reg-contact-id': contacts.get('registrant'),
            'admin-contact-id': contacts.get('admin'),
            'tech-contact-id': contacts.get('tech'),
            'billing-contact-id': contacts.get('billing'),
            'invoice-option': 'NoInvoice'
        }
        
        if nameservers:
            for i, ns in enumerate(nameservers, 1):
                params[f'ns{i}'] = ns
        
        return self._make_request('POST', '/api/domains/transfer.json', params=params)
    
    def renew_domain(self, order_id: int, years: int, exp_date: str, 
                     invoice_option: str = 'NoInvoice') -> Dict:
        """
        Renew a domain
        
        Args:
            order_id: Domain order ID
            years: Number of years to renew
            exp_date: Current expiration date (timestamp)
            invoice_option: Invoice generation option
        
        Returns:
            Renewal result
        """
        params = {
            'order-id': order_id,
            'years': years,
            'exp-date': exp_date,
            'invoice-option': invoice_option
        }
        
        return self._make_request('POST', '/api/domains/renew.json', params=params)
    
    def search_domains(self, customer_id: Optional[int] = None, status: Optional[str] = None,
                      no_of_records: int = 50, page_no: int = 1) -> Dict:
        """
        Search for domains
        
        Args:
            customer_id: Filter by customer ID
            status: Filter by status (Active, Suspended, etc.)
            no_of_records: Number of records per page
            page_no: Page number
        
        Returns:
            List of domains matching criteria
        """
        params = {
            'no-of-records': no_of_records,
            'page-no': page_no
        }
        
        if customer_id:
            params['customer-id'] = customer_id
        if status:
            params['status'] = status
        
        return self._make_request('GET', '/api/domains/search.json', params=params)
    
    def get_domain_details(self, domain_name: str) -> Dict:
        """
        Get details of a domain by domain name
        
        Args:
            domain_name: Full domain name
        
        Returns:
            Domain details
        """
        params = {'domain-name': domain_name}
        return self._make_request('GET', '/api/domains/details-by-name.json', params=params)
    
    # ==================== CUSTOMER OPERATIONS ====================
    
    def create_customer(self, username: str, password: str, email: str,
                       name: str, company: str, address: Dict, phone: Dict) -> Dict:
        """
        Create a new customer account
        
        Args:
            username: Unique username
            password: Customer password
            email: Email address
            name: Customer name (first + last)
            company: Company name
            address: Address information
            phone: Phone information
        
        Returns:
            Customer creation result with customer ID
        """
        params = {
            'username': username,
            'passwd': password,
            'name': name,
            'company': company,
            'address-line-1': address.get('line1'),
            'city': address.get('city'),
            'state': address.get('state'),
            'country': address.get('country'),
            'zipcode': address.get('zipcode'),
            'phone-cc': phone.get('cc'),
            'phone': phone.get('number'),
            'lang-pref': 'en'
        }
        
        # Optional fields
        if address.get('line2'):
            params['address-line-2'] = address['line2']
        if address.get('line3'):
            params['address-line-3'] = address['line3']
        
        return self._make_request('POST', '/api/customers/signup.json', params=params)
    
    def get_customer_details(self, customer_id: int) -> Dict:
        """
        Get customer details by customer ID
        
        Args:
            customer_id: Customer ID
        
        Returns:
            Customer details
        """
        params = {'customer-id': customer_id}
        return self._make_request('GET', '/api/customers/details.json', params=params)
    
    # ==================== PRICING OPERATIONS ====================
    
    def get_customer_pricing(self, customer_id: int) -> Dict:
        """
        Get pricing for a customer
        
        Args:
            customer_id: Customer ID
        
        Returns:
            Pricing information
        """
        params = {'customer-id': customer_id}
        return self._make_request('GET', '/api/customers/prcing.json', params=params)
    
    def get_reseller_pricing(self, reseller_id: Optional[int] = None) -> Dict:
        """
        Get reseller pricing
        
        Args:
            reseller_id: Reseller ID (optional, defaults to authenticated reseller)
        
        Returns:
            Reseller pricing information
        """
        params = {}
        if reseller_id:
            params['reseller-id'] = reseller_id
        
        return self._make_request('GET', '/api/resellers/prcing.json', params=params)
    
    # ==================== HOSTING OPERATIONS ====================
    
    def add_hosting(self, customer_id: int, plan_id: int, domain_name: str,
                   months: int = 12) -> Dict:
        """
        Add a hosting order
        
        Args:
            customer_id: Customer ID
            plan_id: Hosting plan ID
            domain_name: Domain name for hosting
            months: Number of months
        
        Returns:
            Hosting order result
        """
        params = {
            'customer-id': customer_id,
            'plan-id': plan_id,
            'domain-name': domain_name,
            'months': months,
            'invoice-option': 'NoInvoice'
        }
        
        return self._make_request('POST', '/api/hosting/linux/add.json', params=params)
    
    def search_hosting_orders(self, customer_id: Optional[int] = None,
                            no_of_records: int = 50, page_no: int = 1) -> Dict:
        """
        Search hosting orders
        
        Args:
            customer_id: Filter by customer ID
            no_of_records: Records per page
            page_no: Page number
        
        Returns:
            List of hosting orders
        """
        params = {
            'no-of-records': no_of_records,
            'page-no': page_no
        }
        
        if customer_id:
            params['customer-id'] = customer_id
        
        return self._make_request('GET', '/api/hosting/linux/search.json', params=params)
    
    # ==================== SSL OPERATIONS ====================
    
    def add_ssl(self, customer_id: int, domain_name: str, months: int,
               plan_id: int, invoice_option: str = 'NoInvoice') -> Dict:
        """
        Add SSL certificate order
        
        Args:
            customer_id: Customer ID
            domain_name: Domain name for SSL
            months: Number of months
            plan_id: SSL plan ID
            invoice_option: Invoice option
        
        Returns:
            SSL order result
        """
        params = {
            'customer-id': customer_id,
            'domain-name': domain_name,
            'months': months,
            'plan-id': plan_id,
            'invoice-option': invoice_option
        }
        
        return self._make_request('POST', '/api/sslcerts/add.json', params=params)
    
    # ==================== UTILITY METHODS ====================
    
    def test_connection(self) -> bool:
        """
        Test API connection and authentication
        
        Returns:
            True if connection successful, False otherwise
        """
        try:
            result = self.get_reseller_pricing()
            return result.get('success', False)
        except Exception as e:
            logger.error(f"Connection test failed: {str(e)}")
            return False

# Create singleton instance
myorderbox_client = MyOrderBoxClient()
