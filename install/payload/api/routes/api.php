<?php

/**
 * API Routes
 * Define all API endpoints here
 * 
 * The $router variable is provided by index.php
 * 
 * Middleware options: ['cors', 'ratelimit', 'auth', 'admin']
 */

// Test route (no auth required)
$router->get('/test', function() {
    $response = new \App\Core\Response();
    $response->success([
        'message' => 'MVC Application is working!',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION
    ]);
}, ['cors', 'ratelimit']);

// Health check (no auth required)
$router->get('/health', function() {
    try {
        $db = \App\Core\Database::getConnection();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected: ' . $e->getMessage();
    }
    
    $response = new \App\Core\Response();
    $response->success([
        'status' => 'ok',
        'database' => $dbStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}, ['cors', 'ratelimit']);

// Token endpoint (public - generates JWT tokens, NO origin validation needed for token generation)
$router->post('/api/token', 'Api/AuthController@generateToken', ['cors', 'ratelimit']);
$router->post('/api/token/refresh', 'Api/AuthController@refreshToken', ['cors', 'ratelimit']);

// OTP Authentication
$router->post('/api/auth/send-otp', 'Api/AuthController@sendOtp', ['cors', 'ratelimit']);
$router->post('/api/auth/verify-otp', 'Api/AuthController@verifyOtp', ['cors', 'ratelimit']);

// Product routes (public)
$router->get('/api/products', 'Api/ProductController@index', ['cors', 'ratelimit']);
$router->get('/api/products/featured', 'Api/ProductController@featured', ['cors', 'ratelimit']);
$router->get('/api/products/search', 'Api/ProductController@search', ['cors', 'ratelimit']);
$router->get('/api/products/slug/{slug}', 'Api/ProductController@showBySlug', ['cors', 'ratelimit']);
$router->get('/api/products/{id}', 'Api/ProductController@show', ['cors', 'ratelimit']);
$router->get('/api/products/{id}/variants', 'Api/ProductController@getVariants', ['cors', 'ratelimit']);

// Category routes (public)
$router->get('/api/categories', 'Api/CategoryController@index', ['cors', 'ratelimit']);
$router->get('/api/categories/slug/{slug}', 'Api/CategoryController@showBySlug', ['cors', 'ratelimit']);
$router->get('/api/categories/{id}', 'Api/CategoryController@show', ['cors', 'ratelimit']);

// Cart routes (public - uses session)
$router->get('/api/cart', 'Api/CartController@index', ['cors', 'ratelimit']);
$router->post('/api/cart', 'Api/CartController@store', ['cors', 'ratelimit']);
$router->put('/api/cart/{id}', 'Api/CartController@update', ['cors', 'ratelimit']);
$router->delete('/api/cart/{id}', 'Api/CartController@destroy', ['cors', 'ratelimit']);
$router->delete('/api/cart', 'Api/CartController@clear', ['cors', 'ratelimit']);

// Customer routes (public for creation, auth for viewing)
$router->get('/api/customers/{id}', 'Api/CustomerController@show', ['cors', 'ratelimit']);
$router->post('/api/customers', 'Api/CustomerController@store', ['cors', 'ratelimit']);
$router->put('/api/customers/{id}', 'Api/CustomerController@update', ['cors', 'ratelimit']);

// Order routes (auth required for viewing, public for creation)
$router->get('/api/orders', 'Api/OrderController@index', ['cors', 'ratelimit', 'auth']);
$router->get('/api/orders/{id}', 'Api/OrderController@show', ['cors', 'ratelimit', 'auth']);
$router->post('/api/orders', 'Api/OrderController@store', ['cors', 'ratelimit']);
$router->post('/api/orders/checkout', 'Api/OrderController@checkout', ['cors', 'ratelimit', 'auth']);
$router->put('/api/orders/{id}/status', 'Api/OrderController@updateStatus', ['cors', 'ratelimit']);

// Coupon routes (public for validation)
$router->post('/api/coupons/validate', 'Api/CouponController@validate', ['cors', 'ratelimit']);

// Combo Pack routes (public)
$router->get('/api/combo-packs', 'Api/ComboPackController@index', ['cors', 'ratelimit']);
$router->get('/api/combo-packs/key/{packKey}', 'Api/ComboPackController@showByPackKey', ['cors', 'ratelimit']);
$router->get('/api/combo-packs/{id}', 'Api/ComboPackController@show', ['cors', 'ratelimit']);

// Scheme routes (public)
$router->get('/api/schemes', 'Api/SchemeController@index', ['cors', 'ratelimit']);
$router->get('/api/schemes/{id}', 'Api/SchemeController@show', ['cors', 'ratelimit']);
$router->get('/api/schemes/{id}/subscriptions', 'Api/SchemeController@subscriptions', ['cors', 'ratelimit']);

// Scheme Subscription routes
$router->get('/api/scheme-subscriptions', 'Api/SchemeSubscriptionController@index', ['cors', 'ratelimit']);
$router->post('/api/scheme-subscriptions', 'Api/SchemeSubscriptionController@store', ['cors', 'ratelimit', 'auth']);
$router->get('/api/scheme-subscriptions/{id}', 'Api/SchemeSubscriptionController@show', ['cors', 'ratelimit']);
$router->get('/api/scheme-subscriptions/{id}/pending-payments', 'Api/SchemeSubscriptionController@pendingPayments', ['cors', 'ratelimit']);
$router->get('/api/scheme-subscriptions/my-fund-schemes', 'Api/SchemeSubscriptionController@myFundSchemes', ['cors', 'ratelimit', 'auth']);
$router->post('/api/scheme-subscriptions/payments/{id}/upload-proof', 'Api/SchemeSubscriptionController@uploadPaymentProof', ['cors', 'ratelimit', 'auth']);

// Slide routes (public)
$router->get('/api/slides', 'Api/SlideController@index', ['cors', 'ratelimit']);
$router->get('/api/slides/{id}', 'Api/SlideController@show', ['cors', 'ratelimit']);

// Setting routes
$router->get('/api/settings', 'Api/SettingController@index', ['cors', 'ratelimit']);
$router->get('/api/settings/{key}', 'Api/SettingController@show', ['cors', 'ratelimit']);
$router->put('/api/settings/{key}', 'Api/SettingController@update', ['cors', 'ratelimit']);

// Payment routes (public for methods, auth for initiation)
$router->get('/api/payment/methods', 'PaymentController@getPaymentMethods', ['cors', 'ratelimit']);
$router->post('/api/payment/initiate', 'PaymentController@initiatePayment', ['cors', 'ratelimit', 'auth']);
$router->post('/api/payment/razorpay/verify', 'PaymentController@verifyRazorpayPayment', ['cors', 'ratelimit']);
$router->post('/api/payment/phonepe/callback', 'PaymentController@phonePeCallback', ['cors', 'ratelimit']);
$router->post('/api/payment/cashfree/webhook', 'PaymentController@cashfreeWebhook', ['cors', 'ratelimit']);

// Home routes (public)
$router->get('/api/home', 'Api/HomeController@index', ['cors', 'ratelimit']);

// Contact routes (public)
$router->post('/api/contact', 'Api/ContactController@submit', ['cors', 'ratelimit']);

// Customer profile routes (auth required)
$router->get('/api/customer/profile', 'Api/CustomerController@profile', ['cors', 'ratelimit', 'auth']);
$router->put('/api/customer/profile', 'Api/CustomerController@updateProfile', ['cors', 'ratelimit', 'auth']);
$router->get('/api/customer/dashboard', 'Api/CustomerController@dashboard', ['cors', 'ratelimit', 'auth']);

// Admin routes (require admin auth)
// Dashboard
$router->get('/api/admin/dashboard', 'Admin/AdminController@dashboard', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/dashboard/stats', 'Admin/DashboardController@stats', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Products (uses existing Product model)
$router->get('/api/admin/products', 'Admin/ProductAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/products/{id}', 'Admin/ProductAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/products', 'Admin/ProductAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/products/{id}', 'Admin/ProductAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/products/{id}', 'Admin/ProductAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);

// Product Variants
$router->get('/api/admin/products/{id}/variants', 'Admin/ProductVariantAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/products/{id}/variants/{variantId}', 'Admin/ProductVariantAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/products/{id}/variants/{variantId}', 'Admin/ProductVariantAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/products/{id}/variants/generate', 'Admin/ProductVariantAdminController@generate', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/products/{id}/variants/{variantId}', 'Admin/ProductVariantAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Brands
$router->get('/api/admin/brands', 'Admin/BrandAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/brands/{id}', 'Admin/BrandAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/brands', 'Admin/BrandAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/brands/{id}', 'Admin/BrandAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/brands/{id}', 'Admin/BrandAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Attributes
$router->get('/api/admin/attributes', 'Admin/AttributeAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/attributes/{id}', 'Admin/AttributeAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/attributes', 'Admin/AttributeAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/attributes/{id}', 'Admin/AttributeAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/attributes/{id}', 'Admin/AttributeAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/attributes/{id}/values', 'Admin/AttributeAdminController@addValue', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/attributes/{id}/values/{valueId}', 'Admin/AttributeAdminController@updateValue', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/attributes/{id}/values/{valueId}', 'Admin/AttributeAdminController@deleteValue', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Categories (uses existing Category model)
$router->get('/api/admin/categories', 'Admin/CategoryAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/categories', 'Admin/CategoryAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/categories/{id}', 'Admin/CategoryAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/categories/{id}', 'Admin/CategoryAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Combo Packs (uses existing ComboPack model)
$router->get('/api/admin/combo-packs', 'Admin/ComboPackAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/combo-packs/{id}', 'Admin/ComboPackAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/combo-packs', 'Admin/ComboPackAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/combo-packs/{id}', 'Admin/ComboPackAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/combo-packs/{id}', 'Admin/ComboPackAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/combo-packs/{id}/items', 'Admin/ComboPackAdminController@getItems', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/combo-packs/{id}/items', 'Admin/ComboPackAdminController@addItem', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/combo-packs/{id}/items/{itemId}', 'Admin/ComboPackAdminController@updateItem', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/combo-packs/{id}/items/{itemId}', 'Admin/ComboPackAdminController@deleteItem', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Customers (uses existing Customer model)
$router->get('/api/admin/customers', 'Admin/CustomerAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/customers/search', 'Admin/CustomerAdminController@search', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/customers/stats', 'Admin/CustomerAdminController@stats', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/customers/{id}', 'Admin/CustomerAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/customers/{id}', 'Admin/CustomerAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Orders (uses existing Order model)
$router->get('/api/admin/orders', 'Admin/OrderAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/orders/search-items', 'Admin/OrderAdminController@searchItems', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/orders', 'Admin/OrderAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/orders/{id}', 'Admin/OrderAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/orders/{id}', 'Admin/OrderAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/orders/{id}/status', 'Admin/OrderAdminController@updateStatus', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/orders/{id}/items', 'Admin/OrderAdminController@updateOrderItems', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/orders/{id}/recalculate', 'Admin/OrderAdminController@recalculateOrder', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/orders/{id}/logs', 'Admin/OrderAdminController@getLogs', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Schemes (uses existing Scheme model)
$router->get('/api/admin/schemes', 'Admin/SchemeAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/schemes/{id}', 'Admin/SchemeAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/schemes', 'Admin/SchemeAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/schemes/{id}', 'Admin/SchemeAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/schemes/{id}/toggle', 'Admin/SchemeAdminController@toggle', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/schemes/{id}', 'Admin/SchemeAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Slides (uses existing Slide model)
$router->get('/api/admin/slides', 'Admin/SlideAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/slides/{id}', 'Admin/SlideAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/slides', 'Admin/SlideAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/slides/{id}', 'Admin/SlideAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/slides/{id}', 'Admin/SlideAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Settings (uses existing Setting model)
$router->get('/api/admin/settings', 'Admin/SettingAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/settings/bulk-update', 'Admin/SettingAdminController@bulkUpdate', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/settings', 'Admin/SettingAdminController@updateAll', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/test-whatsapp', 'Admin/SettingAdminController@testWhatsApp', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/settings/{key}', 'Admin/SettingAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Reports (uses existing models)
$router->get('/api/admin/reports/sales', 'Admin/ReportController@sales', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/reports/products', 'Admin/ReportController@products', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/reports/customers', 'Admin/ReportController@customers', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/reports/sales-by-pack', 'Admin/ReportController@salesByPack', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/reports/recent-activity', 'Admin/ReportController@recentActivity', ['cors', 'ratelimit', 'auth', 'admin']);

// Admin Coupons
$router->get('/api/admin/coupons', 'Admin/CouponAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/coupons/{id}', 'Admin/CouponAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/coupons', 'Admin/CouponAdminController@store', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/coupons/{id}', 'Admin/CouponAdminController@update', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/coupons/{id}', 'Admin/CouponAdminController@destroy', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/coupons/{id}/stats', 'Admin/CouponAdminController@stats', ['cors', 'ratelimit', 'auth', 'admin']);

// POS routes (require admin auth)
$router->get('/api/admin/pos/data', 'Admin/PosController@getData', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/pos/search-customer', 'Admin/PosController@searchCustomer', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/pos/process-sale', 'Admin/PosController@processSale', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/pos/pending-orders', 'Admin/PosController@getPendingOrders', ['cors', 'ratelimit', 'auth', 'admin']);

// Wholesale POS routes (require admin auth)
$router->get('/api/admin/pos-wholesale/data', 'Admin/PosWholesaleController@getData', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/pos-wholesale/process-sale', 'Admin/PosWholesaleController@processSale', ['cors', 'ratelimit', 'auth', 'admin']);

// Fund Scheme Admin routes (require admin auth)
$router->get('/api/admin/fund-schemes/overview', 'Admin/FundSchemeAdminController@overview', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/fund-schemes/schemes', 'Admin/FundSchemeAdminController@schemes', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/fund-schemes/schemes', 'Admin/FundSchemeAdminController@storeScheme', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/fund-schemes/schemes/{id}', 'Admin/FundSchemeAdminController@updateScheme', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/fund-schemes/schemes/{id}/toggle', 'Admin/FundSchemeAdminController@toggleScheme', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/fund-schemes/schemes/{id}', 'Admin/FundSchemeAdminController@deleteScheme', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/fund-schemes/subscriptions', 'Admin/FundSchemeAdminController@subscriptions', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/fund-schemes/payments', 'Admin/FundSchemeAdminController@payments', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/fund-schemes/payments/mark-paid', 'Admin/FundSchemeAdminController@markPaymentPaid', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/fund-schemes/payments/request-reupload', 'Admin/FundSchemeAdminController@requestPaymentReupload', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/fund-schemes/customers', 'Admin/FundSchemeAdminController@customers', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/fund-schemes/settings', 'Admin/FundSchemeAdminController@getSettings', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/fund-schemes/settings', 'Admin/FundSchemeAdminController@updateSettings', ['cors', 'ratelimit', 'auth', 'admin']);

// Scheme Payment Admin routes (dedicated payment management)
$router->get('/api/admin/scheme-payments', 'Admin/SchemePaymentAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/scheme-payments/stats', 'Admin/SchemePaymentAdminController@stats', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/scheme-payments/recent-due', 'Admin/SchemePaymentAdminController@recentDue', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/scheme-payments/{id}', 'Admin/SchemePaymentAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/scheme-payments/{id}/mark-paid', 'Admin/SchemePaymentAdminController@markPaid', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/scheme-payments/{id}/request-reupload', 'Admin/SchemePaymentAdminController@requestReupload', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/scheme-payments/{id}/status', 'Admin/SchemePaymentAdminController@updateStatus', ['cors', 'ratelimit', 'auth', 'admin']);
$router->put('/api/admin/scheme-payments/{id}/notes', 'Admin/SchemePaymentAdminController@updateNotes', ['cors', 'ratelimit', 'auth', 'admin']);

// Cart Session Admin routes (require admin auth)
$router->get('/api/admin/cart-sessions', 'Admin/CartSessionAdminController@index', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/cart-sessions/{sessionId}', 'Admin/CartSessionAdminController@show', ['cors', 'ratelimit', 'auth', 'admin']);
$router->delete('/api/admin/cart-sessions/{sessionId}', 'Admin/CartSessionAdminController@clear', ['cors', 'ratelimit', 'auth', 'admin']);
$router->post('/api/admin/cart-sessions/convert-to-order', 'Admin/CartSessionAdminController@convertToOrder', ['cors', 'ratelimit', 'auth', 'admin']);

// Bulk Import Admin routes (require admin auth)
$router->post('/api/admin/bulk-import', 'Admin/BulkImportAdminController@import', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/bulk-import/history', 'Admin/BulkImportAdminController@history', ['cors', 'ratelimit', 'auth', 'admin']);

// Print Admin routes (require admin auth)
$router->get('/api/admin/print/order', 'Admin/PrintAdminController@order', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/print/pos-receipt', 'Admin/PrintAdminController@posReceipt', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/print/combo-pack', 'Admin/PrintAdminController@comboPack', ['cors', 'ratelimit', 'auth', 'admin']);
$router->get('/api/admin/print/product-price-list', 'Admin/PrintAdminController@productPriceList', ['cors', 'ratelimit', 'auth', 'admin']);

// Token endpoints are already defined above (line 40-41) - removed duplicate

// Auth routes (public) - Frontend
$router->post('/api/auth/login', 'Api/AuthController@login', ['cors', 'ratelimit', 'origin']);
$router->post('/api/auth/logout', 'Api/AuthController@logout', ['cors', 'ratelimit', 'origin']);
$router->post('/api/auth/verify-otp', 'Api/AuthController@verifyOtp', ['cors', 'ratelimit', 'origin']);
$router->post('/api/auth/google-callback', 'Api/AuthController@googleCallback', ['cors', 'ratelimit', 'origin']);
$router->get('/api/auth/me', 'Api/AuthController@me', ['cors', 'ratelimit', 'origin', 'auth']);

// Admin Auth routes - Separate endpoints to ensure correct session context
$router->post('/api/admin/auth/login', 'Api/AuthController@login', ['cors', 'ratelimit', 'origin']);
$router->post('/api/admin/auth/logout', 'Api/AuthController@logout', ['cors', 'ratelimit', 'origin']);
$router->get('/api/admin/auth/me', 'Api/AuthController@me', ['cors', 'ratelimit', 'origin', 'auth']);

