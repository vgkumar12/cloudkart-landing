<?php
/**
 * E-Commerce Installer Controller
 */

require_once 'includes/installer.class.php';

$installer = new Installer();

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$total_steps = 5;

// Prevent jumping ahead
if (!isset($_SESSION['max_step'])) {
    $_SESSION['max_step'] = 1;
}

if ($step > $_SESSION['max_step'] && $step > 1) {
    header("Location: index.php?step=" . $_SESSION['max_step']);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // Requirements
            $reqs = $installer->checkRequirements();
            $all_ok = true;
            foreach ($reqs as $req) {
                if (!$req) $all_ok = false;
            }
            if ($all_ok) {
                $_SESSION['max_step'] = max($_SESSION['max_step'], 2);
                header("Location: index.php?step=2");
                exit;
            }
            break;
            
        case 2: // Database
            $host = $_POST['db_host'] ?? 'localhost';
            $name = $_POST['db_name'] ?? '';
            $user = $_POST['db_user'] ?? 'root';
            $pass = $_POST['db_pass'] ?? '';
            
            $test = $installer->testConnection($host, $name, $user, $pass);
            
            if ($test['success']) {
                $_SESSION['db_config'] = [
                    'db_host' => $host,
                    'db_name' => $name,
                    'db_user' => $user,
                    'db_pass' => $pass
                ];
                
                // Try to create DB if needed
                if (!$test['database_exists']) {
                    $create = $installer->createDatabase($host, $name, $user, $pass);
                    if (!$create['success']) {
                        $error = "Could not create database: " . $create['error'];
                        break;
                    }
                }
                
                $_SESSION['max_step'] = max($_SESSION['max_step'], 3);
                header("Location: index.php?step=3");
                exit;
            } else {
                $error = "Connection failed: " . ($test['error'] ?? 'Unknown error');
            }
            break;
            
        case 3: // Site Config
            // Handle File Upload
            $logo_path = $_POST['site_logo']; // Default or existing
            
            if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../api/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $ext = pathinfo($_FILES['site_logo_file']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . time() . '.' . $ext;
                $targetFile = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['site_logo_file']['tmp_name'], $targetFile)) {
                    $logo_path = 'api/uploads/' . $filename;
                }
            }

            $_SESSION['site_config'] = [
                'site_name' => $_POST['site_name'],
                'site_url' => $_POST['site_url'],
                'site_description' => $_POST['site_description'],
                'contact_email' => $_POST['contact_email'],
                'contact_phone' => $_POST['contact_phone'],
                'currency_symbol' => $_POST['currency_symbol'],
                'site_logo' => $logo_path,
                'company_name' => $_POST['company_name'],
                'site_hours' => $_POST['site_hours'],
                'site_address' => $_POST['site_address'],
                'minimum_order_value' => $_POST['minimum_order_value'],
                'free_delivery_threshold' => $_POST['free_delivery_threshold'],
                'payment_mode' => $_POST['payment_mode'],
                'smtp_host' => $_POST['smtp_host'],
                'smtp_port' => $_POST['smtp_port'],
                'smtp_username' => $_POST['smtp_username'],
                'smtp_password' => $_POST['smtp_password'],
                'active_theme' => $_POST['active_theme'],
                'install_sample_data' => isset($_POST['install_sample_data'])
            ];
            
            $_SESSION['max_step'] = max($_SESSION['max_step'], 4);
            header("Location: index.php?step=4");
            exit;
            
        case 4: // Admin User
            $_SESSION['admin_config'] = [
                'username' => $_POST['username'],
                'email' => $_POST['email'],
                'password' => $_POST['password']
            ];
            
            // Proceed to install
            $_SESSION['max_step'] = max($_SESSION['max_step'], 5);
            header("Location: index.php?step=5&action=install");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce Installer</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="installer-container">
    <div class="header">
        <div class="logo">🛒 Shop Installer</div>
    </div>

    <div class="card">
        <div class="progress-bar">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1. Req</div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2. Database</div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3. Config</div>
            <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4. Admin</div>
            <div class="step <?php echo $step >= 5 ? 'active' : ''; ?>">5. Install</div>
        </div>

        <div class="content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php
            // Load step view
            $step_file = 'steps/step' . $step . '.php';
            if (file_exists($step_file)) {
                include $step_file;
            } else {
                echo '<p>Step file not found.</p>';
            }
            ?>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; color: #6b7280; font-size: 13px;">
        &copy; <?php echo date('Y'); ?> E-Commerce v2.0 Installer
    </div>
</div>

</body>
</html>
