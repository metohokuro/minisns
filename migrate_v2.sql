-- ミニSNS v2 マイグレーション
-- 既存DBに追加で実行してください（schema.sqlとは別ファイル）

USE minisns;

-- ① postsテーブルにリプライ用カラム追加
ALTER TABLE posts
  ADD COLUMN reply_to_id INT NULL DEFAULT NULL AFTER content,
  ADD INDEX idx_reply_to (reply_to_id);

-- ② 通知テーブル
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    to_user_id  VARCHAR(30) NOT NULL,          -- 受け取る人
    from_user_id VARCHAR(30) NOT NULL,          -- 送った人
    type        ENUM('like','comment','follow','reply') NOT NULL,
    post_id     INT NULL DEFAULT NULL,          -- いいね・コメント・リプライの対象投稿
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_to_user  (to_user_id, is_read),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ③ ハッシュタグテーブル
CREATE TABLE IF NOT EXISTS hashtags (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tag        VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ④ 投稿↔ハッシュタグ 中間テーブル
CREATE TABLE IF NOT EXISTS post_hashtags (
    post_id INT NOT NULL,
    tag_id  INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    INDEX idx_tag_id (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
