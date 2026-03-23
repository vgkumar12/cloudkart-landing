SET FOREIGN_KEY_CHECKS = 0;
-- DEFAULT Settings and Data for new stores

-- DEFAULT Categories (generic starter data, overridden by store owner)
INSERT INTO `#__categories` (name, slug, description, is_active) VALUES
('Products', 'products', 'All products', 1),
('Featured', 'featured', 'Featured and bestseller items', 1),
('New Arrivals', 'new-arrivals', 'Latest additions', 1);

-- General Settings (bootstrapStore() overwrites site_name, contact_email, active_theme, site_description)
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('site_name',             'My Store',              'string',  1),
('site_logo',             '',                      'string',  1),
('site_description',      'Welcome to my store',   'string',  1),
('contact_email',         'support@mystore.com',   'string',  1),
('contact_phone',         '+91 00000 00000',        'string',  1),
('currency_symbol',       '₹',                     'string',  1),
('currency_code',         'INR',                   'string',  1),
('active_theme',          'general',               'string',  1);

-- Site / Business Info
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('company_name',          '',   'string', 1),
('site_phone',            '',   'string', 1),
('site_address',          '',   'string', 1),
('site_hours',            '',   'string', 1),
('site_url',              '',   'string', 1),
('facebook_url',          '',   'string', 1),
('instagram_url',         '',   'string', 1),
('whatsapp_number',       '',   'string', 1);

-- Order / Checkout Defaults
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('payment_mode',              'online', 'string',  1),
('minimum_order_value',       '0',      'number',  1),
('order_prefix',              'ORD',    'string',  1),
('require_login_for_orders',  '0',      'boolean', 1),
('max_order_quantity',        '100',    'number',  1),
('min_delivery_days',         '1',      'number',  1),
('max_delivery_days',         '7',      'number',  1);

-- Shipping
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('shipping_flat_rate',        '0',   'number', 1),
('shipping_free_threshold',   '0',   'number', 1);

-- Payment Gateway Toggles (disabled by default; store owner enables + fills credentials in admin)
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('cod_enabled',           '1', 'boolean', 1),
('bank_transfer_enabled', '0', 'boolean', 1),
('razorpay_enabled',      '0', 'boolean', 1),
('phonepe_enabled',       '0', 'boolean', 1),
('cashfree_enabled',      '0', 'boolean', 1),
('googlepay_enabled',     '0', 'boolean', 1),
('applepay_enabled',      '0', 'boolean', 1),
('paypal_enabled',        '0', 'boolean', 1),
('venmo_enabled',         '0', 'boolean', 1),
('klarna_enabled',        '0', 'boolean', 1),
('paylater_enabled',      '0', 'boolean', 1),
('affirm_enabled',        '0', 'boolean', 1);

-- Payment Gateway Credentials (empty placeholders; is_public=0 keeps them out of settings.json)
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('razorpay_key_id',              '', 'string', 0),
('razorpay_key_secret',          '', 'string', 0),
('phonepe_merchant_id',          '', 'string', 0),
('phonepe_salt_key',             '', 'string', 0),
('cashfree_app_id',              '', 'string', 0),
('cashfree_secret_key',          '', 'string', 0),
('googlepay_gateway_merchant_id','', 'string', 0),
('applepay_merchant_id',         '', 'string', 0),
('applepay_private_key',         '', 'string', 0),
('paypal_client_id',             '', 'string', 0),
('paypal_client_secret',         '', 'string', 0),
('klarna_api_username',          '', 'string', 0),
('klarna_api_password',          '', 'string', 0),
('affirm_public_key',            '', 'string', 0),
('affirm_private_key',           '', 'string', 0);

-- WhatsApp Notifications
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('whatsapp_enabled',             '0', 'boolean', 1),
('whatsapp_notify_order_confirm','1', 'boolean', 1),
('whatsapp_notify_shipping',     '1', 'boolean', 1),
('whatsapp_notify_delivery',     '1', 'boolean', 1),
('whatsapp_notify_payment',      '1', 'boolean', 1);

-- SMTP / Email (blanks; store owner configures via admin)
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('from_email',    '', 'string', 0),
('from_name',     '', 'string', 0),
('admin_email',   '', 'string', 0),
('smtp_enabled',  '0', 'boolean', 0),
('smtp_host',     '', 'string', 0),
('smtp_port',     '587', 'string', 0),
('smtp_username', '', 'string', 0),
('smtp_password', '', 'string', 0),
('smtp_encryption','tls', 'string', 0);

