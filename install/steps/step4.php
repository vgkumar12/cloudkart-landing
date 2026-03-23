<h2>Admin Account</h2>
<p class="subtitle">Create your administrator account.</p>

<form method="post">
    <div class="form-group">
        <label>Admin Username</label>
        <input type="text" name="username" value="admin" required>
    </div>

    <div class="form-group">
        <label>Admin Email</label>
        <input type="email" name="email" value="admin@example.com" required>
    </div>

    <div class="form-group">
        <label>Admin Password</label>
        <input type="password" name="password" required minlength="6">
        <p style="font-size: 12px; color: #6b7280; margin-top: 4px;">Minimum 6 characters</p>
    </div>

    <div class="actions">
        <a href="index.php?step=3" class="btn-secondary">Back</a>
        <button type="submit" class="btn">Start Installation &rarr;</button>
    </div>
</form>
