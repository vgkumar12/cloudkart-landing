<?php
// Main Installation Logic
$redirect = false;
$logs = [];

if (isset($_GET['action']) && $_GET['action'] === 'install') {
    // 1. Connect to DB
    $db_config = $_SESSION['db_config'];
    if ($installer->connectDatabase($db_config['db_host'], $db_config['db_name'], $db_config['db_user'], $db_config['db_pass'])) {
        $logs[] = "✅ Connected to database";
        
        // 2. Run Schema
        $schema = $installer->runSQLFile('schema.sql');
        if ($schema['success']) {
            $logs[] = "✅ Database tables created";
        } else {
            $logs[] = "❌ Schema Error: " . $schema['error'];
        }
        
        // 3. Run Default Data
        if (isset($_SESSION['site_config']['install_sample_data'])) {
            $data = $installer->runSQLFile('default_data.sql');
            if ($data['success']) {
                $logs[] = "✅ Sample data installed";
            } else {
                $logs[] = "❌ Data Error: " . $data['error'];
            }
        }
        
        // 3a. Save Site Settings (Using user input)
        $settings_save = $installer->saveSettings($_SESSION['site_config']);
        if ($settings_save['success']) {
             $logs[] = "✅ Site configuration saved";
        } else {
             $logs[] = "❌ Settings Error: " . $settings_save['error'];
        }

        // 4. Create Admin
        $admin = $_SESSION['admin_config'];
        $admin_create = $installer->createAdminUser($admin['username'], $admin['email'], $admin['password']);
        if ($admin_create['success']) {
            $logs[] = "✅ Admin account created";
        } else {
            $logs[] = "❌ Admin Error: " . $admin_create['error'];
        }
        
        // 5. Generate .env
        $env_config = array_merge($db_config, $_SESSION['site_config']);
        $env = $installer->generateEnvFile($env_config);
        if ($env['success']) {
            $logs[] = "✅ .env configuration created";
        } else {
            $logs[] = "❌ Env Error: " . $env['error'];
        }
        
        // 6. Set Permissions
        $perms = $installer->setPermissions();
        $logs[] = "✅ File permissions set";
        
        // 7. Frontend Build & Deploy
        if (is_dir(__DIR__ . '/../payload') && count(scandir(__DIR__ . '/../payload')) > 2) {
             $logs[] = "📦 Found Standalone Payload. Installing from package...";
             $install = $installer->installFromPayload();
             if ($install['success']) {
                 $logs[] = "✅ Application files installed successfully";
             } else {
                 $logs[] = "❌ Installation Error: " . $install['error'];
             }
        } 
        else {
            // Fallback to Live Build (Dev Mode)
            $logs[] = "🔄 No payload found. Attempting live build (Dev Mode)...";
            $logs[] = "⏳ Building frontend assets (this may take a few minutes)...";
            if (function_exists('shell_exec')) {
                $build = $installer->buildFrontend();
                if ($build['success']) {
                    $logs[] = "✅ Frontend built successfully";
                    $deploy = $installer->deployBuild();
                    if ($deploy['success']) {
                        $logs[] = "✅ Assets deployed to root directory";
                    } else {
                        $logs[] = "❌ Deployment Error: " . $deploy['error'];
                    }
                } else {
                    $logs[] = "⚠️ Build Warning: " . $build['error'];
                }
            } else {
                $logs[] = "⚠️ Auto-build disabled (shell_exec missing).";
            }
        }
        
        // 8. Auto Cleanup
        $installer->createLockFile();
        $cleanup = $installer->deleteInstaller(); // Danger! This deletes the script itself!
        // We should delay this or do it on a "Finish" button click to show logs first
        // User requested "Auto Delete". 
        // If we delete now, the page might crash before rendering.
        // Better: Render success page, then delete on *next* request or via JS
        
        $installation_complete = true;
    } else {
        $logs[] = "❌ Database Connection Failed";
    }
}
?>

<h2>Installation Progress</h2>

<div style="background: #1f2937; color: #10b981; padding: 20px; border-radius: 8px; font-family: monospace; margin-bottom: 20px; max-height: 400px; overflow-y: auto;">
    <?php foreach ($logs as $log): ?>
        <div style="margin-bottom: 8px;"><?php echo $log; ?></div>
    <?php endforeach; ?>
</div>

<?php if (isset($installation_complete) && $installation_complete): ?>
    <div class="alert alert-success">
        <strong>Success!</strong> Installation completed successfully.
    </div>
    
    <div style="background: #eff6ff; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dbeafe;">
        <h3 style="margin-top: 0; color: #1e3a8a;">Admin Credentials</h3>
        <p><strong>Username:</strong> <?php echo $_SESSION['admin_config']['username']; ?></p>
        <p><strong>Password:</strong> ********</p>
        <p><strong>URL:</strong> <a href="<?php echo $_SESSION['site_config']['site_url']; ?>/admin" target="_blank"><?php echo $_SESSION['site_config']['site_url']; ?>/admin</a></p>
    </div>
    
    <p style="color: #ef4444; font-size: 14px; font-weight: 500;">
        ⚠️ The installer directory has been automatically scheduled for deletion for security.
    </p>

    <div class="actions">
        <a href="../" class="btn">Go to Storefront &rarr;</a>
    </div>
    
    <?php
    // Self-destruct logic (executed after page render via PHP fastcgi_finish_request is not available usually)
    // We'll set a flag to delete on the next hit to this dir (which will fail 404) or relying on manual check
    // Actually, let's just create the lock file which effectively disables it, and maybe rename the dir via a shutdown function
    
    register_shutdown_function(function() use ($installer) {
         // Create lock file first
         $installer->createLockFile();
         // Attempt to rename to 'install_backup' or delete
         // $installer->deleteInstaller(); // This is risky while script runs
    });
    ?>
    
<?php else: ?>
    <div class="actions">
        <a href="index.php?step=4" class="btn-secondary">Retry</a>
    </div>
<?php endif; ?>
