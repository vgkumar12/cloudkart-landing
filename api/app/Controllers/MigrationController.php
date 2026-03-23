<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * MigrationController
 * Runs DB + file migrations across ALL provisioned stores.
 * Protected by a secret token defined in config.php (MIGRATION_SECRET).
 *
 * POST /api/migrate
 * Body: { "secret": "...", "dry_run": false }
 */
class MigrationController {

    private $platformDb;  // platform_stores list
    private $storeDb;     // ck_* tables
    private $results = [];

    public function __construct() {
        $this->platformDb = Database::getPlatformConnection();
        $this->storeDb    = Database::getStoreConnection();
    }

    public function run(): void {
        $request  = new Request();
        $response = new Response();

        // — Auth guard —
        $secret = $request->input('secret');
        $expected = defined('MIGRATION_SECRET') ? MIGRATION_SECRET : '';
        if (!$secret || !$expected || !hash_equals($expected, $secret)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

        $dryRun = (bool) $request->input('dry_run');

        // Fetch all provisioned stores
        $stores = $this->platformDb->query(
            "SELECT id, subdomain, table_prefix FROM platform_stores ORDER BY id ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($stores as $store) {
            $prefix = $store['table_prefix'];
            $log    = ['store' => $store['subdomain'], 'prefix' => $prefix, 'migrations' => []];

            $log['migrations'][] = $this->migration_add_show_in_menu($prefix, $dryRun);
            $log['migrations'][] = $this->migration_add_category_menu_item($prefix, $dryRun);
            $log['migrations'][] = $this->migration_sync_files($store['subdomain'], $dryRun);

            $this->results[] = $log;
        }

        $response->success([
            'dry_run' => $dryRun,
            'stores_processed' => count($stores),
            'results' => $this->results
        ], $dryRun ? 'Dry run complete — no changes made' : 'Migrations applied successfully');
    }

    // -------------------------------------------------------------------------
    // Migration 1: Add show_in_menu column to categories table
    // -------------------------------------------------------------------------
    private function migration_add_show_in_menu(string $prefix, bool $dry): array {
        $table = $prefix . 'categories';
        $name  = 'add_show_in_menu_to_categories';

        // Check if column already exists
        $cols = $this->storeDb->query("SHOW COLUMNS FROM `$table` LIKE 'show_in_menu'")->fetchAll();
        if (!empty($cols)) {
            return ['migration' => $name, 'status' => 'skipped', 'reason' => 'column already exists'];
        }

        if ($dry) {
            return ['migration' => $name, 'status' => 'would_run', 'sql' => "ALTER TABLE `$table` ADD COLUMN `show_in_menu` TINYINT(1) DEFAULT 1 AFTER `is_active`"];
        }

        try {
            $this->storeDb->exec("ALTER TABLE `$table` ADD COLUMN `show_in_menu` TINYINT(1) DEFAULT 1 AFTER `is_active`");
            return ['migration' => $name, 'status' => 'applied'];
        } catch (\Exception $e) {
            return ['migration' => $name, 'status' => 'error', 'reason' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Migration 2: Insert "All Categories" (#category-menu) into header menu
    // -------------------------------------------------------------------------
    private function migration_add_category_menu_item(string $prefix, bool $dry): array {
        $menuTable = $prefix . 'menus';
        $itemTable = $prefix . 'menu_items';
        $name      = 'add_all_categories_menu_item';

        // Find header menu
        $menu = $this->storeDb->query("SELECT id FROM `$menuTable` WHERE location = 'header' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        if (!$menu) {
            return ['migration' => $name, 'status' => 'skipped', 'reason' => 'no header menu found'];
        }
        $menuId = $menu['id'];

        // Check if #category-menu item already exists
        $existing = $this->storeDb->prepare("SELECT id FROM `$itemTable` WHERE menu_id = ? AND url = '#category-menu' LIMIT 1");
        $existing->execute([$menuId]);
        if ($existing->fetch()) {
            return ['migration' => $name, 'status' => 'skipped', 'reason' => 'item already exists'];
        }

        // Shift existing items' sort_order up to make room at position 2
        $sql = "INSERT INTO `$itemTable` (menu_id, title, url, sort_order, status) VALUES (?, 'All Categories', '#category-menu', 2, 'active')";

        if ($dry) {
            return ['migration' => $name, 'status' => 'would_run', 'sql' => str_replace('?', $menuId, $sql)];
        }

        try {
            // Push items at sort_order >= 2 down by 1
            $this->storeDb->prepare("UPDATE `$itemTable` SET sort_order = sort_order + 1 WHERE menu_id = ? AND sort_order >= 2")
                      ->execute([$menuId]);

            $this->storeDb->prepare($sql)->execute([$menuId]);
            return ['migration' => $name, 'status' => 'applied'];
        } catch (\Exception $e) {
            return ['migration' => $name, 'status' => 'error', 'reason' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Migration 3: Sync new dist/ files into stores/{subdomain}/
    // Only syncs assets/ and api/ — never overwrites settings.json or config.php
    // -------------------------------------------------------------------------
    private function migration_sync_files(string $subdomain, bool $dry): array {
        $name      = 'sync_dist_files';
        $distPath  = defined('CLOUDKART_ROOT') ? CLOUDKART_ROOT . DIRECTORY_SEPARATOR . 'dist' : null;
        $storePath = defined('CLOUDKART_ROOT') ? CLOUDKART_ROOT . DIRECTORY_SEPARATOR . 'stores' . DIRECTORY_SEPARATOR . $subdomain : null;

        if (!$distPath || !is_dir($distPath)) {
            return ['migration' => $name, 'status' => 'skipped', 'reason' => 'dist/ not found'];
        }
        if (!$storePath || !is_dir($storePath)) {
            return ['migration' => $name, 'status' => 'skipped', 'reason' => "stores/$subdomain not found (CPanel hosting — manual sync required)"];
        }

        // Only sync these directories — never touch settings.json, config.php, images/, or user data
        $syncDirs = ['assets', 'api'];
        $synced   = [];

        foreach ($syncDirs as $dir) {
            $src  = $distPath  . DIRECTORY_SEPARATOR . $dir;
            $dest = $storePath . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($src)) continue;

            if ($dry) {
                $synced[] = "$dir/ (would sync)";
                continue;
            }

            try {
                $this->copyRecursive($src, $dest);
                $synced[] = "$dir/ synced";
            } catch (\Exception $e) {
                return ['migration' => $name, 'status' => 'error', 'reason' => $e->getMessage()];
            }
        }

        return [
            'migration' => $name,
            'status'    => $dry ? 'would_run' : 'applied',
            'dirs'      => $synced
        ];
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
}
