<?php

/**
 * Platform API Routes
 */

$router->get('/plans', 'PlanController@index');

// Platform migrations — runs DB + file updates across all provisioned stores
// Protected by MIGRATION_SECRET in config.php
$router->post('/migrate', 'MigrationController@run');

// Super Admin — Store Controller (requires HMAC-signed token with role=admin)
$router->get('/admin/stores',                    'StoreAdminController@index');
$router->get('/admin/stores/{id}',               'StoreAdminController@show');
$router->get('/admin/stores/{id}/pending',       'StoreAdminController@pending');
$router->post('/admin/stores/{id}/update',       'StoreAdminController@update');
$router->post('/admin/stores/bulk-update',       'StoreAdminController@bulkUpdate');

$router->post('/register', 'RegistrationController@register');
$router->post('/domain/check', 'DomainController@checkAvailability');

$router->post('/checkout', 'SubscriptionController@checkout');
$router->post('/checkout/verify', 'SubscriptionController@verify');

$router->get('/licence/validate', 'LicenceController@validate');

// Billing Routes
$router->get('/billing/info', 'BillingController@info');
$router->post('/billing/initiate', 'BillingController@initiatePayment');
$router->post('/billing/webhook', 'BillingController@webhook');

// Authentication
$router->post('/login', 'AuthController@login');

// Dashboard & Control
$router->get('/dashboard', 'DashboardController@getDashboardData');
$router->get('/dashboard/settings', 'DashboardController@getStoreSettings');
$router->post('/dashboard/settings', 'DashboardController@updateStoreSettings');
