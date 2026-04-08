<?php
/**
 * ============================================================
 * DENTAL APP — DATABASE CLASS
 * Handles all MySQL operations.
 * Usage:
 *   $db = new Database();
 *   $users = $db->select('users', ['status' => 'active']);
 *   $id    = $db->insert('users', ['name' => 'Ali', 'email' => 'ali@example.com']);
 *   $db->update('users', ['name' => 'Ali Updated'], ['id' => 5]);
 *   $db->delete('users', ['id' => 5]);
 * ============================================================
 */

class Database
{
    /* ---- Private configuration — change these values ---- */
    private string $host     = 'localhost';
    private string $dbname   = 'dental';
    private string $username = 'root';
    private string $password = '';
    private string $charset  = 'utf8mb4';
    private int    $port     = 3306;

    // private string $host     = 'localhost';
    // private string $dbname   = 'ouraeohl_dental_management';
    // private string $username = 'ouraeohl_dental_manager';
    // private string $password = 'SabaDev@2001';
    // private string $charset  = 'utf8mb4';
    // private int    $port     = 3306;
    /* ----------------------------------------------------- */

    private PDO        $pdo;
    private ?PDOStatement $lastStmt = null;
    private array      $queryLog    = [];
    private bool       $logQueries  = false;   // set true in dev

