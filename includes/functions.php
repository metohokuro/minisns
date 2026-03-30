<?php
// includes/functions.php - ユーティリティ関数

require_once __DIR__ . '/db.php';

/**
 * XSS対策済みエスケープ
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 改行をbrタグに変換（XSS対策付き）
 */
function nl2brSafe(string $str): string {
    return nl2br(e($str));
}

/**
 * 相対時刻表示（例: 3分前）
 */
function timeAgo(string $datetime): string {
    $now  = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
    $past = new DateTimeImmutable($datetime, new DateTimeZone('Asia/Tokyo'));
    $diff = $now->getTimestamp() - $past->getTimestamp();

    if ($diff < 60)        return 'たった今';
    if ($diff < 3600)      return (int)($diff / 60) . '分前';
    if ($diff < 86400)     return (int)($diff / 3600) . '時間前';
    if ($diff < 86400 * 7) return (int)($diff / 86400) . '日前';
    return $past->format('Y/m/d');
}

/**
 * ユーザー情報取得
 */
function getUserById(string $userId): ?array {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * フォロー済みチェック
 */
function isFollowing(string $followerId, string $followingId): bool {
    $pdo  = getPDO();
    $stmt = $pdo->prepare(
        "SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?"
    );
    $stmt->execute([$followerId, $followingId]);
    return (bool)$stmt->fetch();
}

/**
 * フォロワー数取得
 */
function getFollowerCount(string $userId): int {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * フォロー中の数取得
 */
function getFollowingCount(string $userId): int {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * 投稿数取得
 */
function getPostCount(string $userId): int {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * いいね済みチェック
 */
function isLiked(int $postId, string $userId): bool {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * いいね数取得
 */
function getLikeCount(int $postId): int {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    return (int)$stmt->fetchColumn();
}

/**
 * コメント数取得
 */
function getCommentCount(int $postId): int {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
    $stmt->execute([$postId]);
    return (int)$stmt->fetchColumn();
}

/**
 * アバターカラー生成（user_idから一意の色）
 */
function generateAvatarColor(string $userId): string {
    $colors = [
        '#FF6B6B', '#FF8E53', '#FFCF4B', '#6BCB77',
        '#4ECDC4', '#45B7D1', '#6C63FF', '#F72585',
        '#B5179E', '#7209B7', '#3A0CA3', '#4361EE',
    ];
    $index = abs(crc32($userId)) % count($colors);
    return $colors[$index];
}

/**
 * アバターイニシャル取得
 */
function getAvatarInitial(string $displayName, string $userId): string {
    $name = !empty($displayName) ? $displayName : $userId;
    // マルチバイト対応
    $char = mb_substr($name, 0, 1, 'UTF-8');
    return strtoupper($char);
}

// ============================================================
//  通知
// ============================================================

/**
 * 通知を作成（自分への通知・重複はスキップ）
 */
function createNotification(
    string $toUserId,
    string $fromUserId,
    string $type,
    ?int   $postId = null
): void {
    // 自分自身への通知は作らない
    if ($toUserId === $fromUserId) return;

    $pdo  = getPDO();

    // 同じ通知が短時間で重複しないようにチェック（いいねの連打対策）
    $stmt = $pdo->prepare("
        SELECT id FROM notifications
        WHERE to_user_id = ? AND from_user_id = ? AND type = ?
          AND (post_id = ? OR (post_id IS NULL AND ? IS NULL))
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$toUserId, $fromUserId, $type, $postId, $postId]);
    if ($stmt->fetch()) return;

    $stmt = $pdo->prepare("
        INSERT INTO notifications (to_user_id, from_user_id, type, post_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$toUserId, $fromUserId, $type, $postId]);
}

/**
 * 未読通知数取得
 */
function getUnreadNotificationCount(string $userId): int {
    $pdo  = getPDO();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications WHERE to_user_id = ? AND is_read = 0"
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// ============================================================
//  ハッシュタグ
// ============================================================

/**
 * 本文からハッシュタグを抽出して保存し、紐付ける
 */
function saveHashtags(int $postId, string $content): void {
    preg_match_all('/#([\p{L}\p{N}_ぁ-ん一-龠ァ-ン]{1,50})/u', $content, $matches);
    $tags = array_unique($matches[1] ?? []);
    if (empty($tags)) return;

    $pdo = getPDO();
    foreach ($tags as $tag) {
        $tag = mb_strtolower($tag, 'UTF-8');

        // hashtags テーブルに INSERT OR IGNORE
        $pdo->prepare("INSERT IGNORE INTO hashtags (tag) VALUES (?)")->execute([$tag]);

        // tag_id 取得
        $stmt = $pdo->prepare("SELECT id FROM hashtags WHERE tag = ?");
        $stmt->execute([$tag]);
        $tagId = $stmt->fetchColumn();

        // 中間テーブル
        $pdo->prepare("INSERT IGNORE INTO post_hashtags (post_id, tag_id) VALUES (?, ?)")
            ->execute([$postId, $tagId]);
    }
}

/**
 * 投稿本文内の #タグ をリンクに変換（XSS対策済み）
 */
function linkifyHashtags(string $content): string {
    $escaped = e($content);
    // エスケープ済みの文字列に対してリンク化
    return preg_replace_callback(
        '/#([\p{L}\p{N}_ぁ-ん一-龠ァ-ン]{1,50})/u',
        fn($m) => '<a href="hashtag.php?tag=' . urlencode($m[1]) . '" class="hashtag-link">#' . e($m[1]) . '</a>',
        $escaped
    );
}

/**
 * 投稿本文を整形（改行変換 + ハッシュタグリンク化）
 */
function formatContent(string $content): string {
    return nl2br(linkifyHashtags($content));
}
