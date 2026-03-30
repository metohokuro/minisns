<?php
// index.php - タイムライン（メインページ）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$isLoggedIn  = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$csrfToken   = getCsrfToken();

// 投稿処理
$postError = '';
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $postError = 'CSRFエラーが発生しました。';
    } else {
        $content = trim($_POST['post_content'] ?? '');
        if (empty($content)) {
            $postError = '投稿内容を入力してください';
        } elseif (mb_strlen($content) > 280) {
            $postError = '投稿は280文字以内で入力してください';
        } else {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, reply_to_id) VALUES (?, ?, ?)");
            $replyToId = (int)($_POST['reply_to_id'] ?? 0) ?: null;
            $stmt->execute([$currentUser['user_id'], $content, $replyToId]);
            $newPostId = (int)$pdo->lastInsertId();
            saveHashtags($newPostId, $content);
            if ($replyToId) {
                $stmtOrig = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
                $stmtOrig->execute([$replyToId]);
                $orig = $stmtOrig->fetch();
                if ($orig) createNotification($orig['user_id'], $currentUser['user_id'], 'reply', $replyToId);
            }
            header('Location: index.php');
            exit;
        }
    }
    // トークンを再生成
    $csrfToken = getCsrfToken();
}

// タイムライン取得（全投稿、新着順）
$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.user_id,
        p.content,
        p.created_at,
        p.reply_to_id,
        u.display_name,
        u.avatar_color,
        (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
        rp.user_id   AS reply_to_user_id,
        ru.display_name AS reply_to_display_name
    FROM posts p
    INNER JOIN users u  ON p.user_id = u.user_id
    LEFT  JOIN posts rp ON p.reply_to_id = rp.id
    LEFT  JOIN users ru ON rp.user_id = ru.user_id
    ORDER BY p.created_at DESC
    LIMIT 50
");
$stmt->execute();
$posts = $stmt->fetchAll();

// 未読通知数
$unreadCount   = $isLoggedIn ? getUnreadNotificationCount($currentUser['user_id']) : 0;
// 未読DM数
$unreadDmCount = 0;
if ($isLoggedIn) {
    $stmtDm = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id = ? AND is_read = 0");
    $stmtDm->execute([$currentUser['user_id']]);
    $unreadDmCount = (int)$stmtDm->fetchColumn();
}

// ウィジェット: フォロー候補（未フォロー・ランダム5件）
$suggestions = [];
if ($isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.display_name, u.avatar_color
        FROM users u
        WHERE u.user_id != ?
          AND u.user_id NOT IN (
              SELECT following_id FROM follows WHERE follower_id = ?
          )
        ORDER BY RAND()
        LIMIT 5
    ");
    $stmt->execute([$currentUser['user_id'], $currentUser['user_id']]);
    $suggestions = $stmt->fetchAll();
}

$welcome = isset($_GET['welcome']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title>ミニSNS - タイムライン</title>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<link rel="stylesheet" href="assets/style.css">
<script src="https://fpjscdn.net/v3/ogWCYmBVDHhTWcLJYmq5/iife.min.js"></script>
</head>
<body data-logged-in="<?= $isLoggedIn ? '1' : '0' ?>">

<!-- 自動ログインバナー（未ログイン時） -->
<?php if (!$isLoggedIn): ?>
<div style="padding:12px 16px;max-width:640px;margin:12px auto 0">
  <div class="autologin-banner" id="autologin-banner" style="display:none"></div>
</div>
<?php endif; ?>

<div class="app-container">

  <!-- ===== 左サイドバー ===== -->
  <aside class="left-sidebar">
    <div class="logo">
      <div class="logo-icon">✦</div>
      ミニSNS
    </div>

    <a class="nav-item active" href="index.php">
      <span class="nav-icon">🏠</span> ホーム
    </a>

    <?php if ($isLoggedIn): ?>
    <a class="nav-item" href="notifications.php" style="position:relative">
      <span class="nav-icon">🔔</span> 通知
      <?php if ($unreadCount > 0): ?>
      <span class="notif-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-item" href="hashtag.php">
      <span class="nav-icon">#</span> タグ検索
    </a>
    <a class="nav-item" href="dm.php" style="position:relative">
      <span class="nav-icon">✉️</span> DM
      <?php if ($unreadDmCount > 0): ?>
      <span class="notif-badge"><?= $unreadDmCount > 99 ? '99+' : $unreadDmCount ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-item" href="profile.php?id=<?= e($currentUser['user_id']) ?>">
      <span class="nav-icon">👤</span> プロフィール
    </a>
    <?php endif; ?>

    <?php if ($isLoggedIn): ?>
    <button class="nav-post-btn" onclick="document.getElementById('compose-textarea').focus()">
      ✦ 投稿する
    </button>

    <?php if (isAdmin()): ?>
    <a class="nav-item" href="admin.php" style="color:#FF8E53">
      <span class="nav-icon">⚙️</span> 管理
    </a>
    <?php endif; ?>

    <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--border)">
      <a href="profile.php?id=<?= e($currentUser['user_id']) ?>" class="sidebar-user">
        <div class="avatar avatar-sm"
             style="background:<?= e($currentUser['avatar_color'] ?? '#6C63FF') ?>">
          <?= e(getAvatarInitial($currentUser['display_name'] ?? '', $currentUser['user_id'])) ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= e($currentUser['display_name'] ?? $currentUser['user_id']) ?>
          </div>
          <div style="font-size:.75rem;color:var(--text-muted)">@<?= e($currentUser['user_id']) ?></div>
        </div>
      </a>
      <a href="logout.php" class="nav-item" style="margin-top:4px;color:var(--danger)">
        <span class="nav-icon">🚪</span> ログアウト
      </a>
    </div>

    <?php else: ?>
    <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
      <a href="register.php" class="btn btn-primary">新規登録</a>
      <a href="login.php" class="btn btn-outline">ログイン</a>
    </div>
    <?php endif; ?>
  </aside>

  <!-- ===== メインフィード ===== -->
  <main>
    <?php if ($welcome): ?>
    <div class="flash flash-success" style="margin-bottom:12px">
      🎉 ようこそ！アカウントが作成されました
    </div>
    <?php endif; ?>

    <?php if ($postError): ?>
    <div class="flash flash-error" style="margin-bottom:12px">
      ⚠️ <?= e($postError) ?>
    </div>
    <?php endif; ?>

    <!-- 投稿フォーム -->
    <?php if ($isLoggedIn): ?>
    <div class="compose-box" style="margin-bottom:12px">
      <form method="POST" action="index.php" id="compose-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div class="compose-inner">
          <div class="avatar"
               style="background:<?= e($currentUser['avatar_color'] ?? '#6C63FF') ?>">
            <?= e(getAvatarInitial($currentUser['display_name'] ?? '', $currentUser['user_id'])) ?>
          </div>
          <textarea class="compose-textarea"
                    id="compose-textarea"
                    name="post_content"
                    placeholder="いまどうしてる？"
                    maxlength="280"><?= e($_POST['post_content'] ?? '') ?></textarea>
        </div>
        <div class="compose-footer">
          <span class="char-count" id="char-count">0 / 280</span>
          <button type="submit" class="btn btn-primary btn-sm" id="compose-submit" disabled>
            投稿する
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- フィード -->
    <div class="feed">
      <div class="feed-header">タイムライン</div>

      <?php if (empty($posts)): ?>
      <div class="empty-state">
        <span class="empty-icon">✦</span>
        <div class="empty-title">まだ投稿がありません</div>
        <div class="empty-text">最初の投稿をしてみましょう</div>
      </div>
      <?php else: ?>

      <?php foreach ($posts as $post):
        $liked   = $isLoggedIn ? isLiked($post['id'], $currentUser['user_id']) : false;
        $initial = getAvatarInitial($post['display_name'] ?? '', $post['user_id']);
        $color   = $post['avatar_color'] ?? '#6C63FF';
      ?>
      <div class="post-card" id="post-<?= (int)$post['id'] ?>">

        <?php if ($post['reply_to_id'] && $post['reply_to_user_id']): ?>
        <div class="reply-indicator">
          ↩ <a href="profile.php?id=<?= e($post['reply_to_user_id']) ?>">
            <?= e($post['reply_to_display_name'] ?: $post['reply_to_user_id']) ?>
          </a> への返信
        </div>
        <?php endif; ?>

        <div class="post-header">
          <a href="profile.php?id=<?= e($post['user_id']) ?>">
            <div class="avatar" style="background:<?= e($color) ?>">
              <?= e($initial) ?>
            </div>
          </a>
          <div class="post-meta">
            <div class="post-user">
              <a href="profile.php?id=<?= e($post['user_id']) ?>" class="post-display-name">
                <?= e($post['display_name'] ?: $post['user_id']) ?>
              </a>
              <span class="post-user-id">@<?= e($post['user_id']) ?></span>
              <span class="post-time"><?= timeAgo($post['created_at']) ?></span>
            </div>
          </div>
        </div>

        <div class="post-content"><?= formatContent($post['content']) ?></div>

        <div class="post-actions">
          <?php if ($isLoggedIn): ?>
          <!-- いいね -->
          <button class="post-action-btn <?= $liked ? 'liked' : '' ?>"
                  onclick="Like.toggle(<?= (int)$post['id'] ?>, this)">
            <?= $liked ? '❤️' : '🤍' ?>
            <span class="like-count"><?= (int)$post['like_count'] ?></span>
          </button>
          <!-- コメント -->
          <button class="post-action-btn"
                  onclick="Comments.toggle(<?= (int)$post['id'] ?>)">
            💬 <span data-comment-count="<?= (int)$post['id'] ?>"><?= (int)$post['comment_count'] ?></span>
          </button>
          <!-- リプライ -->
          <button class="post-action-btn"
                  onclick="Reply.open(<?= (int)$post['id'] ?>, '<?= e(addslashes($post['display_name'] ?: $post['user_id'])) ?>')">
            ↩ 返信
          </button>
          <!-- 削除（自分の投稿 or 管理者） -->
          <?php if ($currentUser['user_id'] === $post['user_id'] || isAdmin()): ?>
          <button class="post-action-btn delete-btn"
                  onclick="deletePost(<?= (int)$post['id'] ?>, this)"
                  title="<?= isAdmin() && $currentUser['user_id'] !== $post['user_id'] ? '管理者として削除' : '削除' ?>">
            <?= isAdmin() && $currentUser['user_id'] !== $post['user_id'] ? '⚙️🗑️' : '🗑️' ?>
          </button>
          <?php endif; ?>
          <?php else: ?>
          <span class="post-action-btn">🤍 <?= (int)$post['like_count'] ?></span>
          <span class="post-action-btn">💬 <?= (int)$post['comment_count'] ?></span>
          <?php endif; ?>
        </div>

        <div class="comments-section" id="comments-<?= (int)$post['id'] ?>"></div>
      </div>
      <?php endforeach; ?>

      <?php endif; ?>
    </div>
  </main>

  <!-- ===== 右サイドバー ===== -->
  <aside class="right-sidebar">
    <?php if (!$isLoggedIn): ?>
    <div class="widget">
      <div class="widget-title">このSNSについて</div>
      <p style="font-size:.85rem;color:var(--text-muted);line-height:1.7;margin-bottom:12px">
        仲間内だけのクローズドSNS。<br>
        アカウントを作って会話に参加しよう。
      </p>
      <a href="register.php" class="btn btn-primary" style="width:100%;display:flex">新規登録</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($suggestions)): ?>
    <div class="widget">
      <div class="widget-title">おすすめユーザー</div>
      <?php foreach ($suggestions as $s): ?>
      <div class="suggest-item">
        <a href="profile.php?id=<?= e($s['user_id']) ?>">
          <div class="avatar avatar-sm"
               style="background:<?= e($s['avatar_color'] ?? '#6C63FF') ?>">
            <?= e(getAvatarInitial($s['display_name'] ?? '', $s['user_id'])) ?>
          </div>
        </a>
        <div class="suggest-info">
          <div class="suggest-name">
            <?= e($s['display_name'] ?: $s['user_id']) ?>
          </div>
          <div class="suggest-handle">@<?= e($s['user_id']) ?></div>
        </div>
        <?php if ($isLoggedIn): ?>
        <button class="btn btn-primary btn-sm"
                onclick="Follow.toggle('<?= e($s['user_id']) ?>', this)">
          フォロー
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="widget" style="font-size:.75rem;color:var(--text-faint);line-height:1.8">
      ミニSNS &copy; <?= date('Y') ?> &bull;
      <a href="terms.php" style="color:var(--text-faint)">利用規約</a>
    </div>
  </aside>

