-- Fix Domain Extensions Pricing
-- Run this to update domain prices and add missing TLDs

-- Fix .co.za from ZAR to USD
UPDATE domain_extensions 
SET register_price = 8.99,
    renew_price = 8.99,
    transfer_price = 8.99
WHERE extension = '.co.za';

-- Update .tech to correct price
UPDATE domain_extensions 
SET register_price = 39.99,
    renew_price = 39.99,
    transfer_price = 39.99
WHERE extension = '.tech';

-- Add missing TLDs
INSERT INTO domain_extensions (uuid, extension, register_price, renew_price, transfer_price, restore_price, min_years, max_years, is_active, is_popular, is_new, is_premium, category, description)
VALUES
('TLD-IO-001', '.io', 49.99, 49.99, 49.99, 150.00, 1, 10, 1, 1, 0, 0, 'generic', 'Popular for tech startups'),
('.dev', 15.99, 15.99, 15.99, 89.99, 1, 10, 1, 1, 0, 0, 'generic', 'Developers and tech projects'),
('TLD-BIZ-001', '.biz', 12.99, 12.99, 12.99, 79.99, 1, 10, 1, 0, 0, 0, 'generic', 'Business websites'),
('TLD-INFO-001', '.info', 11.99, 11.99, 11.99, 79.99, 1, 10, 1, 0, 0, 0, 'generic', 'Information websites')
ON DUPLICATE KEY UPDATE 
    register_price = VALUES(register_price),
    renew_price = VALUES(renew_price),
    transfer_price = VALUES(transfer_price);

-- Create product_addons table if not exists
CREATE TABLE IF NOT EXISTS `product_addons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `addon_name` varchar(255) NOT NULL,
  `addon_slug` varchar(255) NOT NULL,
  `addon_type` enum('domain_privacy','domain_extend','ssl','backup','security','other') NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `billing_cycle` enum('one_time','monthly','quarterly','annually') DEFAULT 'annually',
  `is_active` tinyint(1) DEFAULT 1,
  `applies_to_product_types` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `addon_slug` (`addon_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default addons
INSERT INTO `product_addons` (`uuid`, `addon_name`, `addon_slug`, `addon_type`, `description`, `price`, `billing_cycle`, `applies_to_product_types`) VALUES
('ADDON-001', 'Neural Privacy Shield', 'domain-privacy', 'domain_privacy', 'WHOIS privacy protection - Hide personal information from public database', 9.99, 'annually', '["domain","domain_transfer"]'),
('ADDON-002', 'Extended Registration', 'domain-extend', 'domain_extend', 'Extend domain registration by 1 year', 12.99, 'one_time', '["domain_transfer"]'),
('ADDON-003', 'SSL Certificate', 'ssl-basic', 'ssl', 'Basic SSL certificate for secure connections', 29.99, 'annually', '["domain","hosting"]'),
('ADDON-004', 'CodeGuard Backup', 'codeguard-backup', 'backup', 'Daily website backups with malware monitoring', 2.99, 'monthly', '["hosting"]'),
('ADDON-005', 'SiteLock Security', 'sitelock-security', 'security', 'Website security and malware protection', 19.99, 'monthly', '["hosting"]')
ON DUPLICATE KEY UPDATE 
    addon_name = VALUES(addon_name),
    price = VALUES(price);
