<?php
// profile.php - プロフィールページ（タブ対応版）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$isLoggedIn  = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$csrfToken   = getCsrfToken();

$targetId = trim($_GET['id'] ?? '');
if (empty($targetId)) {
    header('Location: ' . ($isLoggedIn ? 'profile.php?id=' . urlencode($currentUser['user_id']) : 'login.php'));
    exit;
}

$pdo  = getPDO();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$targetId]);
$profile = $stmt->fetch();

if (!$profile) { http_response_code(404); die('ユーザーが見つかりません'); }

$isOwnProfile  = $isLoggedIn && $currentUser['user_id'] === $profile['user_id'];
$isFollowing   = $isLoggedIn && !$isOwnProfile ? isFollowing($currentUser['user_id'], $profile['user_id']) : false;
$followerCount = getFollowerCount($profile['user_id']);
$followingCount= getFollowingCount($profile['user_id']);
$postCount     = getPostCount($profile['user_id']);
$initial       = getAvatarInitial($profile['display_name'] ?? '', $profile['user_id']);
$color         = $profile['avatar_color'] ?? '#6C63FF';

// 表示タブ
$tab = $_GET['tab'] ?? 'posts';
if (!in_array($tab, ['posts','likes','following','followers'])) $tab = 'posts';

// プロフィール編集
$editError = $editSuccess = '';
if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $editError = 'セキュリティエラー';
    } else {
        $newName = trim($_POST['display_name'] ?? '');
        $newBio  = trim($_POST['bio'] ?? '');
        if (mb_strlen($newName) > 50)     $editError = '表示名は50文字以内';
        elseif (mb_strlen($newBio) > 200) $editError = '自己紹介は200文字以内';
        else {
            $pdo->prepare("UPDATE users SET display_name=?, bio=? WHERE user_id=?")
                ->execute([$newName ?: $profile['user_id'], $newBio, $profile['user_id']]);
            $editSuccess = 'プロフィールを更新しました';
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
            $stmt->execute([$targetId]);
            $profile = $stmt->fetch();
            $initial = getAvatarInitial($profile['display_name'] ?? '', $profile['user_id']);
        }
    }
    $csrfToken = getCsrfToken();
}

// ===== タブ別データ取得 =====

// 投稿
$userPosts = [];
if ($tab === 'posts') {
    $stmt = $pdo->prepare("
        SELECT p.id, p.content, p.created_at, p.reply_to_id,
               (SELECT COUNT(*) FROM likes    l WHERE l.post_id = p.id) AS like_count,
               (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
        FROM posts p WHERE p.user_id = ?
        ORDER BY p.created_at DESC LIMIT 30
    ");
    $stmt->execute([$profile['user_id']]);
    $userPosts = $stmt->fetchAll();
}

// いいねした投稿
$likedPosts = [];
if ($tab === 'likes') {
    $stmt = $pdo->prepare("
        SELECT p.id, p.user_id, p.content, p.created_at, p.reply_to_id,
               u.display_name, u.avatar_color,
               (SELECT COUNT(*) FROM likes    l2 WHERE l2.post_id = p.id) AS like_count,
               (SELECT COUNT(*) FROM comments c  WHERE c.post_id  = p.id) AS comment_count,
               li.created_at AS liked_at
        FROM likes li
        INNER JOIN posts p ON li.post_id = p.id
        INNER JOIN users u ON p.user_id  = u.user_id
        WHERE li.user_id = ?
        ORDER BY li.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$profile['user_id']]);
    $likedPosts = $stmt->fetchAll();
}

// フォロー中
$followingList = [];
if ($tab === 'following') {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.display_name, u.avatar_color, u.bio, f.created_at AS followed_at
        FROM follows f
        INNER JOIN users u ON f.following_id = u.user_id
        WHERE f.follower_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$profile['user_id']]);
    $followingList = $stmt->fetchAll();
}

