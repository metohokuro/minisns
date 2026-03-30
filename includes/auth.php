<?php
// includes/auth.php - 認証ヘルパー

require_once __DIR__ . '/db.php';

/**
 * セッション開始（安全設定付き）
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400 * 30, // 30日
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * ログイン済みかチェック
 */
function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * ログイン必須（未ログインはリダイレクト）
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 現在のログインユーザーID取得
 */
function getCurrentUserId(): ?string {
    startSecureSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * 現在のログインユーザー情報取得
 */
function getCurrentUser(): ?array {
    $userId = getCurrentUserId();
    if (!$userId) return null;

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * CSRFトークン生成（なければ新規作成）
 */
function getCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 */
function verifyCsrfToken(string $token): bool {
    startSecureSession();
    $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    // トークンをローテーション
    if ($valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

/**
 * CSRFトークン検証（失敗したら終了）
 */
function requireCsrfToken(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRFトークンが無効です']));
    }
}

/**
 * 管理者かどうかチェック
 */
function isAdmin(): bool {
    return getCurrentUserId() === 'yamachin';
}

/**
 * 管理者のみ許可（それ以外は403）
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        // JSON or HTML で返す
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=UTF-8');
            die(json_encode(['error' => '管理者権限が必要です']));
        }
        die('<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">
        <link rel="stylesheet" href="assets/style.css"></head><body>
        <div class="auth-page"><div class="auth-card">
        <div class="flash flash-error">⛔ このページは管理者専用です</div>
        <a href="index.php" class="btn btn-outline" style="margin-top:16px;display:flex;justify-content:center">← ホームへ戻る</a>
        </div></div></body></html>');
    }
}

/**
 * フィンガープリントからユーザー検索
 */
function findUserByFingerprint(string $fp): ?array {
    if (empty($fp)) return null;
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT user_id, display_name FROM users WHERE fingerprint = ?");
    $stmt->execute([$fp]);
    return $stmt->fetch() ?: null;
}
