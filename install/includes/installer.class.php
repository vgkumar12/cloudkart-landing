<?php
/**
 * E-Commerce Platform Installer
 * Core Installer Class
 */

class Installer {
    private $db;
    private $config = [];
    private $errors = [];
    
    public function __construct() {
        session_start();
    }
    
    /**
     * Check system requirements
     */
    public function checkRequirements() {
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'json' => extension_loaded('json'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd'),
            'writable_root' => is_writable(dirname(__DIR__)),
            'writable_api' => is_writable(dirname(__DIR__) . '/api'),
        ];
        
        return $requirements;
    }
    
    /**
     * Test database connection
     */
    public function testConnection($host, $dbname, $username, $password) {
        try {
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Try to select the database
            $pdo->exec("USE `$dbname`");
            return ['success' => true, 'database_exists' => true];
            
        } catch (PDOException $e) {
            if ($e->getCode() == 1049) {
                // Database doesn't exist, but connection works
                return ['success' => true, 'database_exists' => false];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create database if it doesn't exist
     */
    public function createDatabase($host, $dbname, $username, $password) {
        try {
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return ['success' => true];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Run SQL file
     */
    public function runSQLFile($filename) {
        try {
            $sql = file_get_contents(__DIR__ . '/../sql/' . $filename);
            
            // Split by semicolon but ignore those inside quotes
            $statements = $this->splitSQL($sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && $statement !== ';') {
                    $this->db->exec($statement);
                }
            }
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Split SQL statements properly
     */
    private function splitSQL($sql) {
        $statements = [];
        $buffer = '';
        $in_string = false;
        $string_char = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (($char === '"' || $char === "'") && ($i === 0 || $sql[$i-1] !== '\\')) {
                if (!$in_string) {
                    $in_string = true;
                    $string_char = $char;
                } elseif ($char === $string_char) {
                    $in_string = false;
                }
            }
            
            if ($char === ';' && !$in_string) {
                $statements[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        
        if (!empty(trim($buffer))) {
            $statements[] = $buffer;
        }
        
        return $statements;
    }
    
    /**
     * Connect to database
     */
    public function connectDatabase($host, $dbname, $username, $password) {
        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $this->db = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            return true;
        } catch (PDOException $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Create admin user
     */
    public function createAdminUser($username, $email, $password) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, role, created_at, updated_at)
                VALUES (?, ?, ?, 'admin', NOW(), NOW())
            ");
            
            $stmt->execute([$username, $email, $hashedPassword]);
            return ['success' => true];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate .env file
     */
    public function generateEnvFile($config) {
        $envContent = "# Database Configuration\n";
        $envContent .= "DB_HOST={$config['db_host']}\n";
        $envContent .= "DB_NAME={$config['db_name']}\n";
        $envContent .= "DB_USER={$config['db_user']}\n";
        $envContent .= "DB_PASS={$config['db_pass']}\n\n";
        
        $envContent .= "# Site Configuration\n";
        $envContent .= "SITE_NAME={$config['site_name']}\n";
        $envContent .= "SITE_URL={$config['site_url']}\n";
        $envContent .= "ACTIVE_THEME={$config['active_theme']}\n\n";
        
        $envContent .= "# App Configuration\n";
        $envContent .= "APP_ENV=production\n";
        $envContent .= "APP_DEBUG=false\n";
        
        $envPath = dirname(__DIR__) . '/.env';
        
        if (file_put_contents($envPath, $envContent)) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Could not write .env file'];
    }
    
    /**
     * Set folder permissions
     */
    public function setPermissions() {
        $folders = [
            dirname(__DIR__) . '/api/uploads',
            dirname(__DIR__) . '/api/logs',
        ];
        
        foreach ($folders as $folder) {
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            } else {
                chmod($folder, 0777);
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Create install lock file
     */
    public function createLockFile() {
        $lockFile = dirname(__DIR__) . '/install.lock';
        $content = "Installation completed on: " . date('Y-m-d H:i:s') . "\n";
        $content .= "DO NOT DELETE THIS FILE\n";
        
        return file_put_contents($lockFile, $content) !== false;
    }
    
    /**
     * Delete installer directory
     */
    public function deleteInstaller() {
        $installerDir = __DIR__ . '/..';
        return $this->rrmdir($installerDir);
    }
    
    /**
     * Recursive directory removal
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
            return true;
        }
        return false;
    }
    
    /**
     * Save site configuration to settings table
     */
    public function saveSettings($config) {
        try {
            $stmt = $this->db->prepare("REPLACE INTO settings (setting_key, setting_value, setting_type, is_public) VALUES (?, ?, ?, ?)");
            
            $settings = [
                'site_name' => [$config['site_name'], 'text', 1],
                'site_description' => [$config['site_description'], 'text', 1],
                'contact_email' => [$config['contact_email'], 'text', 1],
                'contact_phone' => [$config['contact_phone'], 'text', 1],
                'currency_symbol' => [$config['currency_symbol'], 'text', 1],
                'site_logo' => [$config['site_logo'], 'text', 1],
                'company_name' => [$config['company_name'], 'text', 1],
                'site_hours' => [$config['site_hours'], 'text', 1],
                'site_address' => [$config['site_address'], 'text', 1],
                'minimum_order_value' => [$config['minimum_order_value'], 'number', 1],
                'free_delivery_threshold' => [$config['free_delivery_threshold'], 'number', 1],
                'payment_mode' => [$config['payment_mode'], 'text', 1],
                'smtp_host' => [$config['smtp_host'], 'text', 1],
                'smtp_port' => [$config['smtp_port'], 'number', 1],
                'smtp_username' => [$config['smtp_username'], 'text', 1],
                'smtp_password' => [$config['smtp_password'], 'text', 0], // Private
                'active_theme' => [$config['active_theme'], 'select', 1],
                'site_url' => [$config['site_url'], 'text', 1],
            ];
            
            foreach ($settings as $key => $data) {
                $stmt->execute([$key, $data[0], $data[1], $data[2]]);
            }
            
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build frontend assets using npm
     */
    public function buildFrontend() {
        if (!function_exists('shell_exec')) {
            return ['success' => false, 'error' => 'shell_exec is disabled'];
        }

        // Increase execution time for build
        set_time_limit(600);
        
        $rootDir = dirname(__DIR__);
        $command = "cd " . escapeshellarg($rootDir) . " && npm install && npm run build";
        
        try {
            // redirect stderr to stdout to capture errors
            $output = shell_exec($command . " 2>&1");
            
            // Check if dist folder was created
            if (is_dir($rootDir . '/dist')) {
                 return ['success' => true, 'output' => $output];
            } else {
                 return ['success' => false, 'error' => "Build failed. Output: " . substr($output, -500)];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deploy from local payload (Standalone Mode)
     */
    public function installFromPayload() {
        $payloadDir = __DIR__ . '/../payload';
        $rootDir = dirname(dirname(__DIR__)); // Parent of install folder
        
        if (!is_dir($payloadDir)) {
             return ['success' => false, 'error' => 'Payload directory not found'];
        }
        
        $filesMoved = 0;
        try {
            // Move/Copy files from payload to root
            $this->recurseMove($payloadDir, $rootDir);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Recursive Move/Copy
     */
    private function recurseMove($src, $dst) {
        $dir = opendir($src);
        if (!is_dir($dst)) @mkdir($dst, 0777, true);
        
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                
                if (is_dir($srcFile)) {
                    $this->recurseMove($srcFile, $dstFile);
                } else {
                    // Try rename (move) first, fall back to copy
                    if (!@rename($srcFile, $dstFile)) {
                         copy($srcFile, $dstFile);
                         unlink($srcFile); // Delete source after copy to save space?
                    }
                }
            }
        }
        closedir($dir);
    }

    /**
     * Deploy built files to root
     */
    public function deployBuild() {
        $rootDir = dirname(__DIR__);
        $distDir = $rootDir . '/dist';
        
        if (!is_dir($distDir)) {
            return ['success' => false, 'error' => 'Dist directory not found'];
        }
        
        try {
            // Copy contents of dist to root
            $this->recurseCopy($distDir, $rootDir);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Recursive copy function
     */
    private function recurseCopy($src, $dst) {
        $dir = opendir($src);
        if (!is_dir($dst)) @mkdir($dst);
        
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                // Prevent recursive copying of dist into itself (if dist is inside root)
                // But here we are copying FROM dist TO root.
                // If we copy dist/dist to root/dist, it's fine.
                // IMPORTANT: Don't copy 'install' folder if it exists in dist (unlikely)
                
                if (is_dir($src . '/' . $file)) {
                    $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    
    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }
}
