<?php
// setup_admin.php - データベース＆管理者アカウント一括セットアップ
// ⚠️ 使用後はこのファイルを必ず削除してください！

require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();
$step = $_GET['step'] ?? 'db'; // db | admin | done

$dbErrors = [];
$dbSuccess = [];
$dbExists = [];
$adminError = '';
$adminSuccess = '';

// ===== テーブル存在チェック関数 =====
function tableExists($pdo, $tableName) {
    try {
        $pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ===== STEP 1: データベース作成 =====
if ($step === 'db' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_db'])) {
    
    $tables = [
        'users' => "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(30) NOT NULL UNIQUE,
                fingerprint VARCHAR(255) NOT NULL,
                display_name VARCHAR(50) DEFAULT NULL,
                bio TEXT DEFAULT NULL,
                avatar_color VARCHAR(7) DEFAULT '#6C63FF',
                avatar_img VARCHAR(255) DEFAULT NULL,
                is_banned TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_fingerprint (fingerprint),
                INDEX idx_banned (is_banned)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'posts' => "
            CREATE TABLE posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(30) NOT NULL,
                content TEXT NOT NULL,
                reply_to_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at),
                INDEX idx_reply_to (reply_to_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'likes' => "
            CREATE TABLE likes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                user_id VARCHAR(30) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_like (post_id, user_id),
                INDEX idx_post_id (post_id),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'comments' => "
            CREATE TABLE comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                user_id VARCHAR(30) NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_post_id (post_id),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'follows' => "
            CREATE TABLE follows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                follower_id VARCHAR(30) NOT NULL,
                following_id VARCHAR(30) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_follow (follower_id, following_id),
                INDEX idx_follower (follower_id),
                INDEX idx_following (following_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'notifications' => "
            CREATE TABLE notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_user_id VARCHAR(30) NOT NULL,
                from_user_id VARCHAR(30) NOT NULL,
                type ENUM('like', 'comment', 'follow', 'reply') NOT NULL,
                post_id INT DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_to_user (to_user_id),
                INDEX idx_from_user (from_user_id),
                INDEX idx_is_read (is_read),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'hashtags' => "
            CREATE TABLE hashtags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tag VARCHAR(50) NOT NULL UNIQUE,
                use_count INT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tag (tag),
                INDEX idx_use_count (use_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'post_hashtags' => "
            CREATE TABLE post_hashtags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                hashtag_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_post_tag (post_id, hashtag_id),
                INDEX idx_post_id (post_id),
                INDEX idx_hashtag_id (hashtag_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'direct_messages' => "
            CREATE TABLE direct_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id VARCHAR(30) NOT NULL,
                receiver_id VARCHAR(30) NOT NULL,
                content TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sender (sender_id),
                INDEX idx_receiver (receiver_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'post_images' => "
            CREATE TABLE post_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                sort_order TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_post_id (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
    ];

    foreach ($tables as $name => $sql) {
        if (tableExists($pdo, $name)) {
            $dbExists[] = $name;
        } else {
            try {
                $pdo->exec($sql);
                $dbSuccess[] = $name;
            } catch (PDOException $e) {
                $dbErrors[] = "{$name}: " . $e->getMessage();
            }
        }
    }

    // 成功したら次のステップへ
    if (empty($dbErrors)) {
        header('Location: setup_admin.php?step=admin');
        exit;
    }
}

// ===== STEP 2: 管理者アカウント作成 =====
if ($step === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $adminId = trim($_POST['admin_id'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminBio = trim($_POST['admin_bio'] ?? '');
    $adminColor = trim($_POST['admin_color'] ?? '#FF6B6B');
    $adminFp = 'SETUP_' . bin2hex(random_bytes(16)); // ダミーフィンガープリント

    if (empty($adminId)) {
        $adminError = 'ユーザーIDを入力してください';
    } elseif (!preg_match('/^[a-z0-9_]{3,30}$/i', $adminId)) {
        $adminError = 'ユーザーIDは3〜30文字の英数字とアンダースコアのみ';
    } else {
        // 既に存在するかチェック
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
        $stmt->execute([$adminId]);
        if ($stmt->fetch()) {
            $adminError = 'このユーザーIDは既に存在します';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO users (user_id, fingerprint, display_name, bio, avatar_color)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([$adminId, $adminFp, $adminName ?: $adminId, $adminBio, $adminColor]);
                
                $adminSuccess = "管理者アカウント「{$adminId}」を作成しました！";
                // 完了ステップへ
                header('Location: setup_admin.php?step=done&admin=' . urlencode($adminId));
                exit;
            } catch (PDOException $e) {
                $adminError = 'エラー: ' . $e->getMessage();
            }
        }
    }
}

// ===== 各ステップの状態確認 =====
$allTablesExist = true;
$requiredTables = ['users','posts','likes','comments','follows','notifications','hashtags','post_hashtags','direct_messages','post_images'];
foreach ($requiredTables as $t) {
    if (!tableExists($pdo, $t)) {
        $allTablesExist = false;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#6C63FF">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title>初期セットアップ - ミニSNS</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.setup-container {
  max-width: 600px;
  margin: 60px auto;
  padding: 0 20px;
}
.setup-steps {
  display: flex;
  justify-content: space-between;
  margin-bottom: 32px;
  position: relative;
}
.setup-steps::before {
  content: '';
  position: absolute;
  top: 16px;
  left: 10%;
  right: 10%;
  height: 2px;
  background: var(--border);
  z-index: 0;
}
.setup-step {
  flex: 1;
  text-align: center;
  position: relative;
  z-index: 1;
}
.step-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--bg-card);
  border: 2px solid var(--border);
  margin: 0 auto 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .8rem;
  font-weight: 700;
  color: var(--text-muted);
}
.setup-step.active .step-circle {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}
.setup-step.done .step-circle {
  background: var(--success);
  border-color: var(--success);
  color: #fff;
}
.step-label {
  font-size: .78rem;
  color: var(--text-muted);
}
.setup-step.active .step-label {
  color: var(--text);
  font-weight: 600;
}
.setup-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 32px;
}
.setup-title {
  font-size: 1.4rem;
  font-weight: 800;
  margin-bottom: 8px;
}
.setup-desc {
  font-size: .88rem;
  color: var(--text-muted);
  margin-bottom: 24px;
  line-height: 1.7;
}
</style>
</head>
<body>

<div class="setup-container">

  <!-- ステップインジケーター -->
  <div class="setup-steps">
    <div class="setup-step <?= $step === 'db' ? 'active' : ($allTablesExist ? 'done' : '') ?>">
      <div class="step-circle"><?= $allTablesExist ? '✓' : '1' ?></div>
      <div class="step-label">データベース</div>
    </div>
    <div class="setup-step <?= $step === 'admin' ? 'active' : ($step === 'done' ? 'done' : '') ?>">
      <div class="step-circle"><?= $step === 'done' ? '✓' : '2' ?></div>
      <div class="step-label">管理者アカウント</div>
    </div>
    <div class="setup-step <?= $step === 'done' ? 'active done' : '' ?>">
      <div class="step-circle"><?= $step === 'done' ? '✓' : '3' ?></div>
      <div class="step-label">完了</div>
    </div>
  </div>

  <!-- STEP 1: データベース作成 -->
  <?php if ($step === 'db'): ?>
  <div class="setup-card">
    <div class="setup-title">📊 データベース作成</div>
    <div class="setup-desc">
      ミニSNSに必要な全10テーブルを作成します。
    </div>

    <?php if (!empty($dbErrors)): ?>
    <div class="flash flash-error">
      ⚠️ エラーが発生しました
      <ul style="margin:8px 0 0;padding-left:20px">
        <?php foreach ($dbErrors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($allTablesExist && empty($dbSuccess)): ?>
    <div class="flash flash-success">
      ✅ すべてのテーブルが既に作成されています！
    </div>
    <div style="margin-top:20px">
      <a href="setup_admin.php?step=admin" class="btn btn-primary" style="width:100%">
        次へ：管理者アカウント作成 →
      </a>
    </div>
    <?php else: ?>
    <form method="POST">
      <button type="submit" name="create_db" value="1" class="btn btn-primary" style="width:100%">
        テーブルを作成する
      </button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- STEP 2: 管理者アカウント作成 -->
  <?php if ($step === 'admin'): ?>
  <div class="setup-card">
    <div class="setup-title">👤 管理者アカウント作成</div>
    <div class="setup-desc">
      管理者権限を持つアカウントを作成します。このアカウントは全ユーザーに自動フォローされます。
    </div>

    <?php if ($adminError): ?>
    <div class="flash flash-error">⚠️ <?= htmlspecialchars($adminError) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">ユーザーID（必須）</label>
        <input class="form-input" type="text" name="admin_id" 
               placeholder="例: yamachin" 
               pattern="[a-zA-Z0-9_]{3,30}"
               value="<?= htmlspecialchars($_POST['admin_id'] ?? 'yamachin') ?>"
               required>
        <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px">
          3〜30文字の英数字とアンダースコアのみ
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">表示名（任意）</label>
        <input class="form-input" type="text" name="admin_name" 
               placeholder="例: やまちん"
               value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>"
               maxlength="50">
      </div>

      <div class="form-group">
        <label class="form-label">自己紹介（任意）</label>
        <textarea class="form-input" name="admin_bio" 
                  placeholder="このSNSの管理者です"
                  maxlength="200"><?= htmlspecialchars($_POST['admin_bio'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">アバター色</label>
        <input type="color" name="admin_color" 
               value="<?= htmlspecialchars($_POST['admin_color'] ?? '#FF6B6B') ?>"
               style="width:100%;height:50px;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
      </div>

      <button type="submit" name="create_admin" value="1" class="btn btn-primary" style="width:100%">
        管理者アカウントを作成
      </button>
    </form>
  </div>
  <?php endif; ?>

  <!-- STEP 3: 完了 -->
  <?php if ($step === 'done'): ?>
  <div class="setup-card">
    <div style="text-align:center;margin-bottom:24px">
      <div style="font-size:3rem;margin-bottom:12px">🎉</div>
      <div class="setup-title">セットアップ完了！</div>
      <div class="setup-desc">
        管理者アカウント「<?= htmlspecialchars($_GET['admin'] ?? '') ?>」が作成されました。
      </div>
    </div>

    <div class="flash flash-error">
      ⚠️ <strong>重要：</strong> セキュリティのため、必ず <code>setup_admin.php</code> を削除してください
    </div>

    <div style="margin-top:24px;display:flex;flex-direction:column;gap:12px">
      <a href="register.php" class="btn btn-primary" style="display:flex;justify-content:center">
        新規登録ページへ →
      </a>
      <a href="index.php" class="btn btn-outline" style="display:flex;justify-content:center">
        トップページへ
      </a>
    </div>
  </div>
  <?php endif; ?>

</div>

</body>
</html>
