<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Helpers\Auth;

/**
 * StoreAdminController
 * Secure per-store management for super admins.
 * Every method calls Auth::requireSuperAdmin() — role is verified
 * from the HMAC-signed token, never from client input.
 *
 * Routes:
 *   GET  /api/admin/stores              → index()
 *   GET  /api/admin/stores/{id}         → show(id)
 *   GET  /api/admin/stores/{id}/pending → pending(id)
 *   POST /api/admin/stores/{id}/update  → update(id)
 *   POST /api/admin/stores/bulk-update  → bulkUpdate()
 */
class StoreAdminController {

    private \PDO $db;       // platform DB — platform_stores, users
    private \PDO $storeDb;  // stores DB  — ck_* tables

    public function __construct() {
        $this->db      = Database::getPlatformConnection();
        $this->storeDb = Database::getStoreConnection();
        $this->ensurePlatformColumns();
    }

    // =========================================================================
    // Public endpoints
    // =========================================================================

    /** GET /api/admin/stores — list all stores with version & migration status */
    public function index(): void {
        Auth::requireSuperAdmin();
        $response = new Response();

        $stores = $this->db->query("
            SELECT s.id, s.subdomain, s.store_name, s.table_prefix, s.theme,
                   s.status, s.is_trial, s.trial_ends_at, s.created_at,
                   s.app_version, s.last_synced_at,
                   u.name  AS owner_name,
                   u.email AS owner_email,
                   p.name  AS plan_name
            FROM   platform_stores s
            JOIN   users           u ON u.id = s.user_id
            JOIN   platform_plans  p ON p.id = s.plan_id
            ORDER  BY s.id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $latestVersion = $this->latestVersion();

        foreach ($stores as &$store) {
            $store['latest_version']  = $latestVersion;
            $store['needs_update']    = ($store['app_version'] ?? '') !== $latestVersion;
            $store['pending_count']   = count($this->getPendingMigrations($store));
        }
        unset($store);

        $response->success([
            'stores'         => $stores,
            'latest_version' => $latestVersion,
            'total'          => count($stores),
        ], 'Stores retrieved');
    }

    /** GET /api/admin/stores/{id} — single store detail */
    public function show(int $id): void {
        Auth::requireSuperAdmin();
        $response = new Response();

        $store = $this->fetchStore($id);
        if (!$store) { $response->notFound('Store not found'); return; }

        $store['pending_migrations'] = $this->getPendingMigrations($store);
        $store['latest_version']     = $this->latestVersion();
        $store['needs_update']       = ($store['app_version'] ?? '') !== $store['latest_version'];

        $response->success($store, 'Store retrieved');
    }

    /** GET /api/admin/stores/{id}/pending — what migrations are pending */
    public function pending(int $id): void {
        Auth::requireSuperAdmin();
        $response = new Response();

        $store = $this->fetchStore($id);
        if (!$store) { $response->notFound('Store not found'); return; }

        $response->success([
            'store_id'   => $id,
            'subdomain'  => $store['subdomain'],
            'pending'    => $this->getPendingMigrations($store),
            'latest_version' => $this->latestVersion(),
            'current_version' => $store['app_version'] ?? 'unknown',
        ], 'Pending migrations');
    }

    /** POST /api/admin/stores/{id}/update — apply migrations to one store */
    public function update(int $id): void {
        Auth::requireSuperAdmin();
        $request  = new Request();
        $response = new Response();

        $dryRun = (bool) $request->input('dry_run');

        $store = $this->fetchStore($id);
        if (!$store) { $response->notFound('Store not found'); return; }

        $result = $this->applyMigrations($store, $dryRun);

        if (!$dryRun && $result['success']) {
            $this->stampVersion($id);
        }

        $response->success($result, $dryRun ? 'Dry run complete' : 'Migrations applied');
    }

    /** POST /api/admin/stores/bulk-update — apply to selected stores */
    public function bulkUpdate(): void {
        Auth::requireSuperAdmin();
        $request  = new Request();
        $response = new Response();

        $ids    = $request->input('store_ids'); // array of ints
        $dryRun = (bool) $request->input('dry_run');

        if (!is_array($ids) || empty($ids)) {
            $response->error('store_ids array is required'); return;
        }

        $results = [];
        foreach ($ids as $id) {
            $id    = (int) $id;
            $store = $this->fetchStore($id);
            if (!$store) { $results[] = ['id' => $id, 'error' => 'not found']; continue; }

            $res = $this->applyMigrations($store, $dryRun);
            if (!$dryRun && $res['success']) {
                $this->stampVersion($id);
            }
            $results[] = $res;
        }

        $response->success([
            'dry_run' => $dryRun,
            'results' => $results,
        ], $dryRun ? 'Dry run complete' : 'Bulk update applied');
    }

    // =========================================================================
    // Migration engine
    // =========================================================================

    /** Returns list of pending (not yet applied) migrations for a store */
    private function getPendingMigrations(array $store): array {
        $pending = [];
        foreach ($this->allMigrations() as $m) {
            if ($m['check']($store)) {
                $pending[] = ['id' => $m['id'], 'label' => $m['label']];
            }
        }
        return $pending;
    }

    /** Runs all pending migrations for a store. Returns detailed log. */
    private function applyMigrations(array $store, bool $dry): array {
        $log     = [];
        $success = true;

        foreach ($this->allMigrations() as $m) {
            if (!$m['check']($store)) {
                $log[] = ['id' => $m['id'], 'label' => $m['label'], 'status' => 'already_applied'];
                continue;
            }
            try {
                $result  = $m['apply']($store, $dry);
                $log[]   = array_merge(['id' => $m['id'], 'label' => $m['label']], $result);
            } catch (\Exception $e) {
                $log[]   = ['id' => $m['id'], 'label' => $m['label'], 'status' => 'error', 'reason' => $e->getMessage()];
                $success = false;
            }
        }

        return [
            'store_id'  => $store['id'],
            'subdomain' => $store['subdomain'],
            'dry_run'   => $dry,
            'success'   => $success,
            'migrations' => $log,
        ];
    }

    /**
     * All known migrations.
     * Each entry: ['id', 'label', 'check' => fn(store):bool, 'apply' => fn(store, dry):array]
     * 'check' returns TRUE when the migration is still PENDING (needs to run).
     */
    private function allMigrations(): array {
        return [

            // ------------------------------------------------------------------
            // M001 — Add show_in_menu to categories
            // ------------------------------------------------------------------
            [
                'id'    => 'M001',
                'label' => 'Add show_in_menu column to categories',
                'check' => function (array $store): bool {
                    $table = $store['table_prefix'] . 'categories';
                    return empty($this->storeDb->query("SHOW COLUMNS FROM `$table` LIKE 'show_in_menu'")->fetchAll());
                },
                'apply' => function (array $store, bool $dry): array {
                    $table = $store['table_prefix'] . 'categories';
                    $sql   = "ALTER TABLE `$table` ADD COLUMN `show_in_menu` TINYINT(1) DEFAULT 1 AFTER `is_active`";
                    if ($dry) return ['status' => 'would_run', 'sql' => $sql];
                    $this->storeDb->exec($sql);
                    return ['status' => 'applied'];
                },
            ],

            // ------------------------------------------------------------------
            // M002 — Add "All Categories" (#category-menu) to header menu
            // ------------------------------------------------------------------
            [
                'id'    => 'M002',
                'label' => 'Add "All Categories" mega-menu item to header navigation',
                'check' => function (array $store): bool {
                    $menuTable = $store['table_prefix'] . 'menus';
                    $itemTable = $store['table_prefix'] . 'menu_items';
                    $menu = $this->storeDb->query("SELECT id FROM `$menuTable` WHERE location='header' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                    if (!$menu) return false;
                    $exists = $this->storeDb->prepare("SELECT id FROM `$itemTable` WHERE menu_id=? AND url='#category-menu' LIMIT 1");
                    $exists->execute([$menu['id']]);
                    return !$exists->fetch();
                },
                'apply' => function (array $store, bool $dry): array {
                    $menuTable = $store['table_prefix'] . 'menus';
                    $itemTable = $store['table_prefix'] . 'menu_items';
                    $menu = $this->storeDb->query("SELECT id FROM `$menuTable` WHERE location='header' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                    if (!$menu) return ['status' => 'skipped', 'reason' => 'no header menu'];

                    $menuId = $menu['id'];
                    $sql = "INSERT INTO `$itemTable` (menu_id, title, url, sort_order, status) VALUES ($menuId, 'All Categories', '#category-menu', 2, 'active')";
                    if ($dry) return ['status' => 'would_run', 'sql' => $sql];

                    $this->storeDb->prepare("UPDATE `$itemTable` SET sort_order = sort_order + 1 WHERE menu_id=? AND sort_order >= 2")->execute([$menuId]);
                    $this->storeDb->prepare("INSERT INTO `$itemTable` (menu_id, title, url, sort_order, status) VALUES (?, 'All Categories', '#category-menu', 2, 'active')")->execute([$menuId]);
                    return ['status' => 'applied'];
                },
            ],

            // ------------------------------------------------------------------
            // M003 — Sync dist/assets + dist/api into stores/{subdomain}/
            //        (local hosting only — skipped on CPanel)
            // ------------------------------------------------------------------
            [
                'id'    => 'M003',
                'label' => 'Sync latest app files (assets + API) to store directory',
                'check' => function (array $store): bool {
                    // Pending if store dir exists locally and version doesn't match
                    $storePath = $this->storePath($store['subdomain']);
                    if (!$storePath) return false; // CPanel = not applicable
                    $storeVersion = $this->readStoreVersion($storePath);
                    return $storeVersion !== $this->latestVersion();
                },
                'apply' => function (array $store, bool $dry): array {
                    $storePath = $this->storePath($store['subdomain']);
                    if (!$storePath) return ['status' => 'skipped', 'reason' => 'CPanel hosting — file sync must be done via cPanel File Manager or deploy script'];

                    $dist = defined('CLOUDKART_ROOT') ? CLOUDKART_ROOT . DIRECTORY_SEPARATOR . 'dist' : null;
                    if (!$dist || !is_dir($dist)) return ['status' => 'skipped', 'reason' => 'dist/ not found'];

                    if ($dry) return ['status' => 'would_run', 'dirs' => ['assets/', 'api/']];

                    foreach (['assets', 'api'] as $dir) {
                        $src  = $dist . DIRECTORY_SEPARATOR . $dir;
                        $dest = $storePath . DIRECTORY_SEPARATOR . $dir;
                        if (is_dir($src)) $this->copyRecursive($src, $dest);
                    }
                    // Write version marker to the store directory
                    file_put_contents($storePath . DIRECTORY_SEPARATOR . 'version.json', json_encode(['version' => $this->latestVersion(), 'synced_at' => date('c')]));
                    return ['status' => 'applied', 'dirs' => ['assets/', 'api/']];
                },
            ],

            // ------------------------------------------------------------------
            // M004 — Add Shiprocket columns to shipments table
            // ------------------------------------------------------------------
            [
                'id'    => 'M004',
                'label' => 'Add Shiprocket columns to shipments (shiprocket_order_id, shiprocket_shipment_id, shiprocket_awb, label_url)',
                'check' => function (array $store): bool {
                    $table = $store['table_prefix'] . 'shipments';
                    return empty($this->storeDb->query("SHOW COLUMNS FROM `$table` LIKE 'shiprocket_order_id'")->fetchAll());
                },
                'apply' => function (array $store, bool $dry): array {
                    $table = $store['table_prefix'] . 'shipments';
                    $sqls = [
                        "ALTER TABLE `$table` ADD COLUMN `shiprocket_order_id` VARCHAR(50) DEFAULT NULL AFTER `notes`",
                        "ALTER TABLE `$table` ADD COLUMN `shiprocket_shipment_id` VARCHAR(50) DEFAULT NULL AFTER `shiprocket_order_id`",
                        "ALTER TABLE `$table` ADD COLUMN `shiprocket_awb` VARCHAR(100) DEFAULT NULL AFTER `shiprocket_shipment_id`",
                        "ALTER TABLE `$table` ADD COLUMN `label_url` VARCHAR(500) DEFAULT NULL AFTER `shiprocket_awb`",
                        "ALTER TABLE `$table` ADD INDEX `idx_shipment_sr_awb` (`shiprocket_awb`)",
                    ];
                    if ($dry) return ['status' => 'would_run', 'sql' => $sqls];
                    try {
                        foreach ($sqls as $sql) {
                            $this->storeDb->exec($sql);
                        }
                        return ['status' => 'applied'];
                    } catch (\Exception $e) {
                        return ['status' => 'error', 'reason' => $e->getMessage()];
                    }
                },
            ],

        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function fetchStore(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, u.name AS owner_name, u.email AS owner_email, p.name AS plan_name
            FROM   platform_stores s
            JOIN   users           u ON u.id = s.user_id
            JOIN   platform_plans  p ON p.id = s.plan_id
            WHERE  s.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function stampVersion(int $id): void {
        $this->db->prepare("UPDATE platform_stores SET app_version=?, last_synced_at=NOW() WHERE id=?")
                 ->execute([$this->latestVersion(), $id]);
    }

    private function latestVersion(): string {
        $versionFile = defined('CLOUDKART_ROOT') ? CLOUDKART_ROOT . '/dist/version.json' : null;
        if ($versionFile && file_exists($versionFile)) {
            $data = json_decode(file_get_contents($versionFile), true);
            return $data['version'] ?? PLATFORM_VERSION;
        }
        return defined('PLATFORM_VERSION') ? PLATFORM_VERSION : '1.0.0';
    }

    private function storePath(string $subdomain): ?string {
        if (!defined('CLOUDKART_ROOT')) return null;
        $path = CLOUDKART_ROOT . DIRECTORY_SEPARATOR . 'stores' . DIRECTORY_SEPARATOR . $subdomain;
        return is_dir($path) ? $path : null;
    }

    private function readStoreVersion(string $storePath): string {
        $file = $storePath . DIRECTORY_SEPARATOR . 'version.json';
        if (!file_exists($file)) return 'unknown';
        $data = json_decode(file_get_contents($file), true);
        return $data['version'] ?? 'unknown';
    }

    private function copyRecursive(string $source, string $dest): void {
        if (!is_dir($dest)) mkdir($dest, 0755, true);
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            $target = $dest . DIRECTORY_SEPARATOR . $item->getSubPathName();
            $item->isDir() ? (is_dir($target) ?: mkdir($target, 0755, true)) : copy($item, $target);
        }
    }

    /** Add app_version + last_synced_at to platform_stores if missing (one-time, harmless) */
    private function ensurePlatformColumns(): void {
        try {
            if (empty($this->db->query("SHOW COLUMNS FROM `platform_stores` LIKE 'app_version'")->fetchAll())) {
                $this->db->exec("ALTER TABLE `platform_stores` ADD COLUMN `app_version` VARCHAR(50) DEFAULT NULL AFTER `status`");
            }
            if (empty($this->db->query("SHOW COLUMNS FROM `platform_stores` LIKE 'last_synced_at'")->fetchAll())) {
                $this->db->exec("ALTER TABLE `platform_stores` ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `app_version`");
            }
        } catch (\Exception $e) {
            // Non-fatal — version tracking simply won't work if DB perms restrict ALTER
            error_log('StoreAdminController: could not ensure platform columns — ' . $e->getMessage());
        }
    }
}
