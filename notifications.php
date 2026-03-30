<?php
// notifications.php - 通知一覧

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
requireLogin();

$currentUser = getCurrentUser();
$csrfToken   = getCsrfToken();
$pdo         = getPDO();

// 全通知を既読にする
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE to_user_id = ?")
    ->execute([$currentUser['user_id']]);

// 通知一覧取得（最新50件）
$stmt = $pdo->prepare("
    SELECT
        n.id, n.type, n.post_id, n.is_read, n.created_at,
        n.from_user_id,
        u.display_name AS from_display_name,
        u.avatar_color,
        p.content      AS post_content
    FROM notifications n
    INNER JOIN users u ON n.from_user_id = u.user_id
    LEFT  JOIN posts  p ON n.post_id = p.id
    WHERE n.to_user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([$currentUser['user_id']]);
$notifications = $stmt->fetchAll();

$typeLabel = [
    'like'    => '❤️ があなたの投稿にいいねしました',
    'comment' => '💬 があなたの投稿にコメントしました',
    'follow'  => '👤 があなたをフォローしました',
    'reply'   => '↩ があなたの投稿に返信しました',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title>通知 - ミニSNS</title>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<link rel="stylesheet" href="assets/style.css">
</head>
<body data-logged-in="1">

<div class="app-container">

  <!-- 左サイドバー -->
  <aside class="left-sidebar">
    <div class="logo"><div class="logo-icon">✦</div> ミニSNS</div>
    <a class="nav-item" href="index.php"><span class="nav-icon">🏠</span> ホーム</a>
    <a class="nav-item active" href="notifications.php"><span class="nav-icon">🔔</span> 通知</a>
    <a class="nav-item" href="hashtag.php"><span class="nav-icon">#</span> タグ検索</a>
    <a class="nav-item" href="profile.php?id=<?= e($currentUser['user_id']) ?>">
      <span class="nav-icon">👤</span> プロフィール
    </a>
    <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--border)">
      <a href="logout.php" class="nav-item" style="color:var(--danger)">
        <span class="nav-icon">🚪</span> ログアウト
      </a>
    </div>
  </aside>

  <!-- メイン -->
  <main>
    <div class="feed">
      <div class="feed-header">🔔 通知</div>

      <?php if (empty($notifications)): ?>
      <div class="empty-state">
        <span class="empty-icon">🔔</span>
        <div class="empty-title">通知はありません</div>
        <div class="empty-text">いいね・コメント・フォローされると通知が届きます</div>
      </div>
      <?php else: ?>

      <?php foreach ($notifications as $n):
        $initial = getAvatarInitial($n['from_display_name'] ?? '', $n['from_user_id']);
        $color   = $n['avatar_color'] ?? '#6C63FF';
        $label   = $typeLabel[$n['type']] ?? '通知';
      ?>
      <div class="notif-item <?= $n['is_read'] ? '' : 'notif-unread' ?>">
        <a href="profile.php?id=<?= e($n['from_user_id']) ?>">
          <div class="avatar avatar-sm" style="background:<?= e($color) ?>">
            <?= e($initial) ?>
          </div>
        </a>
        <div class="notif-body">
          <span class="notif-text">
            <a href="profile.php?id=<?= e($n['from_user_id']) ?>" class="notif-name">
              <?= e($n['from_display_name'] ?: $n['from_user_id']) ?>
            </a>
            <?php
              // typeラベルからアイコン部分だけ分離して表示
              preg_match('/^(.\s)(.+)$/', $label, $lm);
              echo e($lm[2] ?? $label);
            ?>
          </span>
          <?php if ($n['post_content']): ?>
          <div class="notif-preview">
            "<?= e(mb_substr($n['post_content'], 0, 60)) ?><?= mb_strlen($n['post_content']) > 60 ? '…' : '' ?>"
          </div>
          <?php endif; ?>
          <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
        </div>
        <?php if ($n['post_id']): ?>
        <a href="index.php#post-<?= (int)$n['post_id'] ?>" class="notif-arrow">→</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php endif; ?>
    </div>
  </main>

  <aside class="right-sidebar">
    <div class="widget">
      <div class="widget-title">通知について</div>
      <p style="font-size:.85rem;color:var(--text-muted);line-height:1.8">
        📌 このページを開くと全て既読になります
      </p>
    </div>
  </aside>

</div>

<nav class="bottom-nav">
  <a href="index.php" class="bottom-nav-item"><span class="nav-icon">🏠</span>ホーム</a>
  <a href="notifications.php" class="bottom-nav-item active"><span class="nav-icon">🔔</span>通知</a>
  <a href="profile.php?id=<?= e($currentUser['user_id']) ?>" class="bottom-nav-item">
    <span class="nav-icon">👤</span>プロフィール
  </a>
</nav>

<script src="assets/script.js"></script>
</body>
</html>
