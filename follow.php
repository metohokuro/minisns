<?php
// follow.php - フォロー/アンフォロー API（JSON）

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

$targetId = trim($_POST['target_id'] ?? '');
$myId     = getCurrentUserId();

if (empty($targetId)) {
    http_response_code(400);
    echo json_encode(['error' => '対象ユーザーIDが必要です']);
    exit;
}

if ($targetId === $myId) {
    http_response_code(400);
    echo json_encode(['error' => '自分自身をフォローすることはできません']);
    exit;
}

$pdo = getPDO();

// 対象ユーザー存在確認
$stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
$stmt->execute([$targetId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'ユーザーが見つかりません']);
    exit;
}

// フォロー状態確認
$stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
$stmt->execute([$myId, $targetId]);
$existing = $stmt->fetch();

if ($existing) {
    // アンフォロー
    $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")
        ->execute([$myId, $targetId]);
    $following = false;
} else {
    // フォロー
    $pdo->prepare("INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)")
        ->execute([$myId, $targetId]);
    $following = true;
    // フォロー通知
    createNotification($targetId, $myId, 'follow');
}

echo json_encode([
    'success'   => true,
    'following' => $following,
]);
