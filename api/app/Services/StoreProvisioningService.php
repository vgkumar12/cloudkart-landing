<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

class StoreProvisioningService {
    private $platformDb;
    private $config;
    private $cpanel;

    public function __construct() {
        $this->platformDb = Database::getPlatformConnection(); // platform_stores, platform_plans, users
        $this->cpanel = new CPanelService();
        // Detect base path: 4 levels up if in root/api/..., 5 levels up if in landing/api/...
        $servicesPath = __DIR__;
        $levels = (strpos($servicesPath, DIRECTORY_SEPARATOR . 'landing' . DIRECTORY_SEPARATOR) !== false) ? 5 : 4;
        $basePath = $servicesPath;
        for ($i = 0; $i < $levels; $i++) {
            $basePath = dirname($basePath);
        }

        // Prefer the explicit constant if defined in config.php (avoids path arithmetic bugs)
        if (defined('CLOUDKART_ROOT')) {
            $basePath = CLOUDKART_ROOT;
        }

        $this->config = [
            'base_path'   => $basePath,
            'dist_path'   => $basePath . DIRECTORY_SEPARATOR . 'dist',
            'stores_path' => $basePath . DIRECTORY_SEPARATOR . 'stores',
            'schema_path' => $basePath . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'schema.sql',
            'data_path'   => $basePath . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'default_data.sql',
            'db_user'     => DB_USER,
            'db_pass'     => DB_PASS,
            'db_host'     => DB_HOST,
            'main_domain' => defined('MAIN_DOMAIN') ? MAIN_DOMAIN : 'localhost',
        ];
    }

