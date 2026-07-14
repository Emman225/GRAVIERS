<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreBinlog extends Command
{
    protected $signature = 'db:restore-binlog {file : Path to the mysqlbinlog decoded output file}';

    protected $description = 'Parse a mysqlbinlog decoded output file and restore INSERT/UPDATE/DELETE operations into the database';

    /**
     * Cache of table column names, keyed by table name.
     * Each entry is an ordered array of column names matching ordinal position.
     */
    private array $columnCache = [];

    /**
     * Timestamp-like column name patterns that should be converted from Unix epoch to datetime.
     */
    private const TIMESTAMP_COLUMNS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'email_verified_at',
        'phone_verified_at',
        'verified_at',
    ];

    /**
     * Tables to skip entirely.
     */
    private const SKIP_TABLES = [
        'migrations',
    ];

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Parsing binlog file: {$filePath}");
        $this->info('Reading and parsing operations...');

        $operations = $this->parseFile($filePath);

        $totalOps = count($operations);
        $this->info("Found {$totalOps} operations to replay.");

        if ($totalOps === 0) {
            $this->warn('No operations found in the file.');
            return Command::SUCCESS;
        }

        // Stats
        $stats = []; // table => ['insert' => [ok, fail], 'update' => [ok, fail], 'delete' => [ok, fail]]
        $processed = 0;

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            DB::beginTransaction();

            foreach ($operations as $op) {
                $table = $op['table'];

                if (!isset($stats[$table])) {
                    $stats[$table] = [
                        'insert' => [0, 0],
                        'update' => [0, 0],
                        'delete' => [0, 0],
                    ];
                }

                try {
                    $this->executeOperation($op);
                    $stats[$table][$op['type']][0]++;
                } catch (\Throwable $e) {
                    $stats[$table][$op['type']][1]++;
                    $this->warn("Failed {$op['type']} on `{$table}`: {$e->getMessage()}");
                }

                $processed++;
                if ($processed % 100 === 0) {
                    $this->info("Progress: {$processed}/{$totalOps} operations processed...");
                }
            }

            DB::commit();
            $this->info('Transaction committed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Transaction rolled back due to error: {$e->getMessage()}");
            return Command::FAILURE;
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        // Print summary
        $this->newLine();
        $this->info('=== Restore Summary ===');
        $headers = ['Table', 'Type', 'Success', 'Failed'];
        $rows = [];
        foreach ($stats as $table => $types) {
            foreach ($types as $type => $counts) {
                if ($counts[0] > 0 || $counts[1] > 0) {
                    $rows[] = [$table, strtoupper($type), $counts[0], $counts[1]];
                }
            }
        }
        $this->table($headers, $rows);

        $totalSuccess = array_sum(array_map(fn($t) => $t['insert'][0] + $t['update'][0] + $t['delete'][0], $stats));
        $totalFailed = array_sum(array_map(fn($t) => $t['insert'][1] + $t['update'][1] + $t['delete'][1], $stats));
        $this->info("Total: {$totalSuccess} succeeded, {$totalFailed} failed out of {$totalOps} operations.");

        return Command::SUCCESS;
    }

    /**
     * Parse the binlog file and extract all operations.
     */
    private function parseFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        $operations = [];
        $currentOp = null;
        $currentSection = null; // 'set' or 'where'
        $currentFields = [];

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            // Detect INSERT
            if (preg_match('/^### INSERT INTO `([^`]+)`\.`([^`]+)`/', $line, $m)) {
                // Flush previous operation
                if ($currentOp !== null) {
                    $operations[] = $this->finalizeOperation($currentOp, $currentFields, $currentSection);
                }
                $table = $m[2];
                if (in_array($table, self::SKIP_TABLES, true)) {
                    $currentOp = null;
                    $currentSection = null;
                    $currentFields = [];
                    continue;
                }
                $currentOp = ['type' => 'insert', 'table' => $table, 'set' => [], 'where' => []];
                $currentSection = null;
                $currentFields = [];
                continue;
            }

            // Detect UPDATE
            if (preg_match('/^### UPDATE `([^`]+)`\.`([^`]+)`/', $line, $m)) {
                if ($currentOp !== null) {
                    $operations[] = $this->finalizeOperation($currentOp, $currentFields, $currentSection);
                }
                $table = $m[2];
                if (in_array($table, self::SKIP_TABLES, true)) {
                    $currentOp = null;
                    $currentSection = null;
                    $currentFields = [];
                    continue;
                }
                $currentOp = ['type' => 'update', 'table' => $table, 'set' => [], 'where' => []];
                $currentSection = null;
                $currentFields = [];
                continue;
            }

            // Detect DELETE
            if (preg_match('/^### DELETE FROM `([^`]+)`\.`([^`]+)`/', $line, $m)) {
                if ($currentOp !== null) {
                    $operations[] = $this->finalizeOperation($currentOp, $currentFields, $currentSection);
                }
                $table = $m[2];
                if (in_array($table, self::SKIP_TABLES, true)) {
                    $currentOp = null;
                    $currentSection = null;
                    $currentFields = [];
                    continue;
                }
                $currentOp = ['type' => 'delete', 'table' => $table, 'set' => [], 'where' => []];
                $currentSection = null;
                $currentFields = [];
                continue;
            }

            // Skip if no current operation
            if ($currentOp === null) {
                continue;
            }

            // Detect WHERE section
            if (preg_match('/^### WHERE\s*$/', $line)) {
                // Save any pending fields from previous section
                if ($currentSection !== null && !empty($currentFields)) {
                    $currentOp[$currentSection] = $currentFields;
                    $currentFields = [];
                }
                $currentSection = 'where';
                continue;
            }

            // Detect SET section
            if (preg_match('/^### SET\s*$/', $line)) {
                if ($currentSection !== null && !empty($currentFields)) {
                    $currentOp[$currentSection] = $currentFields;
                    $currentFields = [];
                }
                $currentSection = 'set';
                continue;
            }

            // Parse @N=value lines
            if ($currentSection !== null && preg_match('/^###\s+@(\d+)=(.*)$/', $line, $m)) {
                $position = (int)$m[1];
                $rawValue = $m[2];
                $currentFields[$position] = $this->parseValue($rawValue);
                continue;
            }

            // If we hit a non-### line, the current operation block is over
            if ($currentOp !== null && !str_starts_with($line, '###')) {
                if ($currentSection !== null && !empty($currentFields)) {
                    $currentOp[$currentSection] = $currentFields;
                    $currentFields = [];
                }
                // Only add if the operation has meaningful data
                if (!empty($currentOp['set']) || !empty($currentOp['where'])) {
                    $operations[] = $currentOp;
                }
                $currentOp = null;
                $currentSection = null;
            }
        }

        // Flush last operation
        if ($currentOp !== null) {
            $operations[] = $this->finalizeOperation($currentOp, $currentFields, $currentSection);
        }

        fclose($handle);

        return $operations;
    }

    /**
     * Finalize an operation by saving any remaining fields.
     */
    private function finalizeOperation(array $op, array $fields, ?string $section): array
    {
        if ($section !== null && !empty($fields)) {
            $op[$section] = $fields;
        }
        return $op;
    }

    /**
     * Parse a raw value from the binlog output.
     * Returns the PHP representation: null, string, int, or float.
     */
    private function parseValue(string $raw): mixed
    {
        $raw = trim($raw);

        if ($raw === 'NULL') {
            return null;
        }

        // String value: starts and ends with single quote
        if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            $inner = substr($raw, 1, -1);
            // Unescape: binlog uses \' for literal quotes, \\ for backslash
            $inner = str_replace("\\'", "'", $inner);
            $inner = str_replace('\\\\', '\\', $inner);
            return $inner;
        }

        // Numeric: integer
        if (preg_match('/^-?\d+$/', $raw)) {
            return (int)$raw;
        }

        // Numeric: float/double
        if (is_numeric($raw)) {
            return (float)$raw;
        }

        // Fallback: return as string
        return $raw;
    }

    /**
     * Get column names for a table (cached).
     */
    private function getColumns(string $table): array
    {
        if (!isset($this->columnCache[$table])) {
            $columns = DB::select("SHOW COLUMNS FROM `{$table}`");
            $this->columnCache[$table] = array_map(fn($col) => $col->Field, $columns);
        }
        return $this->columnCache[$table];
    }

    /**
     * Map @N positions (1-based) to column names and apply type conversions.
     */
    private function mapFields(string $table, array $positionValues): array
    {
        $columns = $this->getColumns($table);
        $mapped = [];

        foreach ($positionValues as $position => $value) {
            $colIndex = $position - 1; // @1 => index 0
            if (!isset($columns[$colIndex])) {
                $this->warn("Column position @{$position} out of range for table `{$table}` (has " . count($columns) . " columns). Skipping field.");
                continue;
            }
            $colName = $columns[$colIndex];

            // Convert Unix timestamps to datetime for timestamp-like columns
            if ($value !== null && is_int($value) && $this->isTimestampColumn($colName)) {
                $value = date('Y-m-d H:i:s', $value);
            }

            $mapped[$colName] = $value;
        }

        return $mapped;
    }

    /**
     * Check if a column name looks like it holds a timestamp.
     */
    private function isTimestampColumn(string $colName): bool
    {
        $lower = strtolower($colName);

        // Exact matches
        if (in_array($lower, self::TIMESTAMP_COLUMNS, true)) {
            return true;
        }

        // Pattern match: ends with _at
        if (str_ends_with($lower, '_at')) {
            return true;
        }

        return false;
    }

    /**
     * Execute a single parsed operation.
     */
    private function executeOperation(array $op): void
    {
        $table = $op['table'];
        $type = $op['type'];

        switch ($type) {
            case 'insert':
                $this->executeInsert($table, $op['set']);
                break;
            case 'update':
                $this->executeUpdate($table, $op['where'], $op['set']);
                break;
            case 'delete':
                $this->executeDelete($table, $op['where']);
                break;
        }
    }

    /**
     * Execute an INSERT operation.
     */
    private function executeInsert(string $table, array $setPositions): void
    {
        $fields = $this->mapFields($table, $setPositions);

        if (empty($fields)) {
            return;
        }

        $columns = array_keys($fields);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($fields);

        $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $phList = implode(', ', $placeholders);

        $sql = "INSERT INTO `{$table}` ({$colList}) VALUES ({$phList})";
        DB::insert($sql, $values);
    }

    /**
     * Execute an UPDATE operation.
     */
    private function executeUpdate(string $table, array $wherePositions, array $setPositions): void
    {
        $setFields = $this->mapFields($table, $setPositions);
        $whereFields = $this->mapFields($table, $wherePositions);

        if (empty($setFields) || empty($whereFields)) {
            return;
        }

        // Build SET clause
        $setClauses = [];
        $setValues = [];
        foreach ($setFields as $col => $val) {
            $setClauses[] = "`{$col}` = ?";
            $setValues[] = $val;
        }

        // Build WHERE clause - use only the primary key or first column to identify the row
        // We use all WHERE fields for precise matching
        $whereClauses = [];
        $whereValues = [];
        foreach ($whereFields as $col => $val) {
            if ($val === null) {
                $whereClauses[] = "`{$col}` IS NULL";
            } else {
                $whereClauses[] = "`{$col}` = ?";
                $whereValues[] = $val;
            }
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE " . implode(' AND ', $whereClauses) . " LIMIT 1";
        DB::update($sql, array_merge($setValues, $whereValues));
    }

    /**
     * Execute a DELETE operation.
     */
    private function executeDelete(string $table, array $wherePositions): void
    {
        $whereFields = $this->mapFields($table, $wherePositions);

        if (empty($whereFields)) {
            return;
        }

        $whereClauses = [];
        $whereValues = [];
        foreach ($whereFields as $col => $val) {
            if ($val === null) {
                $whereClauses[] = "`{$col}` IS NULL";
            } else {
                $whereClauses[] = "`{$col}` = ?";
                $whereValues[] = $val;
            }
        }

        $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $whereClauses) . " LIMIT 1";
        DB::delete($sql, $whereValues);
    }
}
