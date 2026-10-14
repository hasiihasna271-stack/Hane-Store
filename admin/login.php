<?php
/**
 * HANÉ Admin - Secure Login
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_admin_logged_in()) {
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your administrator email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];

            set_flash('success', "Welcome back, {$admin['name']}!");
            header('Location: ' . BASE_URL . 'admin/index.php');
            exit;
        } else {
            $error = 'Invalid administrator credentials. Access denied.';
        }
    }
}
?>
<!doctype html>
<html lang="en" data-theme-storage-key="hane-admin-theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HANÉ Admin Portal | Sign In</title>
    <script src="<?= ASSET_PATH ?>js/theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/theme.js') ?>"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSET_PATH ?>css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
</head>
<body class="admin-body" style="background: var(--admin-sidebar-bg); display: flex; align-items: center; justify-content: center; min-height: 100vh;">

    <div style="width: min(440px, 92%); background: var(--admin-card-bg); color: var(--admin-text-main); border-radius: 20px; padding: 2.8rem; box-shadow: 0 20px 50px rgba(0,0,0,0.25);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-family: 'Cormorant Garamond', Georgia, serif; font-size: 2.4rem; font-weight: 700; color: var(--admin-accent); letter-spacing: 0.15em;">
                HANÉ
            </div>
            <span style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.2em; color: var(--admin-text-muted); font-weight: 600;">
                Management Console
            </span>
        </div>

        <?php if ($error): ?>
            <div style="background: var(--admin-error-bg); border-left: 4px solid var(--admin-danger); color: var(--admin-alert-error); padding: 0.9rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>admin/login.php">
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--admin-accent); margin-bottom: 0.4rem;">
                    Admin Email
                </label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? 'admin@hane.lk') ?>" required style="width: 100%; padding: 0.85rem 1rem; border: 1px solid var(--admin-border); border-radius: 10px; font-size: 0.95rem; outline: none; background: var(--admin-control-bg); color: var(--admin-text-main);" autofocus>
            </div>

            <div style="margin-bottom: 1.8rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--admin-accent); margin-bottom: 0.4rem;">
                    Password
                </label>
                <input type="password" name="password" value="admin123" required style="width: 100%; padding: 0.85rem 1rem; border: 1px solid var(--admin-border); border-radius: 10px; font-size: 0.95rem; outline: none; background: var(--admin-control-bg); color: var(--admin-text-main);">
            </div>

            <button type="submit" style="width: 100%; padding: 0.95rem; background: var(--admin-login-button-bg, #163F35); color: white; border: none; border-radius: 9999px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: background 0.2s ease;">
                Enter Dashboard &rarr;
            </button>
        </form>

        <div style="margin-top: 1.8rem; padding: 0.9rem; background: var(--admin-soft-bg); border-radius: 10px; font-size: 0.8rem; color: var(--admin-text-muted); text-align: center; border: 1px dashed var(--admin-border);">
            Default Admin: <strong>admin@hane.lk</strong> &bull; Pass: <strong>admin123</strong>
        </div>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="<?= BASE_URL ?>index.php" style="font-size: 0.85rem; color: var(--admin-text-muted); text-decoration: underline;">
                &larr; Return to Customer Storefront
            </a>
        </div>
    </div>

</body>
</html>