    /**
     * Main provisioning method
     */
    public function provisionStore($storeData, $adminData) {
        $subdomain = $storeData['subdomain'];
        $tablePrefix = $storeData['table_prefix'] ?? 'ck_' . str_replace('-', '_', $subdomain) . '_';
        $storePath = $this->config['stores_path'] . DIRECTORY_SEPARATOR . $subdomain;

        try {
            // 1. Create Subdomain (Live CPanel Mode)
            $this->cpanel->createSubdomain($subdomain, $this->config['main_domain'], "/public_html/stores/{$subdomain}");

            // 2. Create directory (Local fallback/File structure)
            if (!is_dir($this->config['stores_path'])) {
                mkdir($this->config['stores_path'], 0755, true);
            }
            
            // Allow proceeding if the directory exists (e.g., from a failed previous attempt)
            // but log it if it's not empty.
            if (!is_dir($storePath)) {
                mkdir($storePath, 0755, true);
            }
            
            // 3. Deploy Files
            $this->deployFiles($storePath);

            // 4. Initialize Database Schema (Single DB with Prefix)
            $this->initializeDatabase($tablePrefix);

            // 5. Update Config (Inject Prefix and Platform DB Credentials)
            $this->updateStoreConfig($storePath, $tablePrefix, $subdomain);

            // 6. Bootstrap Store (Inject user preferences into platform DB with prefix)
            $this->bootstrapStore($tablePrefix, $storeData, $adminData);

            // Calculate URL for response
            $siteUrl = '';
            if (defined('CPANEL_TOKEN') && !empty(CPANEL_TOKEN)) {
                $siteUrl = 'http://' . $subdomain . '.' . $this->config['main_domain'];
            } else {
                $platformUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost/cloudkart/landing';
                $baseUrl = str_replace('/landing', '', $platformUrl);
                $siteUrl = rtrim($baseUrl, '/') . '/stores/' . $subdomain;
            }

            return [
                'success' => true,
                'db_name' => DB_NAME,
                'table_prefix' => $tablePrefix,
                'path' => $storePath,
                'url' => $siteUrl
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    // Simplified: We no longer create separate databases or users.
    // All stores live in the platform database with unique prefixes.

    private function deployFiles($storePath) {
        $source = $this->config['dist_path'];
        if (!is_dir($source)) {
            throw new Exception("Source build directory not found at " . $source);
        }
        $this->copyRecursive($source, $storePath);
    }

    private function copyRecursive($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        foreach ($iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST) as $item) {
            
            if ($item->isDir()) {
                mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }

    private function initializeDatabase($tablePrefix) {
        // Store tables go into the STORE DB (not the platform DB)
        $storeDb = Database::getStoreConnection();
        $this->runSqlFile($storeDb, $this->config['schema_path'], $tablePrefix);
        $this->runSqlFile($storeDb, $this->config['data_path'], $tablePrefix);
    }

    private function runSqlFile($db, $filePath, $prefix = '') {
        if (!file_exists($filePath)) {
            throw new Exception("SQL file not found: " . $filePath);
        }

        $sql = file_get_contents($filePath);

        // Strip UTF-8 BOM if present (added by PowerShell WriteAllText)
        if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
            $sql = substr($sql, 3);
        }

        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/^\s*#.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Inject prefix using the '#__' placeholder
        if (!empty($prefix)) {
            $sql = str_replace('#__', $prefix, $sql);
        }

        // Split by semicolon — but only outside of quoted strings
        $statements = $this->splitSqlStatements($sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $db->exec($statement);
                } catch (Exception $e) {
                    error_log("SQL Execution Failure: " . $e->getMessage());
                    error_log("Failed Statement: " . $statement);
                    throw new Exception("Error executing SQL statement: " . $e->getMessage() . "\nFull Statement logged. Snippet: " . substr($statement, 0, 150));
                }
            }
        }
    }

    private function updateStoreConfig($storePath, $tablePrefix, $subdomain) {
        $configFile = $storePath . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'config.php';
        
        if (!file_exists($configFile)) {
            $rootConfig = $this->config['base_path'] . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'config.php';
            if (file_exists($rootConfig)) {
                if (!is_dir(dirname($configFile))) {
                    mkdir(dirname($configFile), 0755, true);
                }
                copy($rootConfig, $configFile);
            } else {
                throw new Exception("Config file not found in new store and no fallback available: " . $configFile);
            }
        }

        $configContent = file_get_contents($configFile);
        
        // Inject store DB credentials (STORE_DB_NAME — not the platform DB)
        $configContent = preg_replace("/define\('DB_NAME',\s*.*?\);/", "define('DB_NAME', '" . STORE_DB_NAME . "');", $configContent);
        $configContent = preg_replace("/define\('DB_USER',\s*.*?\);/", "define('DB_USER', '" . DB_USER . "');", $configContent);
        $configContent = preg_replace("/define\('DB_PASS',\s*.*?\);/", "define('DB_PASS', '" . DB_PASS . "');", $configContent);
        
        // Inject Table Prefix
        $configContent = preg_replace("/define\('TABLE_PREFIX',\s*.*?\);/", "define('TABLE_PREFIX', '$tablePrefix');", $configContent);

        // Update Site URL
        $siteUrl = '';
        if (defined('CPANEL_TOKEN') && !empty(CPANEL_TOKEN)) {
            $siteUrl = 'http://' . $subdomain . '.' . $this->config['main_domain'];
        } else {
            // Derive from platform SITE_URL (which is typically .../landing)
            $platformUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost/cloudkart/landing';
            $baseUrl = str_replace('/landing', '', $platformUrl);
            $siteUrl = rtrim($baseUrl, '/') . '/stores/' . $subdomain;
        }
        
        $configContent = preg_replace("/define\('SITE_URL',\s*.*?\);/", "define('SITE_URL', '$siteUrl');", $configContent);

        file_put_contents($configFile, $configContent);
    }

    private function bootstrapStore($tablePrefix, $storeData, $adminData) {
        // Bootstrap writes into store tables — use STORE DB
        $storeDb = Database::getStoreConnection();
        $subdomain = $storeData['subdomain'];
        $theme = $storeData['theme'] ?? 'general';
        $storePath = $this->config['stores_path'] . DIRECTORY_SEPARATOR . $subdomain;

        // 1. Core Settings (Site Name, Contact, Theme)
        $phone   = $adminData['phone'] ?? '';
        $address = $storeData['business_address'] ?? '';
        $hours   = $storeData['business_hours']   ?? '';
        $settings = [
            'site_name'        => $storeData['store_name'],
            'site_description' => $storeData['tagline'] ?? '',
            'contact_email'    => $adminData['email'],
            'contact_phone'    => $phone,
            'site_phone'       => $phone,
            'site_address'     => $address,
            'site_hours'       => $hours,
            'company_name'     => $storeData['store_name'],
            'from_email'       => $adminData['email'],
            'admin_email'      => $adminData['email'],
            'facebook_url'     => $storeData['facebook_url']    ?? '',
            'instagram_url'    => $storeData['instagram_url']   ?? '',
            'whatsapp_number'  => $storeData['whatsapp_number'] ?? '',
            'active_theme'     => $theme,
            // payment_mode: crackers/base use estimate; general/organic use online
            'payment_mode'     => in_array($theme, ['crackers', 'base']) ? 'estimate' : 'online',
        ];

        // 2. Handle Logo Copying — use original filename extension, not the tmp path
        if (!empty($storeData['logo_tmp']) && file_exists($storeData['logo_tmp'])) {
            $imagesDir = $storePath . DIRECTORY_SEPARATOR . 'images';
            if (!is_dir($imagesDir)) {
                mkdir($imagesDir, 0755, true);
            }
            // Prefer extension from original filename; fall back to 'png'
            $originalName = $storeData['logo_name'] ?? '';
            $ext = ($originalName !== '') ? (pathinfo($originalName, PATHINFO_EXTENSION) ?: 'png') : 'png';
            $targetLogo = 'logo.' . strtolower($ext);
            if (copy($storeData['logo_tmp'], $imagesDir . DIRECTORY_SEPARATOR . $targetLogo)) {
                $settings['site_logo'] = 'images/' . $targetLogo;
            }
        }

        // 3. Upsert settings (INSERT ... ON DUPLICATE KEY UPDATE handles both new and pre-seeded rows)
        foreach ($settings as $key => $val) {
            $stmt = $storeDb->prepare(
                "INSERT INTO `{$tablePrefix}settings` (setting_key, setting_value, setting_type, is_public)
                 VALUES (?, ?, 'string', 1)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([$key, $val]);
        }

        // 4. Activate the selected theme in the themes table
        //    (default_data.sql seeds all 4 themes; flip is_active to match user's choice)
        $storeDb->prepare("UPDATE `{$tablePrefix}themes` SET is_active = 0")->execute();
        $stmt = $storeDb->prepare("UPDATE `{$tablePrefix}themes` SET is_active = 1 WHERE id = ?");
        $stmt->execute([$theme]);

        // 5. Create Admin User
        $passwordHash = password_hash($adminData['password'], PASSWORD_DEFAULT);
        $stmt = $storeDb->prepare(
            "INSERT INTO `{$tablePrefix}users` (name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)
             ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'admin', is_active = 1"
        );
        $stmt->execute([$adminData['name'], $adminData['email'], $passwordHash]);

        // 6. Personalize default page content (replace {{PLACEHOLDER}} tokens seeded by default_data.sql)
        $this->personalizePages($storeDb, $tablePrefix, $storeData, $adminData);

        // 7. Seed themes.schema_json, theme_settings defaults, and theme_config blobs.
        //    default_data.sql seeds themes with schema_json='{}'; this fills in real schemas,
        //    populates theme_settings AND writes theme_config_{id} blobs for applyThemeStyles().
        $this->seedThemeSchemas($storeDb, $tablePrefix);

        // 8. Apply full color scheme (brand color + header style) to the active theme.
        if (!empty($storeData['brand_color'])) {
            $this->applyBrandColor($storeDb, $tablePrefix, $theme, $storeData['brand_color'], $storeData['header_style'] ?? 'light');
        }

        // 9. Generate a fresh settings.json (now includes theme_config with brand color baked in).
        $this->generateStoreSettingsJson($storeDb, $tablePrefix, $storePath);
    }

    private function seedThemeSchemas(\PDO $db, $tablePrefix) {
        $themesDir = $this->config['base_path'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'themes';
        if (!is_dir($themesDir)) return;

        $upsertSchema   = $db->prepare("UPDATE `{$tablePrefix}themes` SET schema_json = ? WHERE id = ?");
        $upsertSetting  = $db->prepare(
            "INSERT INTO `{$tablePrefix}theme_settings` (theme_id, setting_key, setting_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $upsertConfig = $db->prepare(
            "INSERT INTO `{$tablePrefix}settings` (setting_key, setting_value, setting_type, is_public)
             VALUES (?, ?, 'json', 1)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        foreach (scandir($themesDir) as $themeId) {
            if ($themeId === '.' || $themeId === '..') continue;

            $jsonPath = $themesDir . DIRECTORY_SEPARATOR . $themeId . DIRECTORY_SEPARATOR . 'theme.json';
            if (!file_exists($jsonPath)) continue;

            $schema = json_decode(file_get_contents($jsonPath), true);
            if (!is_array($schema)) continue;

            // 1. Write full schema into themes table so admin editor renders fields
            $upsertSchema->execute([json_encode($schema), $themeId]);

            // 2. Populate theme_settings from each field's default value
            //    and build the theme_config blob that applyThemeStyles() reads
            $config = [];
            foreach ($schema['settings'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $field) {
                    if (!empty($field['key']) && array_key_exists('default', $field)) {
                        $upsertSetting->execute([$themeId, $field['key'], (string)$field['default']]);
                        $config[$field['key']] = $field['default'];
                    }
                }
            }

            // 3. Write theme_config_{id} so applyThemeStyles() sets CSS vars on first load
            $upsertConfig->execute(['theme_config_' . $themeId, json_encode($config)]);
        }
    }

    private function applyBrandColor($db, $tablePrefix, $themeId, $brandColor, $headerStyle = 'light') {
        if (substr($brandColor, 0, 1) !== '#') $brandColor = '#' . $brandColor;
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/i', $brandColor)) return;

        // Derive the full color scheme from brand color + header style
        $scheme = $this->deriveColorScheme($brandColor, $headerStyle, $themeId);

        $upsertSetting = $db->prepare(
            "INSERT INTO `{$tablePrefix}theme_settings` (theme_id, setting_key, setting_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        // 1. Write all derived colors into theme_settings
        foreach ($scheme as $key => $value) {
            $upsertSetting->execute([$themeId, $key, $value]);
        }

        // 2. Merge into the theme_config_{id} blob (already has typography/layout from seedThemeSchemas)
        $configKey = 'theme_config_' . $themeId;
        $row = $db->query(
            "SELECT setting_value FROM `{$tablePrefix}settings` WHERE setting_key = " . $db->quote($configKey)
        )->fetch(\PDO::FETCH_ASSOC);

        $config = ($row) ? (json_decode($row['setting_value'], true) ?: []) : [];
        foreach ($scheme as $key => $value) {
            $config[$key] = $value;
        }

        $db->prepare(
            "INSERT INTO `{$tablePrefix}settings` (setting_key, setting_value, setting_type, is_public)
             VALUES (?, ?, 'json', 1)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([$configKey, json_encode($config)]);
    }

    private function deriveColorScheme(string $brandColor, string $headerStyle, string $themeId): array {
        $darker  = $this->shadeColor($brandColor, -25);
        $darkest = $this->shadeColor($brandColor, -40);

        // In 'light' mode, some themes use the brand color as their footer/top-bar bg
        $lightFooters  = ['organic' => $brandColor,  'general' => '#1f2937', 'crackers' => '#1a1a1a', 'base' => '#1f2937'];
        $lightTopBars  = ['organic' => $darker,       'general' => '#111827', 'crackers' => '#000000', 'base' => '#111827'];

        switch ($headerStyle) {
            case 'dark':
                return [
                    'primary'    => $brandColor,
                    'header_bg'  => '#1a1a1a',
                    'top_bar_bg' => '#000000',
                    'footer_bg'  => '#111827',
                ];
            case 'colored':
                return [
                    'primary'    => $brandColor,
                    'header_bg'  => $brandColor,
                    'top_bar_bg' => $darkest,
                    'footer_bg'  => $darker,
                ];
            default: // light
                return [
                    'primary'    => $brandColor,
                    'header_bg'  => '#ffffff',
                    'top_bar_bg' => $lightTopBars[$themeId]  ?? '#111827',
                    'footer_bg'  => $lightFooters[$themeId]  ?? '#1f2937',
                ];
        }
    }

    private function shadeColor(string $hex, int $percent): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $clamp = fn($v) => max(0, min(255, (int)round($v + $v * $percent / 100)));
        return '#' . implode('', array_map(fn($v) => str_pad(dechex($clamp($v)), 2, '0', STR_PAD_LEFT), [$r, $g, $b]));
    }

    private function personalizePages($db, $tablePrefix, $storeData, $adminData) {
        $replacements = [
            '{{STORE_NAME}}'        => $storeData['store_name'],
            '{{STORE_DESCRIPTION}}' => $storeData['tagline'] ?? '',
            '{{STORE_PHONE}}'       => $adminData['phone'] ?? '',
            '{{STORE_EMAIL}}'       => $adminData['email'],
            '{{STORE_ADDRESS}}'     => $storeData['business_address'] ?? '',
            '{{STORE_HOURS}}'       => $storeData['business_hours']   ?? 'Mon–Sat: 9AM – 6PM',
        ];

        // One UPDATE per token keeps it simple and avoids building a giant dynamic query
        foreach ($replacements as $token => $value) {
            $safeValue = $db->quote($value);
            $safeToken = $db->quote($token);
            $db->exec("UPDATE `{$tablePrefix}pages` SET content = REPLACE(content, {$safeToken}, {$safeValue}) WHERE content LIKE CONCAT('%', {$safeToken}, '%')");
        }

        // Also update meta titles for the five default pages
        $db->prepare("UPDATE `{$tablePrefix}pages` SET meta_title = CONCAT(title, ' | ', ?) WHERE slug IN ('about-us','contact-us','terms-conditions','privacy-policy','return-policy')")
           ->execute([$storeData['store_name']]);
    }

    private function generateStoreSettingsJson(\PDO $db, $tablePrefix, $storePath) {
        try {
            $rows = $db->query("SELECT setting_key, setting_value FROM `{$tablePrefix}settings` WHERE is_public = 1")->fetchAll(\PDO::FETCH_ASSOC);
            $settings = [];
            foreach ($rows as $r) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }
            // Strip anything sensitive (same patterns as settings_inline.php)
            $sensitive = ['password', 'secret', 'private_key', 'client_secret', 'salt_key', 'api_key'];
            foreach (array_keys($settings) as $k) {
                foreach ($sensitive as $p) {
                    if (stripos($k, $p) !== false) { unset($settings[$k]); break; }
                }
            }
            $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            file_put_contents($storePath . DIRECTORY_SEPARATOR . 'settings.json', $json);
        } catch (\Exception $e) {
            // Non-fatal — settings_inline.php reads from DB dynamically anyway
            error_log("generateStoreSettingsJson failed: " . $e->getMessage());
        }
    }

    /**
     * Split SQL into individual statements, respecting single-quoted strings.
     * Prevents splitting on semicolons inside string values (e.g. HTML entities like &amp;).
     */
    private function splitSqlStatements(string $sql): array {
        $statements = [];
        $current    = '';
        $inString   = false;
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $current .= $char;
                if ($char === "'" ) {
                    // escaped quote inside string: ''
                    if (isset($sql[$i + 1]) && $sql[$i + 1] === "'") {
                        $current .= $sql[++$i];
                    } else {
                        $inString = false;
                    }
                }
            } else {
                if ($char === "'") {
                    $inString = true;
                    $current .= $char;
                } elseif ($char === ';') {
                    $stmt = trim($current);
                    if ($stmt !== '') {
                        $statements[] = $stmt;
                    }
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
        }

        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    /**
     * Rollback provisioning artifacts on failure
     */
    public function rollback($subdomain, $tablePrefix = null) {
        $storePath = $this->config['stores_path'] . DIRECTORY_SEPARATOR . $subdomain;
        
        // 1. Remove files
        if (is_dir($storePath)) {
            $this->deleteRecursive($storePath);
        }

        // 2. Clean up partially created tables
        if ($tablePrefix) {
            try {
                $stmt = $this->platformDb->query("SHOW TABLES LIKE '{$tablePrefix}%'");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($tables)) {
                    $this->platformDb->exec("SET FOREIGN_KEY_CHECKS = 0");
                    foreach ($tables as $table) {
                        $this->platformDb->exec("DROP TABLE IF EXISTS `{$table}`");
                    }
                    $this->platformDb->exec("SET FOREIGN_KEY_CHECKS = 1");
                }
            } catch (Exception $e) {
                // Log and ignore DB cleanup errors during rollback to prioritize file cleanup
                error_log("Rollback DB error: " . $e->getMessage());
            }
        }
    }

    private function deleteRecursive($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteRecursive("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