    /* ================================================================
       CONSTRUCTOR — establishes PDO connection
    ================================================================ */
    public function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->dbname,
            $this->charset
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Never leak credentials in production
            error_log('[DB] Connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please try again later.');
        }
    }

    /* ================================================================
       SELECT
       Returns array of rows, or empty array.

       $db->select('users')
       $db->select('users', ['status' => 'active', 'role' => 'admin'])
       $db->select('users', ['id' => 3], 'id, name, email')
       $db->select('users', [], '*', 'created_at DESC', 10, 0)
    ================================================================ */
    public function select(
        string $table,
        array  $where   = [],
        string $columns = '*',
        string $orderBy = '',
        ?int   $limit   = null,
        int    $offset  = 0
    ): array {
        [$whereSql, $bindings] = $this->buildWhere($where);

        $sql  = "SELECT {$columns} FROM `{$table}`";
        $sql .= $whereSql;
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        if ($limit !== null) $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->run($sql, $bindings);
        return $stmt->fetchAll();
    }

    /* ================================================================
       SELECT ONE ROW
    ================================================================ */
    public function selectOne(
        string $table,
        array  $where   = [],
        string $columns = '*'
    ): ?array {
        $rows = $this->select($table, $where, $columns, '', 1);
        return $rows[0] ?? null;
    }

    /* ================================================================
       RAW QUERY — for JOINs, complex queries
       $db->query("SELECT u.*, r.name as role FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.id=?", [5])
    ================================================================ */
    public function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->run($sql, $bindings);
        return $stmt->fetchAll();
    }

    /* ================================================================
       RAW QUERY SINGLE ROW
    ================================================================ */
    public function queryOne(string $sql, array $bindings = []): ?array
    {
        $rows = $this->query($sql, $bindings);
        return $rows[0] ?? null;
    }

    /* ================================================================
       INSERT
       Returns last inserted ID.
    ================================================================ */
    public function insert(string $table, array $data): int|string
    {
        if (empty($data)) throw new InvalidArgumentException('Insert data cannot be empty.');

        $columns  = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql      = "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$placeholders})";
        $this->run($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    /* ================================================================
       INSERT OR UPDATE (UPSERT)
    ================================================================ */
    public function upsert(string $table, array $data, array $updateColumns = []): int|string
    {
        if (empty($data)) throw new InvalidArgumentException('Upsert data cannot be empty.');

        $columns      = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        // If no specific columns to update, update all
        $updateCols = $updateColumns ?: array_keys($data);
        $updateParts = array_map(fn($col) => "`{$col}` = VALUES(`{$col}`)", $updateCols);
        $updateSql   = implode(', ', $updateParts);

        $sql = "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$placeholders}) "
             . "ON DUPLICATE KEY UPDATE {$updateSql}";

        $this->run($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    /* ================================================================
       UPDATE
       Returns number of affected rows.

       $db->update('users', ['name' => 'New Name'], ['id' => 5])
    ================================================================ */
    public function update(string $table, array $data, array $where): int
    {
        if (empty($data))  throw new InvalidArgumentException('Update data cannot be empty.');
        if (empty($where)) throw new InvalidArgumentException('Update requires a WHERE clause for safety.');

        $setParts = array_map(fn($col) => "`{$col}` = ?", array_keys($data));
        $setSql   = implode(', ', $setParts);

        [$whereSql, $whereBindings] = $this->buildWhere($where);
        $sql = "UPDATE `{$table}` SET {$setSql}" . $whereSql;

        $bindings = array_merge(array_values($data), $whereBindings);
        $stmt = $this->run($sql, $bindings);
        return $stmt->rowCount();
    }

    /* ================================================================
       DELETE
       Returns number of affected rows.
    ================================================================ */
    public function delete(string $table, array $where): int
    {
        if (empty($where)) throw new InvalidArgumentException('Delete requires a WHERE clause for safety.');

        [$whereSql, $bindings] = $this->buildWhere($where);
        $sql  = "DELETE FROM `{$table}`" . $whereSql;
        $stmt = $this->run($sql, $bindings);
        return $stmt->rowCount();
    }

    /* ================================================================
       SOFT DELETE — sets deleted_at timestamp instead of deleting
    ================================================================ */
    public function softDelete(string $table, array $where): int
    {
        return $this->update($table, ['deleted_at' => date('Y-m-d H:i:s')], $where);
    }

    /* ================================================================
       COUNT
    ================================================================ */
    public function count(string $table, array $where = []): int
    {
        [$whereSql, $bindings] = $this->buildWhere($where);
        $sql  = "SELECT COUNT(*) as cnt FROM `{$table}`" . $whereSql;
        $row  = $this->queryOne($sql, $bindings);
        return (int)($row['cnt'] ?? 0);
    }

    /* ================================================================
       EXISTS
    ================================================================ */
    public function exists(string $table, array $where): bool
    {
        return $this->count($table, $where) > 0;
    }

    /* ================================================================
       TRANSACTION HELPERS
    ================================================================ */
    public function beginTransaction(): void  { $this->pdo->beginTransaction(); }
    public function commit(): void            { $this->pdo->commit(); }
    public function rollback(): void          { $this->pdo->rollBack(); }

    /**
     * Wraps a callable in a transaction; auto-commits or rolls back.
     * Usage: $db->transaction(function() use ($db) { ... });
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /* ================================================================
       PAGINATION HELPER
       Returns: [ 'data' => [...], 'total' => int, 'pages' => int, 'current' => int ]
    ================================================================ */
    public function paginate(
        string $table,
        array  $where   = [],
        int    $page    = 1,
        int    $perPage = 20,
        string $columns = '*',
        string $orderBy = 'id DESC'
    ): array {
        $total  = $this->count($table, $where);
        $pages  = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $data   = $this->select($table, $where, $columns, $orderBy, $perPage, $offset);

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => $pages,
            'current' => $page,
            'per_page'=> $perPage,
        ];
    }

    /* ================================================================
       LAST INSERT ID
    ================================================================ */
    public function lastId(): int|string
    {
        return $this->pdo->lastInsertId();
    }

    /* ================================================================
       PRIVATE HELPERS
    ================================================================ */

    /**
     * Builds WHERE clause from array.
     * Supports: ['col' => val] and ['col' => ['op' => '>', 'val' => 5]]
     */
    private function buildWhere(array $where): array
    {
        if (empty($where)) return ['', []];

        $parts    = [];
        $bindings = [];

        foreach ($where as $col => $val) {
            if (is_array($val) && isset($val['op'], $val['val'])) {
                $parts[]    = "`{$col}` {$val['op']} ?";
                $bindings[] = $val['val'];
            } elseif (is_array($val)) {
                // IN clause
                $placeholders = implode(', ', array_fill(0, count($val), '?'));
                $parts[]      = "`{$col}` IN ({$placeholders})";
                $bindings     = array_merge($bindings, $val);
            } elseif ($val === null) {
                $parts[] = "`{$col}` IS NULL";
            } else {
                $parts[]    = "`{$col}` = ?";
                $bindings[] = $val;
            }
        }

        return [' WHERE ' . implode(' AND ', $parts), $bindings];
    }

    /**
     * Prepares and executes a statement with error handling.
     */
    private function run(string $sql, array $bindings = []): PDOStatement
    {
        if ($this->logQueries) {
            $this->queryLog[] = ['sql' => $sql, 'bindings' => $bindings, 'time' => microtime(true)];
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            $this->lastStmt = $stmt;
            return $stmt;
        } catch (PDOException $e) {
            error_log('[DB] Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new RuntimeException('A database error occurred. Please try again.');
        }
    }

    public function getQueryLog(): array { return $this->queryLog; }
    public function enableQueryLog(): void { $this->logQueries = true; }
}
