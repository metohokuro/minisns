<?php
// admin.php - 管理者ダッシュボード（yamachin専用）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
requireAdmin(); // yamachin以外は403

$csrfToken = getCsrfToken();
$pdo       = getPDO();

// タブ
$tab = $_GET['tab'] ?? 'posts';

// ===== 投稿一覧 =====
$posts = [];
if ($tab === 'posts') {
    $stmt = $pdo->prepare("
        SELECT p.id, p.user_id, p.content, p.created_at, p.reply_to_id,
               u.display_name,
               (SELECT COUNT(*) FROM likes    l WHERE l.post_id = p.id) AS like_count,
               (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
        FROM posts p
        INNER JOIN users u ON p.user_id = u.user_id
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll();
}

// ===== コメント一覧 =====
$comments = [];
if ($tab === 'comments') {
    $stmt = $pdo->prepare("
        SELECT c.id, c.post_id, c.user_id, c.content, c.created_at,
               u.display_name
        FROM comments c
        INNER JOIN users u ON c.user_id = u.user_id
        ORDER BY c.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $comments = $stmt->fetchAll();
}

// ===== ユーザー一覧 =====
$users = [];
if ($tab === 'users') {
    $stmt = $pdo->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM posts    p WHERE p.user_id = u.user_id) AS post_count,
               (SELECT COUNT(*) FROM follows  f WHERE f.follower_id = u.user_id) AS following_count,
               (SELECT COUNT(*) FROM follows  f WHERE f.following_id = u.user_id) AS follower_count
        FROM users u
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
}

// ===== 統計 =====
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$stats['users'] = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM posts");
$stats['posts'] = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM comments");
$stats['comments'] = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM likes");
$stats['likes'] = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM direct_messages");
$stats['dms'] = (int)$stmt->fetchColumn();
// 直近7日のアクティブユーザー
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM posts WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['active7'] = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title>管理画面 - ミニSNS</title>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<link rel="stylesheet" href="assets/style.css">
<style>
.admin-wrap {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 16px 80px;
}
.admin-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px;
}
.admin-title {
  font-size: 1.4rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 10px;
}
.admin-badge {
  background: var(--danger);
  color: #fff;
  font-size: .65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: .06em;
}

/* 統計グリッド */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  margin-bottom: 28px;
}
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 480px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px;
  text-align: center;
}
.stat-card-num {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--text);
  line-height: 1;
  margin-bottom: 6px;
}
.stat-card-label {
  font-size: .72rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: .06em;
}

/* タブ */
.admin-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 6px;
}
.admin-tab {
  flex: 1;
  padding: 10px;
  text-align: center;
  font-size: .88rem;
  font-weight: 600;
  color: var(--text-muted);
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-decoration: none;
  transition: all .2s;
}
.admin-tab:hover { background: var(--bg-hover); color: var(--text); }
.admin-tab.active {
  background: var(--accent);
  color: #fff;
}

/* テーブル */
.admin-table-wrap {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}
.admin-table {
  width: 100%;
  border-collapse: collapse;
}
.admin-table th {
  background: var(--bg-hover);
  padding: 12px 14px;
  text-align: left;
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--text-muted);
  border-bottom: 1px solid var(--border);
}
.admin-table td {
  padding: 12px 14px;
  font-size: .86rem;
  color: var(--text-muted);
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }
.admin-table tr:hover td { background: var(--bg-hover); }

.admin-content-cell {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text);
}
.admin-user-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}
.reply-tag {
  font-size: .7rem;
  background: rgba(108,99,255,.15);
  color: var(--accent);
  padding: 2px 6px;
  border-radius: 4px;
  white-space: nowrap;
}
.banned-tag {
  font-size: .7rem;
  background: rgba(255,77,106,.15);
  color: var(--danger);
  padding: 2px 6px;
  border-radius: 4px;
}