// フォロワー
$followerList = [];
if ($tab === 'followers') {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.display_name, u.avatar_color, u.bio, f.created_at AS followed_at
        FROM follows f
        INNER JOIN users u ON f.follower_id = u.user_id
        WHERE f.following_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$profile['user_id']]);
    $followerList = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title><?= e($profile['display_name'] ?: $profile['user_id']) ?> - ミニSNS</title>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<link rel="stylesheet" href="assets/style.css">
<style>
/* プロフィールタブ */
.profile-tabs {
  display: flex;
  border-bottom: 1px solid var(--border);
  background: var(--bg-card);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.profile-tab {
  flex: 1;
  min-width: 80px;
  padding: 14px 8px;
  text-align: center;
  font-size: .85rem;
  font-weight: 600;
  color: var(--text-muted);
  text-decoration: none;
  border-bottom: 2px solid transparent;
  transition: all .2s;
  white-space: nowrap;
}
.profile-tab:hover { color: var(--text); background: var(--bg-hover); }
.profile-tab.active { color: var(--accent); border-bottom-color: var(--accent); }

/* ユーザーカードリスト */
.user-card-list { background: var(--bg-card); }
.user-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  transition: background .15s;
}
.user-card:last-child { border-bottom: none; }
.user-card:hover { background: var(--bg-hover); }
.user-card-info { flex: 1; min-width: 0; }
.user-card-name {
  font-weight: 700;
  font-size: .92rem;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-card-handle {
  font-size: .78rem;
  color: var(--text-muted);
  margin-top: 1px;
}
.user-card-bio {
  font-size: .8rem;
  color: var(--text-muted);
  margin-top: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* いいね投稿カードのグレーアウト */
.liked-post-card { border-left: 3px solid rgba(247,37,133,.3); }
</style>
</head>
<body data-logged-in="<?= $isLoggedIn ? '1' : '0' ?>">

<div class="app-container">

  <!-- 左サイドバー -->
  <aside class="left-sidebar">
    <div class="logo"><div class="logo-icon">✦</div> ミニSNS</div>
    <a class="nav-item" href="index.php"><span class="nav-icon">🏠</span> ホーム</a>
    <?php if ($isLoggedIn): ?>
    <a class="nav-item" href="notifications.php"><span class="nav-icon">🔔</span> 通知</a>
    <a class="nav-item" href="dm.php"><span class="nav-icon">✉️</span> DM</a>
    <a class="nav-item" href="hashtag.php"><span class="nav-icon">#</span> タグ検索</a>
    <a class="nav-item active" href="profile.php?id=<?= e($currentUser['user_id']) ?>">
      <span class="nav-icon">👤</span> プロフィール
    </a>
    <?php if (isAdmin()): ?>
    <a class="nav-item" href="admin.php" style="color:#FF8E53"><span class="nav-icon">⚙️</span> 管理</a>
    <?php endif; ?>
    <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--border)">
      <a href="logout.php" class="nav-item" style="color:var(--danger)">
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

  <!-- メイン -->
  <main>
    <?php if ($editSuccess): ?>
    <div class="flash flash-success" style="margin-bottom:12px">✅ <?= e($editSuccess) ?></div>
    <?php endif; ?>
    <?php if ($editError): ?>
    <div class="flash flash-error" style="margin-bottom:12px">⚠️ <?= e($editError) ?></div>
    <?php endif; ?>

    <!-- プロフィールカード -->
    <div class="profile-header" style="margin-bottom:0">
      <div class="profile-cover"></div>
      <div class="profile-info" style="margin-top:48px">
        <div class="avatar avatar-xl" style="background:<?= e($color) ?>;border:4px solid var(--bg-card)">
          <?= e($initial) ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <?php if ($isOwnProfile): ?>
          <button class="btn btn-outline btn-sm" onclick="Modal.open('edit-profile-modal')">編集</button>
          <?php elseif ($isLoggedIn): ?>
          <button class="btn <?= $isFollowing ? 'btn-outline' : 'btn-primary' ?> btn-sm"
                  onclick="Follow.toggle('<?= e($profile['user_id']) ?>', this)">
            <?= $isFollowing ? 'フォロー中' : 'フォロー' ?>
          </button>
          <a href="dm.php?with=<?= e($profile['user_id']) ?>" class="btn btn-outline btn-sm">✉️ DM</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="profile-name"><?= e($profile['display_name'] ?: $profile['user_id']) ?></div>
      <div class="profile-handle">@<?= e($profile['user_id']) ?></div>
      <?php if (!empty($profile['bio'])): ?>
      <div class="profile-bio"><?= nl2brSafe($profile['bio']) ?></div>
      <?php endif; ?>

      <div class="divider"></div>

      <!-- 数字クリックでタブ切替 -->
      <div class="profile-stats">
        <a class="stat-item" href="profile.php?id=<?= e($profile['user_id']) ?>&tab=posts"
           style="text-decoration:none;cursor:pointer">
          <span class="stat-num"><?= $postCount ?></span>
          <span class="stat-label">投稿</span>
        </a>
        <a class="stat-item" href="profile.php?id=<?= e($profile['user_id']) ?>&tab=followers"
           style="text-decoration:none;cursor:pointer">
          <span class="stat-num"><?= $followerCount ?></span>
          <span class="stat-label">フォロワー</span>
        </a>
        <a class="stat-item" href="profile.php?id=<?= e($profile['user_id']) ?>&tab=following"
           style="text-decoration:none;cursor:pointer">
          <span class="stat-num"><?= $followingCount ?></span>
          <span class="stat-label">フォロー中</span>
        </a>
      </div>
    </div>

    <!-- タブ -->
    <div class="profile-tabs">
      <a class="profile-tab <?= $tab==='posts'     ? 'active' : '' ?>"
         href="profile.php?id=<?= e($profile['user_id']) ?>&tab=posts">📝 投稿</a>
      <a class="profile-tab <?= $tab==='likes'     ? 'active' : '' ?>"
         href="profile.php?id=<?= e($profile['user_id']) ?>&tab=likes">❤️ いいね</a>
      <a class="profile-tab <?= $tab==='following' ? 'active' : '' ?>"
         href="profile.php?id=<?= e($profile['user_id']) ?>&tab=following">フォロー中</a>
      <a class="profile-tab <?= $tab==='followers' ? 'active' : '' ?>"
         href="profile.php?id=<?= e($profile['user_id']) ?>&tab=followers">フォロワー</a>
    </div>

    <!-- ===== 投稿タブ ===== -->
    <?php if ($tab === 'posts'): ?>
    <div class="feed" style="border-top:none;border-radius:0 0 var(--radius) var(--radius)">
      <?php if (empty($userPosts)): ?>
      <div class="empty-state">
        <span class="empty-icon">📝</span>
        <div class="empty-title">まだ投稿がありません</div>
      </div>
      <?php else: ?>
      <?php foreach ($userPosts as $post):
        $liked = $isLoggedIn ? isLiked($post['id'], $currentUser['user_id']) : false;
      ?>
      <div class="post-card" id="post-<?= (int)$post['id'] ?>">
        <?php if ($post['reply_to_id']): ?>
        <div class="reply-indicator">↩ 返信</div>
        <?php endif; ?>
        <div class="post-header">
          <div class="avatar" style="background:<?= e($color) ?>"><?= e($initial) ?></div>
          <div class="post-meta">
            <div class="post-user">
              <span class="post-display-name"><?= e($profile['display_name'] ?: $profile['user_id']) ?></span>
              <span class="post-user-id">@<?= e($profile['user_id']) ?></span>
              <span class="post-time"><?= timeAgo($post['created_at']) ?></span>
            </div>
          </div>
        </div>
        <div class="post-content"><?= formatContent($post['content']) ?></div>
        <div class="post-actions">
          <?php if ($isLoggedIn): ?>
          <button class="post-action-btn <?= $liked ? 'liked' : '' ?>"
                  onclick="Like.toggle(<?= (int)$post['id'] ?>, this)">
            <?= $liked ? '❤️' : '🤍' ?> <span class="like-count"><?= (int)$post['like_count'] ?></span>
          </button>
          <button class="post-action-btn" onclick="Comments.toggle(<?= (int)$post['id'] ?>)">
            💬 <span data-comment-count="<?= (int)$post['id'] ?>"><?= (int)$post['comment_count'] ?></span>
          </button>
          <?php if ($isOwnProfile || isAdmin()): ?>
          <button class="post-action-btn delete-btn"
                  onclick="deletePost(<?= (int)$post['id'] ?>, this)" title="削除">🗑️</button>
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

    <!-- ===== いいねタブ ===== -->
    <?php elseif ($tab === 'likes'): ?>
    <div class="feed" style="border-top:none;border-radius:0 0 var(--radius) var(--radius)">
      <?php if (empty($likedPosts)): ?>
      <div class="empty-state">
        <span class="empty-icon">❤️</span>
        <div class="empty-title">まだいいねした投稿がありません</div>
      </div>
      <?php else: ?>
      <?php foreach ($likedPosts as $post):
        $postInitial = getAvatarInitial($post['display_name'] ?? '', $post['user_id']);
        $postColor   = $post['avatar_color'] ?? '#6C63FF';
        $liked       = $isLoggedIn ? isLiked($post['id'], $currentUser['user_id']) : false;
      ?>
      <div class="post-card liked-post-card" id="post-<?= (int)$post['id'] ?>">
        <div class="post-header">
          <a href="profile.php?id=<?= e($post['user_id']) ?>">
            <div class="avatar" style="background:<?= e($postColor) ?>"><?= e($postInitial) ?></div>
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
            <?= $liked ? '❤️' : '🤍' ?> <span class="like-count"><?= (int)$post['like_count'] ?></span>
          </button>
          <button class="post-action-btn" onclick="Comments.toggle(<?= (int)$post['id'] ?>)">
            💬 <span data-comment-count="<?= (int)$post['id'] ?>"><?= (int)$post['comment_count'] ?></span>
          </button>
          <?php if (isAdmin()): ?>
          <button class="post-action-btn delete-btn"
                  onclick="deletePost(<?= (int)$post['id'] ?>, this)" title="削除">🗑️</button>
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

    <!-- ===== フォロー中タブ ===== -->
    <?php elseif ($tab === 'following'): ?>
    <div class="user-card-list" style="border-radius:0 0 var(--radius) var(--radius);border:1px solid var(--border);border-top:none">
      <?php if (empty($followingList)): ?>
      <div class="empty-state">
        <span class="empty-icon">👤</span>
        <div class="empty-title">まだフォローしていません</div>
      </div>
      <?php else: ?>
      <?php foreach ($followingList as $u):
        $uInitial = getAvatarInitial($u['display_name'] ?? '', $u['user_id']);
        $uColor   = $u['avatar_color'] ?? '#6C63FF';
        $uFollowing = $isLoggedIn && $currentUser['user_id'] !== $u['user_id']
            ? isFollowing($currentUser['user_id'], $u['user_id']) : null;
      ?>
      <div class="user-card">
        <a href="profile.php?id=<?= e($u['user_id']) ?>">
          <div class="avatar" style="background:<?= e($uColor) ?>"><?= e($uInitial) ?></div>
        </a>
        <div class="user-card-info">
          <a href="profile.php?id=<?= e($u['user_id']) ?>" style="text-decoration:none">
            <div class="user-card-name"><?= e($u['display_name'] ?: $u['user_id']) ?></div>
            <div class="user-card-handle">@<?= e($u['user_id']) ?></div>
          </a>
          <?php if (!empty($u['bio'])): ?>
          <div class="user-card-bio"><?= e($u['bio']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($isLoggedIn && $currentUser['user_id'] !== $u['user_id']): ?>
        <button class="btn <?= $uFollowing ? 'btn-outline' : 'btn-primary' ?> btn-sm"
                onclick="Follow.toggle('<?= e($u['user_id']) ?>', this)">
          <?= $uFollowing ? 'フォロー中' : 'フォロー' ?>
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ===== フォロワータブ ===== -->
    <?php elseif ($tab === 'followers'): ?>
    <div class="user-card-list" style="border-radius:0 0 var(--radius) var(--radius);border:1px solid var(--border);border-top:none">
      <?php if (empty($followerList)): ?>
      <div class="empty-state">
        <span class="empty-icon">👤</span>
        <div class="empty-title">まだフォロワーがいません</div>
      </div>
      <?php else: ?>
      <?php foreach ($followerList as $u):
        $uInitial = getAvatarInitial($u['display_name'] ?? '', $u['user_id']);
        $uColor   = $u['avatar_color'] ?? '#6C63FF';
        $uFollowing = $isLoggedIn && $currentUser['user_id'] !== $u['user_id']
            ? isFollowing($currentUser['user_id'], $u['user_id']) : null;
      ?>
      <div class="user-card">
        <a href="profile.php?id=<?= e($u['user_id']) ?>">
          <div class="avatar" style="background:<?= e($uColor) ?>"><?= e($uInitial) ?></div>
        </a>
        <div class="user-card-info">
          <a href="profile.php?id=<?= e($u['user_id']) ?>" style="text-decoration:none">
            <div class="user-card-name"><?= e($u['display_name'] ?: $u['user_id']) ?></div>
            <div class="user-card-handle">@<?= e($u['user_id']) ?></div>
          </a>
          <?php if (!empty($u['bio'])): ?>
          <div class="user-card-bio"><?= e($u['bio']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($isLoggedIn && $currentUser['user_id'] !== $u['user_id']): ?>
        <button class="btn <?= $uFollowing ? 'btn-outline' : 'btn-primary' ?> btn-sm"
                onclick="Follow.toggle('<?= e($u['user_id']) ?>', this)">
          <?= $uFollowing ? 'フォロー中' : 'フォロー' ?>
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </main>

  <!-- 右サイドバー -->
  <aside class="right-sidebar">
    <div class="widget">
      <div class="widget-title">ユーザー情報</div>
      <div style="font-size:.85rem;color:var(--text-muted);line-height:2">
        <div>📅 登録日: <?= date('Y/m/d', strtotime($profile['created_at'])) ?></div>
        <div>📝 投稿数: <?= $postCount ?></div>
        <div>👥 フォロワー: <?= $followerCount ?></div>
        <div>➡️ フォロー中: <?= $followingCount ?></div>
      </div>
    </div>
  </aside>

</div>

<!-- 編集モーダル -->
<?php if ($isOwnProfile): ?>
<div class="modal-overlay" id="edit-profile-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">プロフィール編集</div>
      <button class="modal-close" onclick="Modal.close('edit-profile-modal')">✕</button>
    </div>
    <form method="POST" action="profile.php?id=<?= e($profile['user_id']) ?>">
      <input type="hidden" name="csrf_token"    value="<?= e($csrfToken) ?>">
      <input type="hidden" name="edit_profile"  value="1">
      <div class="form-group">
        <label class="form-label">表示名</label>
        <input class="form-input" type="text" name="display_name"
               value="<?= e($profile['display_name'] ?? '') ?>" maxlength="50">
      </div>
      <div class="form-group">
        <label class="form-label">自己紹介</label>
        <textarea class="form-input" name="bio" maxlength="200"
                  placeholder="自己紹介を書く..."><?= e($profile['bio'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-outline"
                onclick="Modal.close('edit-profile-modal')">キャンセル</button>
        <button type="submit" class="btn btn-primary">保存</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ボトムナビ -->
<nav class="bottom-nav">
  <a href="index.php" class="bottom-nav-item"><span class="nav-icon">🏠</span>ホーム</a>
  <?php if ($isLoggedIn): ?>
  <a href="notifications.php" class="bottom-nav-item"><span class="nav-icon">🔔</span>通知</a>
  <a href="profile.php?id=<?= e($currentUser['user_id']) ?>" class="bottom-nav-item active">
    <span class="nav-icon">👤</span>プロフィール
  </a>
  <?php else: ?>
  <a href="login.php" class="bottom-nav-item"><span class="nav-icon">🔑</span>ログイン</a>
  <?php endif; ?>
</nav>

<script src="assets/script.js"></script>
</body>
</html>
