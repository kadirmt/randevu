<?php

namespace App\Core;

use PDO;
use PDOException;

/** PDO/pgsql tekil bağlantı. Her bağlantıda TZ Europe/Istanbul. */
class Database
{
    private static ?PDO $pdo = null;

    public static function init(array $config): void
    {
        if (self::$pdo !== null) {
            return;
        }
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s',
            $config['host'], $config['port'], $config['dbname']);
        try {
            self::$pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec("SET TIME ZONE 'Europe/Istanbul'");
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('[randevu] DB connect: ' . $e->getMessage());
            exit('Database connection error.');
        }
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Database not initialized.');
        }
        return self::$pdo;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** INSERT ... RETURNING id kullanılırsa fetch ile al; yoksa lastInsertId. */
    public static function insertReturningId(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
