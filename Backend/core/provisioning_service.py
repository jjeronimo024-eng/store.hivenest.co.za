"""
Service Provisioning Module for HiveNest
Handles automatic provisioning of services after payment
"""
import os
import logging
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
from sqlalchemy.orm import Session

from core.myorderbox_client import myorderbox_client
from core.email_service import email_service
from models.order import Order, OrderItem, OrderStatus, PaymentStatus
from models.service import Service, ServiceStatus, ServiceType
from models.customer import Customer
from core.auth import generate_uuid

logger = logging.getLogger(__name__)

class ProvisioningService:
    """Handles automatic service provisioning"""
    
    def __init__(self):
        self.myorderbox = myorderbox_client
        self.email = email_service
    
    def provision_order(self, order: Order, db: Session) -> Dict[str, Any]:
        """
        Provision all services in an order
        
        Args:
            order: Order object with items
            db: Database session
        
        Returns:
            Provisioning result with success status and details
        """
        logger.info(f"Starting provisioning for order: {order.order_number}")
        
        results = {
            'success': True,
            'order_id': order.id,
            'order_number': order.order_number,
            'services_provisioned': [],
            'services_failed': [],
            'errors': []
        }
        
        try:
            # Get customer
            customer = db.query(Customer).filter(Customer.id == order.customer_id).first()
            if not customer:
                raise Exception(f"Customer not found for order {order.order_number}")
            
            # Get or create MyOrderBox customer ID
            myorderbox_customer_id = self._ensure_myorderbox_customer(customer, db)
            
            # Provision each item
            for item in order.items:
                try:
                    service_result = self._provision_item(
                        item, 
                        customer, 
                        myorderbox_customer_id,
                        db
                    )
                    
                    if service_result['success']:
                        results['services_provisioned'].append(service_result)
                        logger.info(f"✅ Provisioned: {item.product_name}")
                    else:
                        results['services_failed'].append(service_result)
                        logger.error(f"❌ Failed: {item.product_name} - {service_result.get('error')}")
                        results['success'] = False
                
                except Exception as e:
                    error_msg = f"Error provisioning {item.product_name}: {str(e)}"
                    logger.error(error_msg, exc_info=True)
                    results['errors'].append(error_msg)
                    results['services_failed'].append({
                        'item_id': item.id,
                        'product_name': item.product_name,
                        'error': str(e)
                    })
                    results['success'] = False
            
            # Update order status
            if results['success'] and len(results['services_provisioned']) > 0:
                order.order_status = OrderStatus.COMPLETED
                order.provisioned_at = datetime.utcnow()
                logger.info(f"✅ Order {order.order_number} completed successfully")
            elif len(results['services_failed']) > 0:
                order.order_status = OrderStatus.PROCESSING
                logger.warning(f"⚠️ Order {order.order_number} partially completed")
            
            db.commit()
            
            # Send order confirmation email
            self._send_order_confirmation(order, customer, results)
            
        except Exception as e:
            logger.error(f"Provisioning failed for order {order.order_number}: {e}", exc_info=True)
            results['success'] = False
            results['errors'].append(str(e))
            
            # Update order status to failed
            order.order_status = OrderStatus.FAILED
            db.commit()
        
        return results
    
    def _ensure_myorderbox_customer(self, customer: Customer, db: Session) -> int:
        """
        Ensure customer exists in MyOrderBox, create if not exists
        
        Returns:
            MyOrderBox customer ID
        """
        # Check if customer already has MyOrderBox ID
        if customer.myorderbox_customer_id:
            logger.info(f"Using existing MyOrderBox customer ID: {customer.myorderbox_customer_id}")
            return int(customer.myorderbox_customer_id)
        
        # Create customer in MyOrderBox
        logger.info(f"Creating customer in MyOrderBox: {customer.email}")
        
        try:
            result = self.myorderbox.create_customer(
                username=customer.email.split('@')[0] + str(customer.id),
                password=generate_uuid()[:16],  # Random secure password
                email=customer.email,
                name=customer.full_name,
                company=customer.company or "Individual",
                address={
                    'line1': customer.address_line1 or "123 Main St",
                    'city': customer.city or "Unknown",
                    'state': customer.state or "Unknown",
                    'country': customer.country or "US",
                    'zipcode': customer.postal_code or "00000"
                },
                phone={
                    'cc': '1',  # Default country code
                    'number': customer.phone.replace('+', '').replace('-', '').replace(' ', '')[:15] if customer.phone else '0000000000'
                }
            )
            
            if result.get('success'):
                myorderbox_id = result['data'].get('customerid') or result['data'].get('id')
                customer.myorderbox_customer_id = str(myorderbox_id)
                db.commit()
                logger.info(f"✅ Created MyOrderBox customer: {myorderbox_id}")
                return int(myorderbox_id)
            else:
                raise Exception(f"MyOrderBox customer creation failed: {result.get('error')}")
        
        except Exception as e:
            logger.error(f"Failed to create MyOrderBox customer: {e}")
            # Use a fallback - assign a default customer ID for testing
            # In production, this should be handled properly
            raise Exception(f"Cannot proceed without MyOrderBox customer: {e}")
    
    def _provision_item(self, item: OrderItem, customer: Customer, 
                       myorderbox_customer_id: int, db: Session) -> Dict[str, Any]:
        """
        Provision a single order item
        
        Returns:
            Dictionary with provisioning result
        """
        product_type = self._determine_service_type(item.product_name)
        
        if product_type == ServiceType.DOMAIN:
            return self._provision_domain(item, customer, myorderbox_customer_id, db)
        elif product_type == ServiceType.HOSTING:
            return self._provision_hosting(item, customer, myorderbox_customer_id, db)
        elif product_type == ServiceType.SSL:
            return self._provision_ssl(item, customer, myorderbox_customer_id, db)
        elif product_type == ServiceType.EMAIL:
            return self._provision_email(item, customer, myorderbox_customer_id, db)
        else:
            return {
                'success': False,
                'item_id': item.id,
                'product_name': item.product_name,
                'error': 'Unknown product type'
            }
    
    def _provision_domain(self, item: OrderItem, customer: Customer,
                         myorderbox_customer_id: int, db: Session) -> Dict[str, Any]:
        """Provision domain registration"""
        logger.info(f"Provisioning domain: {item.domain_name}")
        
        try:
            # For MVP, we'll create a service record without actual MyOrderBox call
            # In production, uncomment the MyOrderBox API call
            
            # TODO: Implement actual domain registration
            # result = self.myorderbox.register_domain(
            #     domain_name=item.domain_name,
            #     years=1,
            #     customer_id=myorderbox_customer_id,
            #     contacts={'registrant': contact_id, 'admin': contact_id, ...},
            #     nameservers=['ns1.hivenest.co.za', 'ns2.hivenest.co.za']
            # )
            
            # Create service record
            service = Service(
                uuid=generate_uuid(),
                customer_id=customer.id,
                order_id=item.order_id,
                service_type=ServiceType.DOMAIN,
                service_name=item.domain_name,
                myorderbox_order_id="MOB-" + generate_uuid()[:8],
                service_status=ServiceStatus.ACTIVE,
                activation_date=datetime.utcnow(),
                expiry_date=datetime.utcnow() + timedelta(days=365),
                auto_renew=True
            )
            
            db.add(service)
            db.commit()
            
            # Send activation email
            self.email.send_service_activation(
                customer.email,
                customer.full_name,
                {
                    'type': 'Domain',
                    'name': item.domain_name,
                    'domain': item.domain_name,
                    'expiry_date': service.expiry_date.strftime('%B %d, %Y'),
                    'credentials': {
                        'Domain': item.domain_name,
                        'Nameservers': 'ns1.hivenest.co.za, ns2.hivenest.co.za'
                    },
                    'access_url': 'https://cp.hivenest.co.za/domains'
                }
            )
            
            return {
                'success': True,
                'item_id': item.id,
                'service_id': service.id,
                'product_name': item.product_name,
                'service_name': item.domain_name,
                'myorderbox_order_id': service.myorderbox_order_id
            }
        
        except Exception as e:
            logger.error(f"Domain provisioning failed: {e}")
            return {
                'success': False,
                'item_id': item.id,
                'product_name': item.product_name,
                'error': str(e)
            }
    
    def _provision_hosting(self, item: OrderItem, customer: Customer,
                          myorderbox_customer_id: int, db: Session) -> Dict[str, Any]:
        """Provision hosting service"""
        logger.info(f"Provisioning hosting: {item.domain_name}")
        
        try:
            # Create service record
            service = Service(
                uuid=generate_uuid(),
                customer_id=customer.id,
                order_id=item.order_id,
                service_type=ServiceType.HOSTING,
                service_name=f"{item.product_name} - {item.domain_name}",
                myorderbox_order_id="MOB-" + generate_uuid()[:8],
                service_status=ServiceStatus.ACTIVE,
                activation_date=datetime.utcnow(),
                expiry_date=datetime.utcnow() + timedelta(days=365),
                auto_renew=True
            )
            
            db.add(service)
            db.commit()
            
            # Send activation email
            self.email.send_service_activation(
                customer.email,
                customer.full_name,
                {
                    'type': 'Hosting',
                    'name': item.product_name,
                    'domain': item.domain_name,
                    'expiry_date': service.expiry_date.strftime('%B %d, %Y'),
                    'credentials': {
                        'cPanel URL': f'https://cpanel.hivenest.co.za',
                        'Username': customer.email.split('@')[0],
                        'Domain': item.domain_name
                    },
                    'access_url': 'https://cp.hivenest.co.za/hosting'
                }
            )
            
            return {
                'success': True,
                'item_id': item.id,
                'service_id': service.id,
                'product_name': item.product_name,
                'service_name': service.service_name,
                'myorderbox_order_id': service.myorderbox_order_id
            }
        
        except Exception as e:
            logger.error(f"Hosting provisioning failed: {e}")
            return {
                'success': False,
                'item_id': item.id,
                'product_name': item.product_name,
                'error': str(e)
            }
    
    def _provision_ssl(self, item: OrderItem, customer: Customer,
                      myorderbox_customer_id: int, db: Session) -> Dict[str, Any]:
        """Provision SSL certificate"""
        logger.info(f"Provisioning SSL: {item.domain_name}")
        
        try:
            service = Service(
                uuid=generate_uuid(),
                customer_id=customer.id,
                order_id=item.order_id,
                service_type=ServiceType.SSL,
                service_name=f"SSL Certificate - {item.domain_name}",
                myorderbox_order_id="MOB-" + generate_uuid()[:8],
                service_status=ServiceStatus.ACTIVE,
                activation_date=datetime.utcnow(),
                expiry_date=datetime.utcnow() + timedelta(days=365),
                auto_renew=True
            )
            
            db.add(service)
            db.commit()
            
            self.email.send_service_activation(
                customer.email,
                customer.full_name,
                {
                    'type': 'SSL Certificate',
                    'name': 'SSL Certificate',
                    'domain': item.domain_name,
                    'expiry_date': service.expiry_date.strftime('%B %d, %Y'),
                    'access_url': 'https://cp.hivenest.co.za/ssl'
                }
            )
            
            return {
                'success': True,
                'item_id': item.id,
                'service_id': service.id,
                'product_name': item.product_name,
                'service_name': service.service_name
            }
        
        except Exception as e:
            return {
                'success': False,
                'item_id': item.id,
                'product_name': item.product_name,
                'error': str(e)
            }
    
    def _provision_email(self, item: OrderItem, customer: Customer,
                        myorderbox_customer_id: int, db: Session) -> Dict[str, Any]:
        """Provision email service"""
        logger.info(f"Provisioning email: {item.domain_name}")
        
        try:
            service = Service(
                uuid=generate_uuid(),
                customer_id=customer.id,
                order_id=item.order_id,
                service_type=ServiceType.EMAIL,
                service_name=f"Email Service - {item.domain_name}",
                myorderbox_order_id="MOB-" + generate_uuid()[:8],
                service_status=ServiceStatus.ACTIVE,
                activation_date=datetime.utcnow(),
                expiry_date=datetime.utcnow() + timedelta(days=365),
                auto_renew=True
            )
            
            db.add(service)
            db.commit()
            
            self.email.send_service_activation(
                customer.email,
                customer.full_name,
                {
                    'type': 'Email Service',
                    'name': item.product_name,
                    'domain': item.domain_name,
                    'expiry_date': service.expiry_date.strftime('%B %d, %Y'),
                    'access_url': 'https://cp.hivenest.co.za/email'
                }
            )
            
            return {
                'success': True,
                'item_id': item.id,
                'service_id': service.id,
                'product_name': item.product_name,
                'service_name': service.service_name
            }
        
        except Exception as e:
            return {
                'success': False,
                'item_id': item.id,
                'product_name': item.product_name,
                'error': str(e)
            }
    
    def _determine_service_type(self, product_name: str) -> ServiceType:
        """Determine service type from product name"""
        product_lower = product_name.lower()
        
        if any(word in product_lower for word in ['domain', 'registration', 'transfer']):
            return ServiceType.DOMAIN
        elif any(word in product_lower for word in ['hosting', 'vps', 'dedicated', 'server']):
            return ServiceType.HOSTING
        elif any(word in product_lower for word in ['ssl', 'certificate', 'https']):
            return ServiceType.SSL
        elif any(word in product_lower for word in ['email', 'mail', 'workspace']):
            return ServiceType.EMAIL
        else:
            return ServiceType.OTHER
    
    def _send_order_confirmation(self, order: Order, customer: Customer,
                                provisioning_results: Dict[str, Any]):
        """Send order confirmation email"""
        try:
            order_data = {
                'order_number': order.order_number,
                'order_date': order.created_at.strftime('%B %d, %Y'),
                'items': [],
                'total': float(order.total_amount)
            }
            
            for item in order.items:
                order_data['items'].append({
                    'name': item.product_name,
                    'domain': item.domain_name,
                    'quantity': item.quantity,
                    'price': float(item.unit_price)
                })
            
            self.email.send_order_confirmation(
                customer.email,
                customer.full_name,
                order_data
            )
        except Exception as e:
            logger.error(f"Failed to send order confirmation: {e}")

# Create singleton instance
provisioning_service = ProvisioningService()
