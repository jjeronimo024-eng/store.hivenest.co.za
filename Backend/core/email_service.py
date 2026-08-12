"""
Email Service for HiveNest
Handles all email notifications using SMTP
"""
import os
import smtplib
import logging
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from typing import List, Optional, Dict, Any
from datetime import datetime
from jinja2 import Template

logger = logging.getLogger(__name__)

class EmailService:
    """Email service for sending transactional emails"""
    
    def __init__(self):
        self.smtp_host = os.getenv('SMTP_HOST')
        self.smtp_port = int(os.getenv('SMTP_PORT', 587))
        self.smtp_user = os.getenv('SMTP_USER')
        self.smtp_password = os.getenv('SMTP_PASSWORD')
        self.from_email = os.getenv('SMTP_FROM_EMAIL', self.smtp_user)
        self.from_name = os.getenv('SMTP_FROM_NAME', 'HiveNest Matrix')
        
        if not all([self.smtp_host, self.smtp_user, self.smtp_password]):
            logger.warning("SMTP credentials not fully configured")
    
    def send_email(self, to_email: str, subject: str, html_body: str, 
                   text_body: Optional[str] = None) -> bool:
        """
        Send an email via SMTP
        
        Args:
            to_email: Recipient email address
            subject: Email subject
            html_body: HTML email body
            text_body: Plain text email body (optional)
        
        Returns:
            True if sent successfully, False otherwise
        """
        try:
            # Create message
            msg = MIMEMultipart('alternative')
            msg['Subject'] = subject
            msg['From'] = f"{self.from_name} <{self.from_email}>"
            msg['To'] = to_email
            msg['Date'] = datetime.utcnow().strftime('%a, %d %b %Y %H:%M:%S +0000')
            
            # Add plain text version if provided
            if text_body:
                part1 = MIMEText(text_body, 'plain')
                msg.attach(part1)
            
            # Add HTML version
            part2 = MIMEText(html_body, 'html')
            msg.attach(part2)
            
            # Connect to SMTP server
            logger.info(f"Connecting to SMTP server: {self.smtp_host}:{self.smtp_port}")
            
            with smtplib.SMTP(self.smtp_host, self.smtp_port, timeout=30) as server:
                server.ehlo()
                server.starttls()
                server.ehlo()
                server.login(self.smtp_user, self.smtp_password)
                
                # Send email
                server.send_message(msg)
                
            logger.info(f"Email sent successfully to {to_email}")
            return True
            
        except smtplib.SMTPAuthenticationError as e:
            logger.error(f"SMTP Authentication failed: {e}")
            return False
        except smtplib.SMTPException as e:
            logger.error(f"SMTP error sending email: {e}")
            return False
        except Exception as e:
            logger.error(f"Failed to send email: {e}", exc_info=True)
            return False
    
    def send_order_confirmation(self, customer_email: str, customer_name: str,
                               order_data: Dict[str, Any]) -> bool:
        """
        Send order confirmation email
        
        Args:
            customer_email: Customer email address
            customer_name: Customer name
            order_data: Order information dictionary
        
        Returns:
            True if sent successfully
        """
        subject = f"Order Confirmation - {order_data['order_number']}"
        
        html_template = """
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .order-summary { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .order-item { padding: 10px 0; border-bottom: 1px solid #eee; }
        .total { font-size: 1.2em; font-weight: bold; margin-top: 15px; padding-top: 15px; border-top: 2px solid #667eea; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; color: #888; margin-top: 30px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Order Confirmed!</h1>
            <p>Thank you for your order, {{ customer_name }}</p>
        </div>
        <div class="content">
            <p>Hi {{ customer_name }},</p>
            <p>Your order has been received and payment confirmed. We're now provisioning your services!</p>
            
            <div class="order-summary">
                <h2>Order Details</h2>
                <p><strong>Order Number:</strong> {{ order_number }}</p>
                <p><strong>Order Date:</strong> {{ order_date }}</p>
                <p><strong>Payment Method:</strong> PayPal</p>
                
                <h3 style="margin-top: 20px;">Items Ordered:</h3>
                {% for item in items %}
                <div class="order-item">
                    <strong>{{ item.name }}</strong>
                    {% if item.domain %}
                    <br><span style="color: #667eea;">Domain: {{ item.domain }}</span>
                    {% endif %}
                    <br>Quantity: {{ item.quantity }} × ${{ "%.2f"|format(item.price) }}
                </div>
                {% endfor %}
                
                <div class="total">
                    Total Amount: ${{ "%.2f"|format(total) }}
                </div>
            </div>
            
            <p><strong>What's Next?</strong></p>
            <ul>
                <li>Your services are being provisioned automatically</li>
                <li>You'll receive service activation emails shortly</li>
                <li>Access your services from the Client Portal</li>
            </ul>
            
            <center>
                <a href="https://cp.hivenest.co.za" class="button">Access Client Portal</a>
            </center>
            
            <div class="footer">
                <p>Questions? Contact us at support@hivenest.co.za</p>
                <p>&copy; 2025 HiveNest Matrix. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
        """
        
        template = Template(html_template)
        html_body = template.render(
            customer_name=customer_name,
            order_number=order_data['order_number'],
            order_date=order_data.get('order_date', datetime.utcnow().strftime('%B %d, %Y')),
            items=order_data.get('items', []),
            total=order_data.get('total', 0)
        )
        
        return self.send_email(customer_email, subject, html_body)
    
    def send_service_activation(self, customer_email: str, customer_name: str,
                               service_data: Dict[str, Any]) -> bool:
        """
        Send service activation email with credentials
        
        Args:
            customer_email: Customer email
            customer_name: Customer name
            service_data: Service details and credentials
        
        Returns:
            True if sent successfully
        """
        service_type = service_data.get('type', 'Service')
        subject = f"✅ {service_type} Activated - {service_data.get('name', '')}"
        
        html_template = """
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .service-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #11998e; }
        .credentials { background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 15px 0; font-family: monospace; }
        .button { display: inline-block; padding: 12px 30px; background: #11998e; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; color: #888; margin-top: 30px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Service Activated!</h1>
            <p>Your {{ service_type }} is ready to use</p>
        </div>
        <div class="content">
            <p>Hi {{ customer_name }},</p>
            <p>Great news! Your service has been successfully activated and is ready to use.</p>
            
            <div class="service-box">
                <h2>{{ service_name }}</h2>
                <p><strong>Service Type:</strong> {{ service_type }}</p>
                <p><strong>Status:</strong> <span style="color: #11998e;">✓ Active</span></p>
                {% if domain %}
                <p><strong>Domain:</strong> {{ domain }}</p>
                {% endif %}
                {% if expiry_date %}
                <p><strong>Expires:</strong> {{ expiry_date }}</p>
                {% endif %}
            </div>
            
            {% if credentials %}
            <div class="warning">
                <strong>⚠️ Important:</strong> Please save these credentials securely. You won't be able to view them again.
            </div>
            
            <div class="credentials">
                {% for key, value in credentials.items() %}
                <strong>{{ key }}:</strong> {{ value }}<br>
                {% endfor %}
            </div>
            {% endif %}
            
            {% if access_url %}
            <center>
                <a href="{{ access_url }}" class="button">Access Service</a>
                <a href="https://cp.hivenest.co.za" class="button">Client Portal</a>
            </center>
            {% endif %}
            
            <div class="footer">
                <p>Need help? Contact support@hivenest.co.za</p>
                <p>&copy; 2025 HiveNest Matrix. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
        """
        
        template = Template(html_template)
        html_body = template.render(
            customer_name=customer_name,
            service_type=service_type,
            service_name=service_data.get('name', 'Your Service'),
            domain=service_data.get('domain'),
            expiry_date=service_data.get('expiry_date'),
            credentials=service_data.get('credentials'),
            access_url=service_data.get('access_url')
        )
        
        return self.send_email(customer_email, subject, html_body)
    
    def send_payment_failed(self, customer_email: str, customer_name: str,
                           order_number: str, reason: str) -> bool:
        """Send payment failure notification"""
        subject = f"Payment Failed - Order {order_number}"
        
        html_body = f"""
        <h2>Payment Failed</h2>
        <p>Hi {customer_name},</p>
        <p>We were unable to process your payment for order {order_number}.</p>
        <p><strong>Reason:</strong> {reason}</p>
        <p>Please try again or contact support for assistance.</p>
        <p><a href="https://hivenest.co.za/checkout.php">Try Again</a></p>
        """
        
        return self.send_email(customer_email, subject, html_body)

# Create singleton instance
email_service = EmailService()
