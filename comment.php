<?php
// comment.php - コメント API（JSON）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

startSecureSession();

$action = $_REQUEST['action'] ?? '';
$postId = (int)($_REQUEST['post_id'] ?? 0);

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => '無効なpost_id']);
    exit;
}

$pdo = getPDO();

// ===== GETコメント一覧 =====
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.user_id, c.content, c.created_at,
            u.display_name, u.avatar_color
        FROM comments c
        INNER JOIN users u ON c.user_id = u.user_id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
        LIMIT 30
    ");
    $stmt->execute([$postId]);
    $rows = $stmt->fetchAll();

    $comments = array_map(function($r) {
        return [
            'id'           => (int)$r['id'],
            'user_id'      => $r['user_id'],
            'display_name' => $r['display_name'],
            'content'      => $r['content'],
            'created_at'   => $r['created_at'],
            'avatar_color' => $r['avatar_color'] ?? '#6C63FF',
            'initial'      => getAvatarInitial($r['display_name'] ?? '', $r['user_id']),
        ];
    }, $rows);

    echo json_encode(['success' => true, 'comments' => $comments]);
    exit;
}

// ===== POSTコメント追加 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'post') {
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

    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        echo json_encode(['error' => 'コメント内容を入力してください']);
        exit;
    }

    if (mb_strlen($content) > 200) {
        echo json_encode(['error' => 'コメントは200文字以内で入力してください']);
        exit;
    }

    // 投稿存在確認
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => '投稿が見つかりません']);
        exit;
    }

    $userId = getCurrentUserId();
    $stmt   = $pdo->prepare(
        "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)"
    );
    $stmt->execute([$postId, $userId, $content]);
    // 投稿者に通知
    $stmtOwner = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmtOwner->execute([$postId]);
    $owner = $stmtOwner->fetch();
    if ($owner) createNotification($owner['user_id'], $userId, 'comment', $postId);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => '無効なリクエスト']);
