<?php
/**
 * HANÉ - Forgot Password Request
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$msg = '';
$isSent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf()) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u) {
                $token = bin2hex(random_bytes(24));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $upd->execute([$token, $expiry, $u['id']]);
            }
            $isSent = true;
            $msg = 'If an account exists with that email address, a password reset link has been dispatched to your inbox.';
        } else {
            $msg = 'Please enter a valid email address.';
        }
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 500px; margin: 3.5rem auto 5rem;">
    <div class="form-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="logo" style="justify-content: center; margin-bottom: 0.5rem;">HANÉ</div>
            <h1 style="font-size: 2.2rem; margin-bottom: 0.4rem;">Reset Password</h1>
            <p class="muted">Enter your registered email address and we will assist you in restoring your account access.</p>
        </div>

        <?php if ($msg): ?>
            <div style="background: <?= $isSent ? 'var(--color-success-bg)' : 'var(--color-danger-bg)' ?>; border-left: 4px solid <?= $isSent ? 'var(--color-success)' : 'var(--color-danger)' ?>; color: <?= $isSent ? 'var(--color-success)' : 'var(--color-danger)' ?>; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.9rem;">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!$isSent): ?>
            <form method="post" action="<?= BASE_URL ?>forgot-password.php">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input class="form-control" type="email" name="email" placeholder="name@example.com" required autofocus>
                </div>

                <button class="btn btn-primary btn-full btn-lg" type="submit" style="margin-top: 1rem;">
                    Send Reset Instructions <?= hane_icon('arrow-right') ?>
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 1.8rem; padding-top: 1.5rem; border-top: 1px solid var(--color-line); font-size: 0.92rem;">
            <a href="<?= BASE_URL ?>login.php" style="font-weight: 600; color: var(--color-green); text-decoration: underline;">
                &larr; Return to Sign In
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
