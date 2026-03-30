<?php
// register.php - アカウント登録

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

// ログイン済みはリダイレクト
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFチェック
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'セキュリティエラーが発生しました。もう一度お試しください。';
    } else {
        $userId       = trim($_POST['user_id']      ?? '');
        $displayName  = trim($_POST['display_name'] ?? '');
        $password     = $_POST['password']           ?? '';
        $password2    = $_POST['password2']          ?? '';
        $fingerprint  = trim($_POST['fingerprint']  ?? '');
        $bio          = trim($_POST['bio']           ?? '');

        // バリデーション
        if (empty($userId)) {
            $error = 'ユーザーIDを入力してください';
        } elseif (!preg_match('/^[a-z0-9_]{3,30}$/i', $userId)) {
            $error = 'ユーザーIDは半角英数字とアンダースコアのみ、3〜30文字で設定してください';
        } elseif (empty($password)) {
            $error = 'パスワードを入力してください';
        } elseif (strlen($password) < 8) {
            $error = 'パスワードは8文字以上で設定してください';
        } elseif ($password !== $password2) {
            $error = 'パスワードが一致しません';
        } else {
            $pdo = getPDO();

            // フィンガープリント重複チェック
            if (!empty($fingerprint)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE fingerprint = ?");
                $stmt->execute([$fingerprint]);
                if ($stmt->fetch()) {
                    $error = 'この端末では既にアカウントが作成されています。ログインページへどうぞ。';
                }
            }

            if (empty($error)) {
                // ユーザーID重複チェック
                $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                if ($stmt->fetch()) {
                    $error = 'このユーザーIDは既に使用されています';
                } else {
                    // アカウント作成
                    $passwordHash  = password_hash($password, PASSWORD_DEFAULT);
                    $avatarColor   = generateAvatarColor($userId);
                    $fpValue       = !empty($fingerprint) ? $fingerprint : null;
                    $displayValue  = !empty($displayName) ? $displayName : $userId;

                    try {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare(
                            "INSERT INTO users (user_id, password_hash, fingerprint, display_name, bio, avatar_color)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $userId, $passwordHash, $fpValue,
                            $displayValue, $bio, $avatarColor
                        ]);

                        // yamachinを自動フォロー（yamachinが存在する場合）
                        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE user_id = 'yamachin'");
                        $stmtCheck->execute();
                        if ($stmtCheck->fetch() && $userId !== 'yamachin') {
                            $stmtFollow = $pdo->prepare(
                                "INSERT IGNORE INTO follows (follower_id, following_id)
                                 VALUES (?, 'yamachin')"
                            );
                            $stmtFollow->execute([$userId]);
                        }

                        $pdo->commit();

                        // セッションにセット
                        $_SESSION['user_id'] = $userId;
                        session_regenerate_id(true);

                        // 管理者アカウント（最初に作成されたユーザー）を自動フォロー
                        try {
                            $adminStmt = $pdo->query("
                                SELECT user_id FROM users 
                                WHERE user_id != '{$userId}' 
                                ORDER BY id ASC 
                                LIMIT 1
                            ");
                            $admin = $adminStmt->fetch();
                            if ($admin) {
                                $pdo->prepare(
                                    "INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)"
                                )->execute([$userId, $admin['user_id']]);
                            }
                        } catch (PDOException $e) {
                            // エラーは無視（自動フォロー失敗してもアカウント作成自体は成功）
                        }

                        header('Location: index.php?welcome=1');
                        exit;

                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log('登録エラー: ' . $e->getMessage());
                        $error = '登録中にエラーが発生しました。もう一度お試しください。';
                    }
                }
            }
        }
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<title>アカウント作成 - ミニSNS</title>
<link rel="stylesheet" href="assets/style.css">
<script src="https://fpjscdn.net/v3/ogWCYmBVDHhTWcLJYmq5/iife.min.js"></script>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-icon">✦</div>
      <div class="auth-title">アカウント作成</div>
      <div class="auth-subtitle">クローズドSNSへようこそ</div>
    </div>

    <?php if ($error): ?>
    <div class="flash flash-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="register-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="fingerprint" id="fingerprint-input">

      <div class="form-group">
        <label class="form-label" for="user_id">ユーザーID</label>
        <input class="form-input" type="text" id="user_id" name="user_id"
               placeholder="例: taro_123"
               value="<?= e($_POST['user_id'] ?? '') ?>"
               pattern="[a-zA-Z0-9_]{3,30}" maxlength="30" required autocomplete="username">
        <small style="font-size:.75rem;color:var(--text-muted)">半角英数字・アンダースコア / 3〜30文字</small>
      </div>

      <div class="form-group">
        <label class="form-label" for="display_name">表示名</label>
        <input class="form-input" type="text" id="display_name" name="display_name"
               placeholder="例: 太郎"
               value="<?= e($_POST['display_name'] ?? '') ?>"
               maxlength="50">
        <small style="font-size:.75rem;color:var(--text-muted)">空白の場合はユーザーIDが使われます</small>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">パスワード</label>
        <input class="form-input" type="password" id="password" name="password"
               placeholder="8文字以上" minlength="8" required autocomplete="new-password">
      </div>

      <div class="form-group">
        <label class="form-label" for="password2">パスワード（確認）</label>
        <input class="form-input" type="password" id="password2" name="password2"
               placeholder="もう一度入力" minlength="8" required autocomplete="new-password">
      </div>

      <div class="form-group">
        <label class="form-label" for="bio">自己紹介</label>
        <textarea class="form-input" id="bio" name="bio"
                  placeholder="自己紹介を書いてください（任意）"
                  maxlength="200"><?= e($_POST['bio'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">
        アカウント作成
      </button>
    </form>

    <div class="auth-footer">
      既にアカウントをお持ちですか？
      <a href="login.php">ログイン</a>
    </div>
  </div>
</div>

<script src="assets/script.js"></script>
<script>
// フォームにフィンガープリントをセット
FP.get().then(fp => {
  document.getElementById('fingerprint-input').value = fp;
});

// パスワード一致チェック
document.getElementById('register-form').addEventListener('submit', function(e) {
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password2').value;
  if (p1 !== p2) {
    e.preventDefault();
    alert('パスワードが一致しません');
  }
});
</script>
</body>
</html>
