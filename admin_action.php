<?php
// admin_action.php - 管理者専用アクションAPI

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

startSecureSession();
requireAdmin(); // ★ yamachin以外はここで403

$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRFトークンが無効です']);
    exit;
}

$action = $_POST['action'] ?? '';
$pdo    = getPDO();

switch ($action) {

    // ===== コメント削除 =====
    case 'delete_comment':
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if ($commentId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => '無効なcomment_id']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'コメントが見つかりません']);
            exit;
        }
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$commentId]);
        echo json_encode(['success' => true]);
        break;

    // ===== アカウント削除 =====
    case 'delete_user':
        $targetId = trim($_POST['target_user_id'] ?? '');
        if (empty($targetId)) {
            http_response_code(400);
            echo json_encode(['error' => 'ユーザーIDが必要です']);
            exit;
        }
        // 管理者自身は削除できない
        if ($targetId === 'yamachin') {
            http_response_code(400);
            echo json_encode(['error' => '管理者アカウントは削除できません']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
        $stmt->execute([$targetId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'ユーザーが見つかりません']);
            exit;
        }

        // 関連データを全削除（外部キー制約がない前提）
        // 投稿のいいね・コメント・ハッシュタグ・通知を先に消す
        $stmt = $pdo->prepare("SELECT id FROM posts WHERE user_id = ?");
        $stmt->execute([$targetId]);
        $postIds = array_column($stmt->fetchAll(), 'id');

        foreach ($postIds as $pid) {
            $pdo->prepare("DELETE FROM likes         WHERE post_id = ?")->execute([$pid]);
            $pdo->prepare("DELETE FROM comments      WHERE post_id = ?")->execute([$pid]);
            $pdo->prepare("DELETE FROM post_hashtags WHERE post_id = ?")->execute([$pid]);
            $pdo->prepare("DELETE FROM notifications WHERE post_id = ?")->execute([$pid]);
        }

        // ユーザー自身のデータ
        $pdo->prepare("DELETE FROM posts          WHERE user_id = ?")->execute([$targetId]);
        $pdo->prepare("DELETE FROM likes          WHERE user_id = ?")->execute([$targetId]);
        $pdo->prepare("DELETE FROM comments       WHERE user_id = ?")->execute([$targetId]);
        $pdo->prepare("DELETE FROM follows        WHERE follower_id = ? OR following_id = ?")->execute([$targetId, $targetId]);
        $pdo->prepare("DELETE FROM notifications  WHERE to_user_id = ? OR from_user_id = ?")->execute([$targetId, $targetId]);
        $pdo->prepare("DELETE FROM direct_messages WHERE sender_id = ? OR receiver_id = ?")->execute([$targetId, $targetId]);
        // ユーザー本体
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$targetId]);

        echo json_encode(['success' => true]);
        break;

    // ===== アカウント停止（banフラグ） =====
    case 'ban_user':
        $targetId = trim($_POST['target_user_id'] ?? '');
        if (empty($targetId) || $targetId === 'yamachin') {
            http_response_code(400);
            echo json_encode(['error' => '無効な操作です']);
            exit;
        }
        // is_bannedカラムがあれば更新（なければエラーを無視）
        try {
            $pdo->prepare("UPDATE users SET is_banned = 1 WHERE user_id = ?")->execute([$targetId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            // カラムがない場合はそのまま削除を勧める
            echo json_encode(['success' => false, 'error' => 'is_bannedカラムが存在しません。migrate_v4.sqlを実行してください。']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => '不明なアクション']);
        break;
}
