<?php
// delete_post.php - 投稿削除 API（自分の投稿 or 管理者）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

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
$myId   = getCurrentUserId();

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => '無効なpost_id']);
    exit;
}

$pdo = getPDO();

// ★ 投稿者が自分かチェック（SQLとPHP両方で確認）
$stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo json_encode(['error' => '投稿が見つかりません']);
    exit;
}

if ($post['user_id'] !== $myId && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => '削除権限がありません']);
    exit;
}

// 関連データを一緒に削除
$pdo->prepare("DELETE FROM likes         WHERE post_id = ?")->execute([$postId]);
$pdo->prepare("DELETE FROM comments      WHERE post_id = ?")->execute([$postId]);
$pdo->prepare("DELETE FROM post_hashtags WHERE post_id = ?")->execute([$postId]);
$pdo->prepare("DELETE FROM notifications WHERE post_id = ?")->execute([$postId]);
// 管理者は user_id 条件なしで削除、一般ユーザーは自分の投稿のみ
if (isAdmin()) {
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$postId]);
} else {
    $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?")->execute([$postId, $myId]);
}

echo json_encode(['success' => true]);
