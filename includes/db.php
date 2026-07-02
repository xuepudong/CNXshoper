<?php
/**
 * 数据库连接（PDO 单例）
 */

require_once __DIR__ . '/config.php';

class DB
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // 生产环境不暴露数据库错误细节
                error_log('DB Connection failed: ' . $e->getMessage());
                http_response_code(500);
                exit('服务暂时不可用，请稍后重试。');
            }
        }
        return self::$instance;
    }

    /** 查询多行 */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** 查询单行 */
    public static function queryOne(string $sql, array $params = []): array|false
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** 执行写操作，返回影响行数 */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** 插入并返回自增 ID */
    public static function insert(string $sql, array $params = []): string
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return self::get()->lastInsertId();
    }
}
