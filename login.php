<?php
/**
 * HANÉ - Customer Login Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_user_logged_in()) {
    header('Location: ' . BASE_URL . 'account/profile.php');
    exit;
}

$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? BASE_URL . 'account/profile.php');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];

                // Sync session wishlist to database
                if (!empty($_SESSION['wishlist'])) {
                    $insW = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
                    foreach (array_keys($_SESSION['wishlist']) as $pId) {
                        $insW->execute([$user['id'], $pId]);
                    }
                    unset($_SESSION['wishlist']);
                }

                set_flash('success', "Welcome back, {$user['name']}!");
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid email address or password. Please verify your credentials.';
            }
        }
    }
}

$pageTitle = 'Sign In to Your Account';
include __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 520px; margin: 3.5rem auto 5rem;">
    <div class="form-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="logo" style="justify-content: center; margin-bottom: 0.5rem;">HANÉ</div>
            <h1 style="font-size: 2.2rem; margin-bottom: 0.4rem;">Customer Sign In</h1>
            <p class="muted">Access your orders, saved addresses, and personal wishlist.</p>
        </div>

        <?php if ($error): ?>
            <div style="background: var(--color-danger-bg); border-left: 4px solid var(--color-danger); color: var(--color-danger); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.9rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>login.php">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="name@example.com" required autofocus>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                    <label class="form-label" style="margin-bottom: 0;">Password</label>
                    <a href="<?= BASE_URL ?>forgot-password.php" style="font-size: 0.8rem; text-decoration: underline; color: var(--color-muted);">Forgot Password?</a>
                </div>
                <input class="form-control" type="password" name="password" placeholder="••••••••" required>
            </div>

            <button class="btn btn-primary btn-full btn-lg" type="submit" style="margin-top: 1rem;">
                Sign In <?= hane_icon('arrow-right') ?>
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.8rem; padding-top: 1.5rem; border-top: 1px solid var(--color-line); font-size: 0.92rem;">
            <span class="muted">New to HANÉ?</span> 
            <a href="<?= BASE_URL ?>register.php" style="font-weight: 600; color: var(--color-green); text-decoration: underline;">
                Create an Account
            </a>
        </div>

        <div style="background: var(--color-beige-light); border-radius: var(--radius-md); padding: 1rem; margin-top: 1.5rem; font-size: 0.82rem; color: var(--color-charcoal);">
            <strong>Demo Customer Account:</strong><br>
            Email: <code>customer@hane.lk</code><br>
            Password: <code>customer123</code>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
