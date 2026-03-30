<?php
// dm.php - ダイレクトメッセージ（互換性強化版）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
requireLogin();

$currentUser = getCurrentUser();
$myId        = $currentUser['user_id'];
$csrfToken   = getCsrfToken();

// テーブル存在確認（migrate_v3.sql未実行対策）
$pdo = getPDO();
try {
    $pdo->query("SELECT 1 FROM direct_messages LIMIT 1");
} catch (PDOException $e) {
    die('<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">
    <link rel="stylesheet" href="assets/style.css"></head><body>
    <div class="auth-page"><div class="auth-card">
    <div class="flash flash-error">
    <div><strong>⚠️ テーブルが存在しません</strong><br><br>
    phpMyAdmin などで以下のSQLを実行してください：<br><br>
    <code style="display:block;background:var(--bg-input);padding:10px;border-radius:6px;font-size:.78rem;white-space:pre">CREATE TABLE IF NOT EXISTS direct_messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  sender_id   VARCHAR(30) NOT NULL,
  receiver_id VARCHAR(30) NOT NULL,
  content     TEXT NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sender   (sender_id),
  INDEX idx_receiver (receiver_id),
  INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</code>
    </div></div>
    <a href="index.php" class="btn btn-outline" style="margin-top:16px;display:flex;justify-content:center">
    ← トップへ戻る</a>
    </div></div></body></html>');
}

// 相手を指定している場合はスレッド表示モード
$withUser   = trim($_GET['with'] ?? '');
$threadUser = null;

if ($withUser !== '' && $withUser !== $myId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$withUser]);
    $row = $stmt->fetch();
    if ($row) {
        $threadUser = $row;
    } else {
        $withUser = '';
    }
}

// DM送信処理
$sendError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dm_content'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $sendError = 'セキュリティエラーが発生しました。もう一度お試しください。';
    } else {
        $receiverId = trim($_POST['receiver_id'] ?? '');
        $content    = trim($_POST['dm_content']  ?? '');

        if (empty($receiverId) || $receiverId === $myId) {
            $sendError = '送信先が無効です';
        } elseif (empty($content)) {
            $sendError = 'メッセージを入力してください';
        } elseif (mb_strlen($content) > 500) {
            $sendError = 'メッセージは500文字以内で入力してください';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
            $stmt->execute([$receiverId]);
            if (!$stmt->fetch()) {
                $sendError = 'ユーザーが見つかりません';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO direct_messages (sender_id, receiver_id, content) VALUES (?, ?, ?)"
                );
                $stmt->execute([$myId, $receiverId, $content]);
                header('Location: dm.php?with=' . urlencode($receiverId));
                exit;
            }
        }
    }
    $csrfToken = getCsrfToken();
}

// 既読処理
if ($withUser !== '') {
    $pdo->prepare(
        "UPDATE direct_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
    )->execute([$withUser, $myId]);
}