</div>

<!-- ボトムナビ（モバイル） -->
<nav class="bottom-nav">
  <a href="index.php" class="bottom-nav-item active">
    <span class="nav-icon">🏠</span>ホーム
  </a>
  <?php if ($isLoggedIn): ?>
  <a href="profile.php?id=<?= e($currentUser['user_id']) ?>" class="bottom-nav-item">
    <span class="nav-icon">👤</span>プロフィール
  </a>
  <a href="logout.php" class="bottom-nav-item">
    <span class="nav-icon">🚪</span>ログアウト
  </a>
  <?php else: ?>
  <a href="login.php" class="bottom-nav-item">
    <span class="nav-icon">🔑</span>ログイン
  </a>
  <a href="register.php" class="bottom-nav-item">
    <span class="nav-icon">✨</span>登録
  </a>
  <?php endif; ?>
</nav>

<!-- リプライモーダル -->
<?php if ($isLoggedIn): ?>
<div class="modal-overlay" id="reply-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">↩ <span id="reply-modal-name"></span> に返信</div>
      <button class="modal-close" onclick="Modal.close('reply-modal')">✕</button>
    </div>
    <form method="POST" action="index.php" id="reply-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="reply_to_id" id="reply-to-id" value="">
      <div class="compose-inner" style="margin-bottom:12px">
        <div class="avatar" style="background:<?= e($currentUser['avatar_color'] ?? '#6C63FF') ?>">
          <?= e(getAvatarInitial($currentUser['display_name'] ?? '', $currentUser['user_id'])) ?>
        </div>
        <textarea class="compose-textarea" name="post_content" id="reply-textarea"
                  placeholder="返信を入力..." maxlength="280" required></textarea>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="char-count" id="reply-char-count">0 / 280</span>
        <button type="submit" class="btn btn-primary">返信する</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="assets/script.js"></script>
</body>
</html>
