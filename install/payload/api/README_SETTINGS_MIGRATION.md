# Settings Migration Guide

## Overview
This guide explains how to migrate settings from `config.php` to the database using the settings system.

## Step 1: Run Migration Script

Run the migration script to copy all settings from `config.php` to the database:

```bash
cd upgrade/frontend/api
php migrate-settings.php
```

This script will:
- Read all settings from `config.php`
- Create or update settings in the `settings` database table
- Preserve existing values if settings already exist
- Show a summary of created/updated settings

## Step 2: Verify Settings

1. Login to the admin panel
2. Navigate to Settings page
3. Verify all settings are populated correctly
4. Make any adjustments as needed

## Step 3: Update Code to Use Database Settings

### Option 1: Use Setting Model (Backend/PHP)

```php
use App\Models\Setting;

// Get a setting
$siteName = Setting::get('site_name', 'Default Name');

// Set a setting
Setting::set('site_name', 'New Name', 'string', 'Site name');
```

### Option 2: Use Settings Composable (Frontend/Vue)

```vue
<script setup>
import { useSettings } from '@/composables/useSettings'

const { settings } = useSettings()
</script>

<template>
  <div>{{ settings.site_name }}</div>
</template>
```

### Option 3: Use Settings Utility (Frontend/JS)

```javascript
import { getSetting, fetchSettings } from '@/utils/settings'

// Get all settings
const allSettings = await fetchSettings()

// Get a specific setting
const email = await getSetting('site_email', 'default@example.com')
```

## Settings Categories

The migration script migrates the following categories:

1. **Site Information** - Name, URL, logo, contact info
2. **Email Configuration** - SMTP settings, from email/name
3. **Google OAuth** - Client ID, secret, redirect URI
4. **Order Settings** - Prefix, limits, delivery days
5. **Payment Settings** - Currency, UPI details, QR codes
6. **File Upload** - Upload path, size limits, extensions
7. **Logging** - Log levels, paths, size limits
8. **Cache** - Cache settings
9. **Security** - SSL, security headers
10. **Authentication** - Email verification settings
11. **Environment** - Development/production mode

## Important Notes

⚠️ **Sensitive Data**: 
- SMTP passwords and Google client secrets are migrated
- Make sure your database is secured
- Consider encrypting sensitive settings in the future

⚠️ **Admin Credentials**:
- `ADMIN_USERNAME` and `ADMIN_PASSWORD` are NOT migrated to settings (for security)
- These remain in `config.php` or user management system

⚠️ **Backward Compatibility**:
- The old `config.php` file can remain for reference
- New code should use database settings
- Old code can continue using `config.php` constants during transition

## API Endpoints

### Get All Settings (Public)
```
GET /api/settings
```

### Get All Settings (Admin)
```
GET /api/admin/settings
```

### Update Settings (Admin)
```
PUT /api/admin/settings
Content-Type: application/json

{
  "site_name": "New Name",
  "site_email": "new@example.com",
  ...
}
```

### Update Single Setting (Admin)
```
PUT /api/admin/settings/{key}
Content-Type: application/json

{
  "value": "New Value",
  "type": "string",
  "description": "Setting description"
}
```

## Troubleshooting

### Settings Not Showing
- Make sure migration script ran successfully
- Check database connection
- Verify `settings` table exists

### Settings Not Saving
- Check admin authentication
- Verify API endpoint is accessible
- Check browser console for errors

### Old Values Still Showing
- Clear browser cache
- Clear localStorage: `localStorage.removeItem('app_settings')`
- Check if cache is enabled and clear it

## Next Steps

1. ✅ Run migration script
2. ✅ Verify settings in admin panel
3. ⬜ Update frontend components to use `useSettings()` composable
4. ⬜ Update backend code to use `Setting::get()` instead of constants
5. ⬜ (Optional) Remove config.php constants after full migration