-- Theme Feature Flags (read by storefront to control which features are shown)
-- These match the feature_flags objects in src/themes/*/theme.json
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('theme_flags_general',  '{"show_billing_section":true,"estimate_mode":false,"show_combo_packs":true,"cart_button_label":"Add to Cart","order_button_label":"Place Order"}',   'json', 1),
('theme_flags_crackers', '{"show_billing_section":false,"estimate_mode":true,"show_combo_packs":true,"cart_button_label":"Add to Cart","order_button_label":"Confirm Estimate"}', 'json', 1),
('theme_flags_organic',  '{"show_billing_section":true,"estimate_mode":false,"show_combo_packs":false,"cart_button_label":"Add to Cart","order_button_label":"Place Order"}',  'json', 1),
('theme_flags_base',     '{"show_billing_section":false,"estimate_mode":true,"show_combo_packs":true,"cart_button_label":"Add to Cart","order_button_label":"Confirm Estimate"}', 'json', 1);

-- Theme Configurations (default color palettes per theme)
INSERT INTO `#__settings` (setting_key, setting_value, setting_type, is_public) VALUES
('theme_config_crackers', '{"colors":{"primary":"#ea580c","secondary":"#dc2626","header_bg":"#ffffff","footer_bg":"#1c1917","top_bar_bg":"#ea580c","page_bg":"#fff7ed"},"visuals":{"border_radius":"0.25rem","hide_logo_text":false,"hide_top_bar":false,"logo_width":150},"typography":{"font_body":"Inter","font_heading":"Inter","font_size_base":"16px","font_weight_heading":"700"}}', 'json', 1),
('theme_config_organic',  '{"colors":{"primary":"#315e1c","secondary":"#65a30d","header_bg":"#f0fdf4","footer_bg":"#14532d","top_bar_bg":"#166534","page_bg":"#ffffff"},"visuals":{"border_radius":"1.5rem","hide_logo_text":false,"hide_top_bar":false,"logo_width":150},"typography":{"font_body":"Lato","font_heading":"Playfair Display","font_size_base":"16px","font_weight_heading":"700"}}',  'json', 1),
('theme_config_general',  '{"colors":{"primary":"#0B7C7A","secondary":"#f59e0b","header_bg":"#ffffff","footer_bg":"#1e293b","top_bar_bg":"#0B7C7A","page_bg":"#f8fafc"},"visuals":{"border_radius":"0.5rem","hide_logo_text":false,"hide_top_bar":false,"logo_width":150},"typography":{"font_body":"Inter","font_heading":"Inter","font_size_base":"16px","font_weight_heading":"600"}}',   'json', 1),
('theme_config_base',     '{"colors":{"primary":"#0B7C7A","secondary":"#f59e0b","header_bg":"#ffffff","footer_bg":"#1e293b","top_bar_bg":"#0B7C7A","page_bg":"#f8fafc"},"visuals":{"border_radius":"0.25rem","hide_logo_text":false,"hide_top_bar":false,"logo_width":150},"typography":{"font_body":"Inter","font_heading":"Inter","font_size_base":"16px","font_weight_heading":"600"}}',  'json', 1);

-- Default Pages ({{PLACEHOLDER}} tokens replaced by bootstrapStore() with actual store data)
INSERT INTO `#__pages` (title, slug, content, status, sort_order) VALUES
('About Us', 'about-us',
 '<h2>About {{STORE_NAME}}</h2><p>{{STORE_DESCRIPTION}}</p><p>We are dedicated to providing you with the best products and exceptional customer service.</p><h3>Why Choose Us?</h3><ul><li>Quality products at competitive prices</li><li>Fast and reliable delivery</li><li>Dedicated customer support</li><li>Secure and easy checkout</li></ul>',
 'published', 1),

('Contact Us', 'contact-us',
 '<h2>Contact Us</h2><p>We would love to hear from you! Reach out through any of the channels below.</p><p><strong>Phone:</strong> {{STORE_PHONE}}</p><p><strong>Email:</strong> {{STORE_EMAIL}}</p><p><strong>Address:</strong> {{STORE_ADDRESS}}</p><p><strong>Business Hours:</strong> {{STORE_HOURS}}</p>',
 'published', 2),

('Terms & Conditions', 'terms-conditions',
 '<h2>Terms & Conditions</h2><p>By accessing or using {{STORE_NAME}}, you agree to be bound by these Terms and Conditions.</p><h3>1. Products & Pricing</h3><p>All prices are subject to change without notice.</p><h3>2. Orders</h3><p>All orders are subject to product availability. We reserve the right to refuse or cancel any order at our discretion.</p><h3>3. Delivery</h3><p>Delivery times are estimates and may vary. We are not responsible for delays caused by third-party courier services.</p><h3>4. Returns & Refunds</h3><p>Please refer to our Return Policy for details.</p><h3>5. Contact</h3><p>For any questions, contact us at {{STORE_EMAIL}}.</p>',
 'published', 3),

('Privacy Policy', 'privacy-policy',
 '<h2>Privacy Policy</h2><p>At {{STORE_NAME}}, we are committed to protecting your privacy.</p><h3>Information We Collect</h3><p>We collect your name, email address, phone number, and delivery address when you place an order.</p><h3>How We Use Your Information</h3><p>We use your information to process orders, send order confirmations, and provide customer support.</p><h3>Data Security</h3><p>We implement appropriate security measures to protect your personal information.</p><h3>Contact Us</h3><p>Questions about this Privacy Policy? Contact us at {{STORE_EMAIL}}.</p>',
 'published', 4),

('Return Policy', 'return-policy',
 '<h2>Return Policy</h2><p>We want you to be completely satisfied with your purchase.</p><h3>Eligibility</h3><p>Items may be returned within 7 days of delivery if they are defective, damaged, or not as described.</p><h3>Process</h3><p>Contact us at {{STORE_EMAIL}} with your order number and reason for return. We will guide you through the next steps.</p><h3>Refunds</h3><p>Approved refunds will be processed within 5-7 business days to the original payment method.</p>',
 'published', 5);

-- Default Menus
INSERT INTO `#__menus` (name, location, status) VALUES
('Header Navigation', 'header', 'active'),
('Footer Navigation', 'footer', 'active');

-- Header menu items (menu_id=1)
-- '#category-menu' is a special URL: DynamicMenu.vue renders it as the CategoryMegaMenu dropdown
INSERT INTO `#__menu_items` (menu_id, title, url, page_id, sort_order, status) VALUES
(1, 'Home',           '/',              NULL, 1, 'active'),
(1, 'All Categories', '#category-menu', NULL, 2, 'active'),
(1, 'Products',       '/products',      NULL, 3, 'active'),
(1, 'About Us',       NULL,             1,    4, 'active'),
(1, 'Contact',        NULL,             2,    5, 'active');

-- Footer menu items (menu_id=2)
INSERT INTO `#__menu_items` (menu_id, title, url, page_id, sort_order, status) VALUES
(2, 'Terms & Conditions', NULL, 3, 1, 'active'),
(2, 'Privacy Policy',     NULL, 4, 2, 'active'),
(2, 'Return Policy',      NULL, 5, 3, 'active');

-- Default Shipping Carriers
INSERT INTO `#__shipping_carriers` (code, name, is_active, tracking_url_template) VALUES
('self',      'Self Delivery', 1, ''),
('delhivery', 'Delhivery',     0, 'https://www.delhivery.com/track/package/{tracking_number}');

-- Seed themes table so admin theme switcher works on first login
-- Note: id is the theme slug (varchar), is_active 1 = active theme
-- bootstrapStore() will flip is_active to match the chosen theme
INSERT INTO `#__themes` (id, name, version, description, is_active, schema_json) VALUES
('general',  'General Store',   '1.0.0', 'Modern general-purpose e-commerce store',               1, '{}'),
('crackers', 'Crackers Store',  '1.0.0', 'Fireworks and crackers theme with estimate-based ordering', 0, '{}'),
('organic',  'Organic Store',   '1.0.0', 'Fresh, nature-inspired store theme',                    0, '{}'),
('base',     'Base Store',      '1.0.0', 'Minimal base theme',                                    0, '{}');

SET FOREIGN_KEY_CHECKS = 1;