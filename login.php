<?php
// login.php - ログイン

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'セキュリティエラーが発生しました。';
    } else {
        $userId      = trim($_POST['user_id']  ?? '');
        $password    = $_POST['password']       ?? '';
        $fingerprint = trim($_POST['fingerprint'] ?? '');

        if (empty($userId) || empty($password)) {
            $error = 'ユーザーIDとパスワードを入力してください';
        } else {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                // ブルートフォース対策: わずかなスリープ
                usleep(300000); // 0.3秒
                $error = 'ユーザーIDまたはパスワードが違います';
            } else {
                // フィンガープリント更新（端末が変わった場合は上書き）
                if (!empty($fingerprint) && $user['fingerprint'] !== $fingerprint) {
                    // すでに別ユーザーに割り当てられていないか確認
                    $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE fingerprint = ?");
                    $stmtCheck->execute([$fingerprint]);
                    $existing = $stmtCheck->fetch();
                    if (!$existing) {
                        $pdo->prepare("UPDATE users SET fingerprint = ? WHERE user_id = ?")
                            ->execute([$fingerprint, $userId]);
                    }
                }

                $_SESSION['user_id'] = $userId;
                session_regenerate_id(true);
                header('Location: index.php');
                exit;
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
<title>ログイン - ミニSNS</title>
<link rel="stylesheet" href="assets/style.css">
<script src="https://fpjscdn.net/v3/ogWCYmBVDHhTWcLJYmq5/iife.min.js"></script>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-icon">✦</div>
      <div class="auth-title">ログイン</div>
      <div class="auth-subtitle">クローズドSNSにサインイン</div>
    </div>

    <!-- フィンガープリント自動ログインバナー -->
    <div class="autologin-banner" id="autologin-banner" style="display:none"></div>

    <?php if ($error): ?>
    <div class="flash flash-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="fingerprint" id="fingerprint-input">

      <div class="form-group">
        <label class="form-label" for="user_id">ユーザーID</label>
        <input class="form-input" type="text" id="user_id" name="user_id"
               placeholder="user_id"
               value="<?= e($_POST['user_id'] ?? '') ?>"
               required autocomplete="username">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">パスワード</label>
        <input class="form-input" type="password" id="password" name="password"
               placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">
        ログイン
      </button>
    </form>

    <div class="auth-footer">
      アカウントをお持ちでない方は
      <a href="register.php">新規登録</a>
    </div>
  </div>
</div>

<script src="assets/script.js"></script>
<script>
// フィンガープリント注入 + 自動ログインチェック
FP.get().then(fp => {
  document.getElementById('fingerprint-input').value = fp;
});
AutoLogin.check();
</script>
</body>
</html>
