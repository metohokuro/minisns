<?php
// includes/db.php - データベース接続

define('DB_HOST', 'localhost');
define('DB_NAME', 'minisns');
define('DB_USER', 'your_db_user');      // ← 変更してください
define('DB_PASS', 'your_db_password');  // ← 変更してください
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // 本番環境では詳細なエラーを表示しないこと
            error_log('DB接続失敗: ' . $e->getMessage());
            die(json_encode(['error' => 'データベース接続に失敗しました']));
        }
    }
    return $pdo;
}
