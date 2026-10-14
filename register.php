<?php
/**
 * HANÉ - Customer Registration Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_user_logged_in()) {
    header('Location: ' . BASE_URL . 'account/profile.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($name)) {
            $error = 'Full name is required.';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (empty($phone)) {
            $error = 'Phone number is required for delivery notifications.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            // Check duplicate email
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = 'This email address is already registered. Please sign in or use another email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
                $ins->execute([$name, $email, $phone, $hash]);
                $newUserId = (int)$pdo->lastInsertId();

                // Auto login new customer
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;

                set_flash('success', 'Your account has been created successfully! Welcome to HANÉ.');
                header('Location: ' . BASE_URL . 'account/profile.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Create an Account';
include __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 580px; margin: 3.5rem auto 5rem;">
    <div class="form-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="logo" style="justify-content: center; margin-bottom: 0.5rem;">HANÉ</div>
            <h1 style="font-size: 2.2rem; margin-bottom: 0.4rem;">Join HANÉ</h1>
            <p class="muted">Create an account to enjoy personalized styling, fast checkout, and order tracking.</p>
        </div>

        <?php if ($error): ?>
            <div style="background: var(--color-danger-bg); border-left: 4px solid var(--color-danger); color: var(--color-danger); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.9rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input class="form-control" type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Aanya Senanayake" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="name@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number *</label>
                    <input class="form-control" type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+94 77 123 4567" required>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input class="form-control" type="password" name="password" placeholder="At least 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <input class="form-control" type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>

            <button class="btn btn-primary btn-full btn-lg" type="submit" style="margin-top: 1rem;">
                Create Account <?= hane_icon('arrow-right') ?>
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.8rem; padding-top: 1.5rem; border-top: 1px solid var(--color-line); font-size: 0.92rem;">
            <span class="muted">Already have an account?</span> 
            <a href="<?= BASE_URL ?>login.php" style="font-weight: 600; color: var(--color-green); text-decoration: underline;">
                Sign In Here
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
