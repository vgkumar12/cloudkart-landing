<h2>Database Configuration</h2>
<p class="subtitle">Enter your database connection details.</p>

<form method="post">
    <div class="form-group">
        <label>Database Host</label>
        <input type="text" name="db_host" value="<?php echo $_POST['db_host'] ?? 'localhost'; ?>" required>
    </div>

    <div class="form-group">
        <label>Database Name</label>
        <input type="text" name="db_name" value="<?php echo $_POST['db_name'] ?? 'shop_db'; ?>" required>
        <p style="font-size: 12px; color: #6b7280; margin-top: 4px;">If the database doesn't exist, we'll try to create it.</p>
    </div>

    <div class="form-group">
        <label>Database Username</label>
        <input type="text" name="db_user" value="<?php echo $_POST['db_user'] ?? 'root'; ?>" required>
    </div>

    <div class="form-group">
        <label>Database Password</label>
        <input type="password" name="db_pass" value="<?php echo $_POST['db_pass'] ?? ''; ?>">
    </div>

    <div class="actions">
        <a href="index.php?step=1" class="btn-secondary">Back</a>
        <button type="submit" class="btn">Test Connection & Continue &rarr;</button>
    </div>
</form>