// スレッド一覧（シンプルなUNION版・全MySQL互換）
$stmt = $pdo->prepare("
    SELECT DISTINCT partner_id FROM (
        SELECT receiver_id AS partner_id FROM direct_messages WHERE sender_id   = ?
        UNION
        SELECT sender_id   AS partner_id FROM direct_messages WHERE receiver_id = ?
    ) AS p WHERE partner_id != ?
");
$stmt->execute([$myId, $myId, $myId]);
$partnerIds = array_column($stmt->fetchAll(), 'partner_id');

$threads = [];
foreach ($partnerIds as $pid) {
    $stmtLast = $pdo->prepare("
        SELECT content, created_at FROM direct_messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmtLast->execute([$myId, $pid, $pid, $myId]);
    $last = $stmtLast->fetch();

    $stmtUnread = $pdo->prepare(
        "SELECT COUNT(*) FROM direct_messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
    );
    $stmtUnread->execute([$pid, $myId]);

    $stmtUser = $pdo->prepare("SELECT display_name, avatar_color FROM users WHERE user_id = ?");
    $stmtUser->execute([$pid]);
    $pUser = $stmtUser->fetch();

    $threads[] = [
        'partner_id'   => $pid,
        'display_name' => $pUser['display_name'] ?? $pid,
        'avatar_color' => $pUser['avatar_color']  ?? '#6C63FF',
        'last_content' => $last['content']    ?? '',
        'last_at'      => $last['created_at'] ?? '',
        'unread_count' => (int)$stmtUnread->fetchColumn(),
    ];
}
usort($threads, function($a, $b) { return strcmp($b['last_at'], $a['last_at']); });

// スレッドのメッセージ一覧
$messages = [];
if ($withUser !== '') {
    $stmt = $pdo->prepare("
        SELECT dm.id, dm.sender_id, dm.receiver_id, dm.content, dm.is_read, dm.created_at,
               u.display_name, u.avatar_color
        FROM direct_messages dm
        INNER JOIN users u ON dm.sender_id = u.user_id
        WHERE (dm.sender_id = ? AND dm.receiver_id = ?) OR (dm.sender_id = ? AND dm.receiver_id = ?)
        ORDER BY dm.created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$myId, $withUser, $withUser, $myId]);
    $messages = $stmt->fetchAll();
}

// 未読DM数
$stmtBadge = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0");
$stmtBadge->execute([$myId]);
$unreadDmCount = (int)$stmtBadge->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title>DM - ミニSNS</title>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<link rel="stylesheet" href="assets/style.css">
</head>
<body data-logged-in="1">

<div class="app-container">

  <aside class="left-sidebar">
    <div class="logo"><div class="logo-icon">✦</div> ミニSNS</div>
    <a class="nav-item" href="index.php"><span class="nav-icon">🏠</span> ホーム</a>
    <a class="nav-item" href="notifications.php"><span class="nav-icon">🔔</span> 通知</a>
    <a class="nav-item active" href="dm.php" style="position:relative">
      <span class="nav-icon">✉️</span> DM
      <?php if ($unreadDmCount > 0): ?>
      <span class="notif-badge"><?= $unreadDmCount > 99 ? '99+' : $unreadDmCount ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-item" href="hashtag.php"><span class="nav-icon">#</span> タグ検索</a>
    <a class="nav-item" href="profile.php?id=<?= e($myId) ?>"><span class="nav-icon">👤</span> プロフィール</a>
    <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--border)">
      <a href="logout.php" class="nav-item" style="color:var(--danger)"><span class="nav-icon">🚪</span> ログアウト</a>
    </div>
  </aside>

  <main style="padding:0">
    <div class="dm-layout">

      <div class="dm-threads <?= $withUser ? 'dm-threads--hidden-sp' : '' ?>">
        <div class="dm-panel-header">✉️ ダイレクトメッセージ</div>

        <?php if (empty($threads)): ?>
        <div class="empty-state" style="padding:32px 16px">
          <span class="empty-icon">✉️</span>
          <div class="empty-title">DMがまだありません</div>
          <div class="empty-text">プロフィールページの「✉️ DM」から送れます</div>
        </div>
        <?php else: ?>
        <?php foreach ($threads as $th):
          $thInitial = getAvatarInitial($th['display_name'], $th['partner_id']);
        ?>
        <a class="dm-thread-item <?= $withUser === $th['partner_id'] ? 'active' : '' ?>"
           href="dm.php?with=<?= e($th['partner_id']) ?>">
          <div class="avatar avatar-sm" style="background:<?= e($th['avatar_color']) ?>"><?= e($thInitial) ?></div>
          <div class="dm-thread-info">
            <div class="dm-thread-name">
              <?= e($th['display_name'] ?: $th['partner_id']) ?>
              <span class="dm-thread-handle">@<?= e($th['partner_id']) ?></span>
            </div>
            <div class="dm-thread-preview"><?= e(mb_substr($th['last_content'], 0, 40, 'UTF-8')) ?></div>
          </div>
          <div class="dm-thread-meta">
            <?php if ($th['last_at']): ?><div class="dm-thread-time"><?= timeAgo($th['last_at']) ?></div><?php endif; ?>
            <?php if ($th['unread_count'] > 0): ?><span class="notif-badge"><?= $th['unread_count'] ?></span><?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="dm-chat <?= $withUser ? '' : 'dm-chat--empty' ?>">

        <?php if (!$withUser): ?>
        <div class="dm-empty-chat">
          <span style="font-size:2.5rem">✉️</span>
          <div style="font-weight:600;margin-top:12px;color:var(--text)">スレッドを選んでください</div>
          <div style="font-size:.85rem;color:var(--text-muted);margin-top:6px">プロフィールページの「✉️ DM」から新しいDMを送れます</div>
        </div>

        <?php else: ?>

        <div class="dm-chat-header">
          <a href="dm.php" class="dm-back-btn">←</a>
          <a href="profile.php?id=<?= e($withUser) ?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;flex:1">
            <div class="avatar avatar-sm" style="background:<?= e($threadUser['avatar_color'] ?? '#6C63FF') ?>">
              <?= e(getAvatarInitial($threadUser['display_name'] ?? '', $withUser)) ?>
            </div>
            <div>
              <div style="font-weight:700;font-size:.93rem;color:var(--text)"><?= e($threadUser['display_name'] ?: $withUser) ?></div>
              <div style="font-size:.75rem;color:var(--text-muted)">@<?= e($withUser) ?></div>
            </div>
          </a>
        </div>

        <div class="dm-messages" id="dm-messages">
          <?php if (empty($messages)): ?>
          <div style="text-align:center;padding:32px;color:var(--text-muted);font-size:.88rem">
            最初のメッセージを送ってみましょう
          </div>
          <?php endif; ?>

          <?php
          $prevDate = '';
          foreach ($messages as $msg):
            $isMine  = ($msg['sender_id'] === $myId);
            $msgDate = date('Y/m/d', strtotime($msg['created_at']));
          ?>
          <?php if ($msgDate !== $prevDate): $prevDate = $msgDate; ?>
          <div class="dm-date-divider"><?= e($msgDate) ?></div>
          <?php endif; ?>

          <div class="dm-msg-row <?= $isMine ? 'dm-msg-row--mine' : '' ?>">
            <?php if (!$isMine): ?>
            <div class="avatar avatar-sm" style="background:<?= e($msg['avatar_color'] ?? '#6C63FF') ?>">
              <?= e(getAvatarInitial($msg['display_name'] ?? '', $msg['sender_id'])) ?>
            </div>
            <?php endif; ?>
            <div class="dm-bubble <?= $isMine ? 'dm-bubble--mine' : 'dm-bubble--theirs' ?>">
              <?= nl2br(e($msg['content'])) ?>
              <span class="dm-bubble-time"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if ($sendError): ?>
        <div class="flash flash-error" style="margin:0 16px 8px;border-radius:8px">⚠️ <?= e($sendError) ?></div>
        <?php endif; ?>

        <form method="POST" action="dm.php?with=<?= e($withUser) ?>" class="dm-input-form" id="dm-form">
          <input type="hidden" name="csrf_token"  value="<?= e($csrfToken) ?>">
          <input type="hidden" name="receiver_id" value="<?= e($withUser) ?>">
          <textarea class="dm-input" name="dm_content" id="dm-input"
                    placeholder="メッセージを入力... (Ctrl+Enter で送信)"
                    maxlength="500" rows="1" required></textarea>
          <button type="submit" class="btn btn-primary dm-send-btn">➤</button>
        </form>

        <?php endif; ?>
      </div>

    </div>
  </main>

  <aside class="right-sidebar">
    <div class="widget">
      <div class="widget-title">ユーザーIDで開く</div>
      <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:10px;line-height:1.7">
        相手のユーザーIDを直接入力してDMを開けます
      </p>
      <form method="GET" action="dm.php" style="display:flex;gap:6px">
        <input class="form-input" type="text" name="with" placeholder="user_id" style="flex:1">
        <button type="submit" class="btn btn-primary btn-sm">開く</button>
      </form>
    </div>
  </aside>

</div>

<nav class="bottom-nav">
  <a href="index.php"         class="bottom-nav-item"><span class="nav-icon">🏠</span>ホーム</a>
  <a href="notifications.php" class="bottom-nav-item"><span class="nav-icon">🔔</span>通知</a>
  <a href="dm.php"            class="bottom-nav-item active"><span class="nav-icon">✉️</span>DM</a>
  <a href="profile.php?id=<?= e($myId) ?>" class="bottom-nav-item"><span class="nav-icon">👤</span>プロフィール</a>
</nav>

<script src="assets/script.js"></script>
<script>
(function() {
  var msgBox = document.getElementById('dm-messages');
  if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;
  var input = document.getElementById('dm-input');
  var form  = document.getElementById('dm-form');
  if (input && form) {
    input.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); form.submit(); }
    });
    input.addEventListener('input', function() {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });
  }
})();
</script>
</body>
</html>
