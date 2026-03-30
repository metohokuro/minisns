<?php
// like.php - いいね API（JSON）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

startSecureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'ログインが必要です']);
    exit;
}

// CSRF検証
$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRFトークンが無効です']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => '無効なpost_id']);
    exit;
}

$userId = getCurrentUserId();
$pdo    = getPDO();

// 投稿の存在確認
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
$stmt->execute([$postId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => '投稿が見つかりません']);
    exit;
}

// トグル処理
$stmt = $pdo->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
$stmt->execute([$postId, $userId]);
$existing = $stmt->fetch();

if ($existing) {
    // いいね解除
    $pdo->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
    $liked = false;
} else {
    // いいね追加
    $pdo->prepare("INSERT IGNORE INTO likes (post_id, user_id) VALUES (?, ?)")->execute([$postId, $userId]);
    $liked = true;
    // 投稿者に通知
    $stmtOwner = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmtOwner->execute([$postId]);
    $owner = $stmtOwner->fetch();
    if ($owner) createNotification($owner['user_id'], $userId, 'like', $postId);
}

// 新しいカウント取得
$stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
$stmt->execute([$postId]);
$count = (int)$stmt->fetchColumn();

echo json_encode([
    'success' => true,
    'liked'   => $liked,
    'count'   => $count,
]);
