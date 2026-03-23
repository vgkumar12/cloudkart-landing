<h2>System Requirements</h2>
<p class="subtitle">Please ensure your server meets the following requirements before proceeding.</p>

<div class="req-list">
    <?php
    $reqs = $installer->checkRequirements();
    $all_ok = true;
    
    $labels = [
        'php_version' => 'PHP Version 8.1+',
        'pdo' => 'PDO Extension',
        'pdo_mysql' => 'PDO MySQL Extension',
        'json' => 'JSON Extension',
        'fileinfo' => 'Fileinfo Extension',
        'gd' => 'GD Extension',
        'writable_root' => 'Root Directory Writable',
        'writable_api' => 'API Directory Writable',
    ];

    foreach ($requirements as $key => $status) {
        if (!$status) $all_ok = false;
        ?>
        <div class="req-item">
            <div class="status-icon <?php echo $status ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $status ? '✓' : '✗'; ?>
            </div>
            <div>
                <strong><?php echo $labels[$key] ?? $key; ?></strong>
                <?php if ($key === 'php_version'): ?>
                    <span class="text-sm text-gray-500">(Current: <?php echo PHP_VERSION; ?>)</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    ?>
</div>

<div class="actions">
    <?php if ($all_ok): ?>
        <form method="post">
            <button type="submit" class="btn">Next Step &rarr;</button>
        </form>
    <?php else: ?>
        <button class="btn" disabled style="opacity: 0.5; cursor: not-allowed;">Fix Requirements to Continue</button>
        <button onclick="window.location.reload()" class="btn-secondary">Check Again</button>
    <?php endif; ?>
</div>
