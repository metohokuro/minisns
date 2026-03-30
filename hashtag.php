<?php
// hashtag.php - ハッシュタグ検索・投稿一覧

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$isLoggedIn  = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$csrfToken   = getCsrfToken();
$pdo         = getPDO();

$tag   = trim($_GET['tag'] ?? '');
$posts = [];

if ($tag !== '') {
    // タグに紐づく投稿を取得
    $stmt = $pdo->prepare("
        SELECT
            p.id, p.user_id, p.content, p.created_at, p.reply_to_id,
            u.display_name, u.avatar_color,
            (SELECT COUNT(*) FROM likes   l WHERE l.post_id = p.id) AS like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
        FROM posts p
        INNER JOIN users u ON p.user_id = u.user_id
        INNER JOIN post_hashtags ph ON p.id = ph.post_id
        INNER JOIN hashtags h ON ph.tag_id = h.id
        WHERE h.tag = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([mb_strtolower($tag, 'UTF-8')]);
    $posts = $stmt->fetchAll();
}

// トレンドタグ（直近7日で最も使われたタグ Top10）
$stmt = $pdo->prepare("
    SELECT h.tag, COUNT(ph.post_id) AS cnt
    FROM hashtags h
    INNER JOIN post_hashtags ph ON h.id = ph.tag_id
    INNER JOIN posts p ON ph.post_id = p.id
    WHERE p.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY h.id
    ORDER BY cnt DESC
    LIMIT 10
");
$stmt->execute();
$trends = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title><?= $tag ? '#' . e($tag) : 'タグ検索' ?> - ミニSNS</title>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<link rel="stylesheet" href="assets/style.css">
</head>
<body data-logged-in="<?= $isLoggedIn ? '1' : '0' ?>">

<div class="app-container">

  <!-- 左サイドバー -->
  <aside class="left-sidebar">
    <div class="logo"><div class="logo-icon">✦</div> ミニSNS</div>
    <a class="nav-item" href="index.php"><span class="nav-icon">🏠</span> ホーム</a>
    <?php if ($isLoggedIn): ?>
    <a class="nav-item" href="notifications.php"><span class="nav-icon">🔔</span> 通知</a>
    <?php endif; ?>
    <a class="nav-item active" href="hashtag.php"><span class="nav-icon">#</span> タグ検索</a>
    <?php if ($isLoggedIn): ?>
    <a class="nav-item" href="profile.php?id=<?= e($currentUser['user_id']) ?>">
      <span class="nav-icon">👤</span> プロフィール
    </a>
    <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--border)">
      <a href="logout.php" class="nav-item" style="color:var(--danger)">
        <span class="nav-icon">🚪</span> ログアウト
      </a>
    </div>
    <?php endif; ?>
  </aside>

  <!-- メイン -->
  <main>
    <!-- 検索フォーム -->
    <div class="compose-box" style="margin-bottom:12px">
      <form method="GET" action="hashtag.php" style="display:flex;gap:8px;align-items:center">
        <span style="font-size:1.2rem;color:var(--accent)">#</span>
        <input class="form-input" type="text" name="tag"
               value="<?= e($tag) ?>"
               placeholder="タグを検索..."
               style="flex:1;border-radius:50px"
               maxlength="50">
        <button type="submit" class="btn btn-primary btn-sm">検索</button>
      </form>
    </div>

    <?php if ($tag !== ''): ?>
    <div class="feed">
      <div class="feed-header">
        <span style="color:var(--accent)">#<?= e($tag) ?></span>
        <span style="font-size:.8rem;color:var(--text-muted);font-weight:400;margin-left:8px">
          <?= count($posts) ?> 件
        </span>
      </div>

      <?php if (empty($posts)): ?>
      <div class="empty-state">
        <span class="empty-icon">#</span>
        <div class="empty-title">#<?= e($tag) ?> の投稿はありません</div>
      </div>
      <?php else: ?>

      <?php foreach ($posts as $post):
        $liked   = $isLoggedIn ? isLiked($post['id'], $currentUser['user_id']) : false;
        $initial = getAvatarInitial($post['display_name'] ?? '', $post['user_id']);
        $color   = $post['avatar_color'] ?? '#6C63FF';
      ?>
      <div class="post-card" id="post-<?= (int)$post['id'] ?>">
        <div class="post-header">
          <a href="profile.php?id=<?= e($post['user_id']) ?>">
            <div class="avatar" style="background:<?= e($color) ?>"><?= e($initial) ?></div>
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
          <button class="post-action-btn <?= $liked ? 'liked' : '' ?>"
                  onclick="Like.toggle(<?= (int)$post['id'] ?>, this)">
            <?= $liked ? '❤️' : '🤍' ?>
            <span class="like-count"><?= (int)$post['like_count'] ?></span>
          </button>
          <button class="post-action-btn"
                  onclick="Comments.toggle(<?= (int)$post['id'] ?>)">
            💬 <span data-comment-count="<?= (int)$post['id'] ?>"><?= (int)$post['comment_count'] ?></span>
          </button>
          <?php else: ?>
          <span class="post-action-btn">🤍 <?= (int)$post['like_count'] ?></span>
          <?php endif; ?>
        </div>
        <div class="comments-section" id="comments-<?= (int)$post['id'] ?>"></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- タグ未入力時はトレンドを表示 -->
    <div class="feed">
      <div class="feed-header"># トレンドタグ（直近7日）</div>
      <?php if (empty($trends)): ?>
      <div class="empty-state">
        <span class="empty-icon">#</span>
        <div class="empty-title">まだタグ付き投稿がありません</div>
        <div class="empty-text">投稿に #タグ をつけてみましょう</div>
      </div>
      <?php else: ?>
      <?php foreach ($trends as $i => $t): ?>
      <a href="hashtag.php?tag=<?= urlencode($t['tag']) ?>" class="trend-item">
        <div class="trend-rank"><?= $i + 1 ?></div>
        <div class="trend-info">
          <div class="trend-tag">#<?= e($t['tag']) ?></div>
          <div class="trend-count"><?= (int)$t['cnt'] ?> 件の投稿</div>
        </div>
        <div class="trend-arrow">→</div>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </main>

  <!-- 右サイドバー -->
  <aside class="right-sidebar">
    <?php if (!empty($trends)): ?>
    <div class="widget">
      <div class="widget-title">トレンドタグ</div>
      <?php foreach ($trends as $t): ?>
      <a href="hashtag.php?tag=<?= urlencode($t['tag']) ?>"
         class="hashtag-link" style="display:inline-block;margin:3px 2px;font-size:.85rem">
        #<?= e($t['tag']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="widget" style="font-size:.82rem;color:var(--text-muted);line-height:1.9">
      <div class="widget-title">タグの使い方</div>
      投稿に <span style="color:var(--accent)">#話題</span> のように書くと自動でリンクになります。<br>
      日本語・英数字どちらも使えます。
    </div>
  </aside>

</div>

<nav class="bottom-nav">
  <a href="index.php" class="bottom-nav-item"><span class="nav-icon">🏠</span>ホーム</a>
  <a href="hashtag.php" class="bottom-nav-item active"><span class="nav-icon">#</span>タグ</a>
  <?php if ($isLoggedIn): ?>
  <a href="profile.php?id=<?= e($currentUser['user_id']) ?>" class="bottom-nav-item">
    <span class="nav-icon">👤</span>プロフィール
  </a>
  <?php else: ?>
  <a href="login.php" class="bottom-nav-item"><span class="nav-icon">🔑</span>ログイン</a>
  <?php endif; ?>
</nav>

<script src="assets/script.js"></script>
</body>
</html>
