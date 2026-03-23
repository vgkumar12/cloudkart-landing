<?php

/**
 * Bulk Import Log Model
 */

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class BulkImportLog extends Model {
    protected string $table = 'bulk_import_logs';
    
    public ?int $id = null;
    public ?string $import_type = null;
    public ?string $file_name = null;
    public ?int $file_size = null;
    public ?int $imported_by = null;
    public ?string $status = null;
    public ?int $successful_records = null;
    public ?int $failed_records = null;
    public ?string $error_log = null;
    public ?string $started_at = null;
    public ?string $completed_at = null;
    public ?string $created_at = null;
    
    /**
     * Get import history
     */
    public static function getHistory(int $limit = 20): array {
        $conn = Database::getConnection();
        $stmt = $conn->prepare("
            SELECT bil.*
            FROM bulk_import_logs bil
            ORDER BY bil.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($row) {
            return (new self($row))->toArray();
        }, $results);
    }
    
    /**
     * Create import log entry
     */
    public static function createLog(string $importType, string $fileName, int $fileSize, ?int $importedBy = null): self {
        return self::create([
            'import_type' => $importType,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'imported_by' => $importedBy,
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Update import log completion
     */
    public function updateCompletion(string $status, int $successfulRecords, int $failedRecords, array $errors): void {
        $this->update([
            'status' => $status,
            'successful_records' => $successfulRecords,
            'failed_records' => $failedRecords,
            'error_log' => json_encode($errors),
            'completed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function toArray(): array {
        $array = parent::toArray();
        $array['file_size'] = (int)($array['file_size'] ?? 0);
        $array['successful_records'] = (int)($array['successful_records'] ?? 0);
        $array['failed_records'] = (int)($array['failed_records'] ?? 0);
        if (isset($array['error_log']) && is_string($array['error_log'])) {
            $array['error_log'] = json_decode($array['error_log'], true) ?? [];
        }
        return $array;
    }
}