.action-btns {
  display: flex;
  gap: 6px;
  flex-wrap: nowrap;
}
.btn-admin-del {
  background: rgba(255,77,106,.12);
  color: var(--danger);
  border: 1px solid rgba(255,77,106,.3);
  border-radius: var(--radius-sm);
  padding: 5px 10px;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
  white-space: nowrap;
  font-family: var(--font-main);
}
.btn-admin-del:hover {
  background: var(--danger);
  color: #fff;
}
.btn-admin-ban {
  background: rgba(255,143,83,.12);
  color: #FF8E53;
  border: 1px solid rgba(255,143,83,.3);
  border-radius: var(--radius-sm);
  padding: 5px 10px;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
  white-space: nowrap;
  font-family: var(--font-main);
}
.btn-admin-ban:hover { background: #FF8E53; color: #fff; }

.search-bar {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}
.search-input {
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-family: var(--font-main);
  font-size: .88rem;
  padding: 8px 12px;
  outline: none;
  flex: 1;
  transition: border-color .2s;
}
.search-input:focus { border-color: var(--accent); }
</style>
</head>
<body>

<div class="admin-wrap">

  <!-- ヘッダー -->
  <div class="admin-header">
    <div class="admin-title">
      ⚙️ 管理ダッシュボード
      <span class="admin-badge">Admin</span>
    </div>
    <a href="index.php" class="btn btn-outline btn-sm">← タイムラインへ戻る</a>
  </div>

  <!-- 統計 -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-card-num"><?= number_format($stats['users']) ?></div>
      <div class="stat-card-label">👤 ユーザー</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-num"><?= number_format($stats['posts']) ?></div>
      <div class="stat-card-label">📝 投稿</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-num"><?= number_format($stats['comments']) ?></div>
      <div class="stat-card-label">💬 コメント</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-num"><?= number_format($stats['likes']) ?></div>
      <div class="stat-card-label">❤️ いいね</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-num"><?= number_format($stats['dms']) ?></div>
      <div class="stat-card-label">✉️ DM</div>
    </div>
    <div class="stat-card" style="border-color:rgba(108,99,255,.4)">
      <div class="stat-card-num" style="color:var(--accent)"><?= number_format($stats['active7']) ?></div>
      <div class="stat-card-label">🔥 7日アクティブ</div>
    </div>
  </div>

  <!-- タブ -->
  <div class="admin-tabs">
    <a href="admin.php?tab=posts"
       class="admin-tab <?= $tab === 'posts'    ? 'active' : '' ?>">📝 投稿</a>
    <a href="admin.php?tab=comments"
       class="admin-tab <?= $tab === 'comments' ? 'active' : '' ?>">💬 コメント</a>
    <a href="admin.php?tab=users"
       class="admin-tab <?= $tab === 'users'    ? 'active' : '' ?>">👤 ユーザー</a>
  </div>

  <!-- ===== 投稿タブ ===== -->
  <?php if ($tab === 'posts'): ?>
  <div class="search-bar">
    <input class="search-input" type="text" id="search-posts" placeholder="投稿内容・ユーザーIDで絞り込み...">
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table" id="posts-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>ユーザー</th>
          <th>内容</th>
          <th>日時</th>
          <th>❤️</th>
          <th>💬</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
        <tr data-search="<?= e(strtolower($p['user_id'] . ' ' . $p['content'])) ?>">
          <td style="color:var(--text-faint);font-size:.78rem"><?= (int)$p['id'] ?></td>
          <td>
            <div class="admin-user-cell">
              <div class="avatar avatar-sm"
                   style="background:<?= e(generateAvatarColor($p['user_id'])) ?>">
                <?= e(getAvatarInitial($p['display_name'] ?? '', $p['user_id'])) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.83rem;color:var(--text)">
                  <?= e($p['display_name'] ?: $p['user_id']) ?>
                </div>
                <div style="font-size:.72rem">@<?= e($p['user_id']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <div class="admin-content-cell">
              <?php if ($p['reply_to_id']): ?>
              <span class="reply-tag">↩ 返信</span>
              <?php endif; ?>
              <?= e($p['content']) ?>
            </div>
          </td>
          <td style="white-space:nowrap;font-size:.78rem"><?= timeAgo($p['created_at']) ?></td>
          <td><?= (int)$p['like_count'] ?></td>
          <td><?= (int)$p['comment_count'] ?></td>
          <td>
            <button class="btn-admin-del"
                    onclick="adminDeletePost(<?= (int)$p['id'] ?>, this)">
              🗑️ 削除
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ===== コメントタブ ===== -->
  <?php if ($tab === 'comments'): ?>
  <div class="search-bar">
    <input class="search-input" type="text" id="search-comments" placeholder="コメント内容・ユーザーIDで絞り込み...">
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table" id="comments-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>ユーザー</th>
          <th>コメント内容</th>
          <th>投稿ID</th>
          <th>日時</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($comments as $c): ?>
        <tr data-search="<?= e(strtolower($c['user_id'] . ' ' . $c['content'])) ?>">
          <td style="color:var(--text-faint);font-size:.78rem"><?= (int)$c['id'] ?></td>
          <td>
            <div class="admin-user-cell">
              <div class="avatar avatar-sm"
                   style="background:<?= e(generateAvatarColor($c['user_id'])) ?>">
                <?= e(getAvatarInitial($c['display_name'] ?? '', $c['user_id'])) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.83rem;color:var(--text)">
                  <?= e($c['display_name'] ?: $c['user_id']) ?>
                </div>
                <div style="font-size:.72rem">@<?= e($c['user_id']) ?></div>
              </div>
            </div>
          </td>
          <td><div class="admin-content-cell"><?= e($c['content']) ?></div></td>
          <td style="font-size:.78rem;color:var(--accent)">
            <a href="index.php#post-<?= (int)$c['post_id'] ?>" style="color:var(--accent)">
              #<?= (int)$c['post_id'] ?>
            </a>
          </td>
          <td style="white-space:nowrap;font-size:.78rem"><?= timeAgo($c['created_at']) ?></td>
          <td>
            <button class="btn-admin-del"
                    onclick="adminDeleteComment(<?= (int)$c['id'] ?>, this)">
              🗑️ 削除
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ===== ユーザータブ ===== -->
  <?php if ($tab === 'users'): ?>
  <div class="search-bar">
    <input class="search-input" type="text" id="search-users" placeholder="ユーザーIDまたは表示名で絞り込み...">
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table" id="users-table">
      <thead>
        <tr>
          <th>ユーザー</th>
          <th>投稿数</th>
          <th>フォロワー</th>
          <th>登録日</th>
          <th>状態</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr data-search="<?= e(strtolower($u['user_id'] . ' ' . ($u['display_name'] ?? ''))) ?>"
            id="user-row-<?= e($u['user_id']) ?>">
          <td>
            <div class="admin-user-cell">
              <div class="avatar avatar-sm"
                   style="background:<?= e($u['avatar_color'] ?? generateAvatarColor($u['user_id'])) ?>">
                <?= e(getAvatarInitial($u['display_name'] ?? '', $u['user_id'])) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.85rem;color:var(--text)">
                  <?= e($u['display_name'] ?: $u['user_id']) ?>
                </div>
                <div style="font-size:.72rem">@<?= e($u['user_id']) ?></div>
              </div>
            </div>
          </td>
          <td><?= (int)$u['post_count'] ?></td>
          <td><?= (int)$u['follower_count'] ?></td>
          <td style="font-size:.78rem;white-space:nowrap">
            <?= date('Y/m/d', strtotime($u['created_at'])) ?>
          </td>
          <td>
            <?php if ($u['user_id'] === 'yamachin'): ?>
            <span style="font-size:.72rem;color:var(--accent);font-weight:700">👑 管理者</span>
            <?php elseif (!empty($u['is_banned'])): ?>
            <span class="banned-tag">BAN済み</span>
            <?php else: ?>
            <span style="font-size:.72rem;color:var(--success)">✅ 正常</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($u['user_id'] !== 'yamachin'): ?>
            <div class="action-btns">
              <button class="btn-admin-del"
                      onclick="adminDeleteUser('<?= e($u['user_id']) ?>', '<?= e(addslashes($u['display_name'] ?: $u['user_id'])) ?>', this)">
                🗑️ 削除
              </button>
            </div>
            <?php else: ?>
            <span style="font-size:.75rem;color:var(--text-faint)">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<script src="assets/script.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

// ===== 投稿削除 =====
async function adminDeletePost(postId, btn) {
  if (!confirm(`投稿 #${postId} を削除しますか？\n（いいね・コメント・ハッシュタグも削除されます）`)) return;
  btn.disabled = true;

  const res  = await fetch('delete_post.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `post_id=${postId}&csrf_token=${encodeURIComponent(CSRF)}`,
  });
  const data = await res.json();

  if (data.success) {
    const row = btn.closest('tr');
    row.style.transition = 'opacity .25s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 260);
  } else {
    alert(data.error || 'エラーが発生しました');
    btn.disabled = false;
  }
}

// ===== コメント削除 =====
async function adminDeleteComment(commentId, btn) {
  if (!confirm(`コメント #${commentId} を削除しますか？`)) return;
  btn.disabled = true;

  // CSRFトークンをPOST前に再取得（ローテーション対策）
  const res  = await fetch('admin_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=delete_comment&comment_id=${commentId}&csrf_token=${encodeURIComponent(CSRF)}`,
  });
  const data = await res.json();

  if (data.success) {
    const row = btn.closest('tr');
    row.style.transition = 'opacity .25s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 260);
  } else {
    alert(data.error || 'エラーが発生しました');
    btn.disabled = false;
  }
}

