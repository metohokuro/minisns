<?php
// api.php - 汎用API エンドポイント

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

startSecureSession();

$action = $_GET['action'] ?? '';

switch ($action) {

    // ===== フィンガープリントからユーザー検索 =====
    case 'check_fingerprint':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST only']);
            break;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $fp   = trim($body['fingerprint'] ?? '');

        if (empty($fp)) {
            echo json_encode(['user_id' => null]);
            break;
        }

        $user = findUserByFingerprint($fp);
        if ($user) {
            echo json_encode([
                'user_id'      => $user['user_id'],
                'display_name' => $user['display_name'],
            ]);
        } else {
            echo json_encode(['user_id' => null]);
        }
        break;

    // ===== フィンガープリントでの自動ログイン =====
    case 'fingerprint_login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST only']);
            break;
        }

        $userId = trim($_POST['user_id']     ?? '');
        $fp     = trim($_POST['fingerprint'] ?? '');

        if (empty($userId) || empty($fp)) {
            http_response_code(400);
            echo json_encode(['error' => '必須パラメータがありません']);
            break;
        }

        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT id, user_id FROM users WHERE user_id = ? AND fingerprint = ?");
        $stmt->execute([$userId, $fp]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        } else {
            // フィンガープリントが一致しない → ログインページへ
            header('Location: login.php?fp_fail=1');
            exit;
        }
        break;

    // ===== ハッシュタグ補完候補 =====
    case 'hashtag_suggest':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) {
            echo json_encode(['tags' => []]);
            break;
        }
        $pdo  = getPDO();
        $stmt = $pdo->prepare("
            SELECT h.tag
            FROM hashtags h
            WHERE h.tag LIKE ?
            ORDER BY (
                SELECT COUNT(*) FROM post_hashtags ph WHERE ph.tag_id = h.id
            ) DESC
            LIMIT 8
        ");
        $stmt->execute([mb_strtolower($q, 'UTF-8') . '%']);
        $tags = array_column($stmt->fetchAll(), 'tag');
        echo json_encode(['tags' => $tags]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => '不明なアクション']);
        break;
}
