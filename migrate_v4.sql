-- ミニSNS v4 マイグレーション
-- v3のmigrate_v3.sqlまで実行済みの場合、このファイルを追加で実行してください

USE minisns;

-- usersテーブルにBANフラグ追加
ALTER TABLE users
  ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0 AFTER bio,
  ADD INDEX idx_banned (is_banned);
