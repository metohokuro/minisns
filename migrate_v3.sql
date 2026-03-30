-- ミニSNS v3 マイグレーション
-- v2のmigrate_v2.sql実行済みの場合、このファイルも追加で実行してください

USE minisns;

-- DM（ダイレクトメッセージ）テーブル
CREATE TABLE IF NOT EXISTS direct_messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    VARCHAR(30) NOT NULL,
    receiver_id  VARCHAR(30) NOT NULL,
    content      TEXT NOT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_thread   (sender_id, receiver_id),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
