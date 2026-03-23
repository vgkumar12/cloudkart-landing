<h2>Site Configuration</h2>
<p class="subtitle">Set up your store details and choose a theme.</p>

<form method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label>Store Name</label>
        <input type="text" name="site_name" value="<?php echo $_POST['site_name'] ?? 'My Awesome Shop'; ?>" required>
    </div>

    <div class="form-group">
        <label>Store URL</label>
        <input type="text" name="site_url" value="<?php echo $_POST['site_url'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . str_replace('/install/index.php', '', $_SERVER['PHP_SELF']); ?>" required>
    </div>

    <div class="form-group">
        <label>Store Description</label>
        <input type="text" name="site_description" value="<?php echo $_POST['site_description'] ?? 'Best quality products online'; ?>">
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label>Contact Email</label>
            <input type="email" name="contact_email" value="<?php echo $_POST['contact_email'] ?? 'support@example.com'; ?>">
        </div>
        <div class="form-group">
            <label>Contact Phone</label>
            <input type="text" name="contact_phone" value="<?php echo $_POST['contact_phone'] ?? '+91 98765 43210'; ?>">
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label>Currency Symbol</label>
            <input type="text" name="currency_symbol" value="<?php echo $_POST['currency_symbol'] ?? '₹'; ?>" style="width: 80px;">
        </div>
        <div class="form-group">
            <label>Store Logo</label>
            <?php if (isset($_POST['site_logo']) && !empty($_POST['site_logo'])): ?>
                <div style="margin-bottom: 5px;">
                    <img src="../<?php echo $_POST['site_logo']; ?>" style="height: 40px; vertical-align: middle; border: 1px solid #ddd; border-radius: 4px; padding: 2px;">
                    <span style="font-size: 11px; color: #666;"><?php echo basename($_POST['site_logo']); ?></span>
                </div>
                <input type="hidden" name="existing_logo" value="<?php echo $_POST['site_logo']; ?>">
            <?php endif; ?>
            <input type="file" name="site_logo_file" accept="image/*">
            <input type="hidden" name="site_logo" value="<?php echo $_POST['site_logo'] ?? 'src/assets/logo.png'; ?>">
            <p style="font-size: 11px; color: #6b7280;">Upload to replace default. Max 2MB.</p>
        </div>
    </div>

    <!-- Extended Site Info -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="company_name" value="<?php echo $_POST['company_name'] ?? 'My Company'; ?>">
        </div>
        <div class="form-group">
            <label>Business Hours</label>
            <input type="text" name="site_hours" value="<?php echo $_POST['site_hours'] ?? 'Mon-Sat: 9AM-8PM'; ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Site Address</label>
        <textarea name="site_address" rows="3" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"><?php echo $_POST['site_address'] ?? ''; ?></textarea>
    </div>

    <!-- Commerce Settings -->
    <h3 style="font-size: 16px; margin: 20px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Commerce Settings</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label>Min Order Value (<?php echo $_POST['currency_symbol'] ?? '₹'; ?>)</label>
            <input type="number" name="minimum_order_value" value="<?php echo $_POST['minimum_order_value'] ?? '500'; ?>">
        </div>
        <div class="form-group">
            <label>Free Delivery Threshold (<?php echo $_POST['currency_symbol'] ?? '₹'; ?>)</label>
            <input type="number" name="free_delivery_threshold" value="<?php echo $_POST['free_delivery_threshold'] ?? '1000'; ?>">
        </div>
    </div>
    
    <div class="form-group" style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-top: 15px;">
        <label>Payment Mode</label>
        <div style="display: flex; gap: 20px; margin-top: 5px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="radio" name="payment_mode" value="estimate" <?php echo (!isset($_POST['payment_mode']) || $_POST['payment_mode'] == 'estimate') ? 'checked' : ''; ?> style="margin-right: 8px;">
                <div>
                    <strong>Estimate Mode</strong><br>
                    <span style="font-size: 11px; color: #666;">Users place order, you contact them. (Crackers default)</span>
                </div>
            </label>
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="radio" name="payment_mode" value="online" <?php echo (isset($_POST['payment_mode']) && $_POST['payment_mode'] == 'online') ? 'checked' : ''; ?> style="margin-right: 8px;">
                <div>
                    <strong>Online Payment</strong><br>
                    <span style="font-size: 11px; color: #666;">Enable Gateways (Razorpay/PhonePe) later in admin.</span>
                </div>
            </label>
        </div>
    </div>

    <!-- Email Settings (SMTP) -->
    <h3 style="font-size: 16px; margin: 20px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Email Settings (SMTP) <span style="font-size: 12px; font-weight: normal; color: #777;">Optional - for OTPs/Notifications</span></h3>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div class="form-group">
            <label>SMTP Host</label>
            <input type="text" name="smtp_host" value="<?php echo $_POST['smtp_host'] ?? ''; ?>" placeholder="smtp.gmail.com">
        </div>
        <div class="form-group">
            <label>Port</label>
            <input type="text" name="smtp_port" value="<?php echo $_POST['smtp_port'] ?? '587'; ?>">
        </div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="form-group">
            <label>SMTP Username</label>
            <input type="text" name="smtp_username" value="<?php echo $_POST['smtp_username'] ?? ''; ?>">
        </div>
        <div class="form-group">
            <label>SMTP Password</label>
            <input type="password" name="smtp_password" value="<?php echo $_POST['smtp_password'] ?? ''; ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Select Default Theme</label>
        <div class="theme-grid">
            <label class="theme-card <?php echo (!isset($_POST['active_theme']) || $_POST['active_theme'] == 'general') ? 'selected' : ''; ?>" onclick="selectTheme(this)">
                <input type="radio" name="active_theme" value="general" checked>
                <div style="font-size: 32px; margin-bottom: 10px;">🛒</div>
                <strong>General</strong>
                <p style="font-size: 12px; color: #6b7280;">Modern, Blue & Orange</p>
            </label>
            
            <label class="theme-card" onclick="selectTheme(this)">
                <input type="radio" name="active_theme" value="organic">
                <div style="font-size: 32px; margin-bottom: 10px;">🌿</div>
                <strong>Organic</strong>
                <p style="font-size: 12px; color: #6b7280;">Fresh, Green & Nature</p>
            </label>
            
            <label class="theme-card" onclick="selectTheme(this)">
                <input type="radio" name="active_theme" value="crackers">
                <div style="font-size: 32px; margin-bottom: 10px;">🧨</div>
                <strong>Crackers</strong>
                <p style="font-size: 12px; color: #6b7280;">Vibrant, Seasonal</p>
            </label>
        </div>
    </div>

    <div class="form-group" style="margin-top: 30px;">
        <label style="display: flex; align-items: center; cursor: pointer;">
            <input type="checkbox" name="install_sample_data" value="1" checked style="width: auto; margin-right: 10px;">
            <span>Install Sample Products & Data</span>
        </label>
        <p style="font-size: 12px; color: #6b7280; margin-left: 24px;">Recommended for testing. Includes products, categories, and settings.</p>
    </div>

    <div class="actions">
        <a href="index.php?step=2" class="btn-secondary">Back</a>
        <button type="submit" class="btn">Next Step &rarr;</button>
    </div>
</form>

<script>
function selectTheme(card) {
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('input').checked = true;
}
</script>