// ===== アカウント削除 =====
async function adminDeleteUser(userId, displayName, btn) {
  if (!confirm(`「${displayName}」(@${userId}) のアカウントを完全に削除しますか？\n\n⚠️ 投稿・コメント・DM・フォロー情報がすべて削除されます。\nこの操作は取り消せません。`)) return;
  // 二段階確認
  if (!confirm(`本当に削除しますか？\n「${displayName}」のデータはすべて失われます。`)) return;
  btn.disabled = true;

  const res  = await fetch('admin_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=delete_user&target_user_id=${encodeURIComponent(userId)}&csrf_token=${encodeURIComponent(CSRF)}`,
  });
  const data = await res.json();

  if (data.success) {
    const row = document.getElementById(`user-row-${userId}`);
    if (row) {
      row.style.transition = 'opacity .25s';
      row.style.opacity = '0';
      setTimeout(() => row.remove(), 260);
    }
  } else {
    alert(data.error || 'エラーが発生しました');
    btn.disabled = false;
  }
}

// ===== テーブル絞り込み =====
function initSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tr[data-search]').forEach(row => {
      row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
  });
}
initSearch('search-posts',    'posts-table');
initSearch('search-comments', 'comments-table');
initSearch('search-users',    'users-table');
</script>
</body>
</html>
