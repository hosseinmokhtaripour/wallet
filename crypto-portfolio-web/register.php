<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = '';
$displayName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!validate_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($displayName === '' || mb_strlen($displayName) < 2) {
        $errors[] = 'Display name must be at least 2 characters.';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $errors[] = 'Email is already registered. Please log in.';
        }
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name, created_at) VALUES (:email, :password_hash, :display_name, NOW())'
        );
        $insert->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
        ]);

        set_flash('success', 'Registration successful. Please log in.');
        header('Location: login.php');
        exit;
    }
}

render_header('Register');
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Create Account</h1>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" type="email" name="email" required value="<?= e($email) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="display_name">Display Name</label>
                        <input class="form-control" id="display_name" type="text" name="display_name" required value="<?= e($displayName) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" id="password" type="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input class="form-control" id="confirm_password" type="password" name="confirm_password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Register</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
