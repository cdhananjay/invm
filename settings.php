<?php
/**
 * Account Settings Page
 * Profile management, password change, and account actions
 */

require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$conn = getDbConnection();

$error = '';
$success = '';
$profileError = '';
$profileSuccess = '';
$passwordError = '';
$passwordSuccess = '';

// Show success message after redirect
if (isset($_GET['updated'])) {
    $profileSuccess = 'Profile updated successfully';
}

// Handle profile update
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    if (empty($name) || empty($email)) {
        $profileError = 'Name and email are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Please enter a valid email address';
    } else {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $profileError = 'This email is already registered';
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $userId);

            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                header("Location: settings.php?updated=1");
                exit;
            } else {
                $profileError = 'Failed to update profile';
            }
        }
        $stmt->close();
    }
}

// Handle password change
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        $passwordError = 'Both current and new password are required';
    } elseif (strlen($newPassword) < 8) {
        $passwordError = 'New password must be at least 8 characters';
    } else {
        // Verify current password
        if (password_verify($currentPassword, $user['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $newHash, $userId);

            if ($stmt->execute()) {
                $passwordSuccess = 'Password updated successfully';
            } else {
                $passwordError = 'Failed to update password';
            }
            $stmt->close();
        } else {
            $passwordError = 'Current password is incorrect';
        }
    }
}

// Handle account deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (password_verify($confirmPassword, $user['password_hash'])) {
        // Delete user (cascade will delete all items)
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            // Destroy session and redirect to login
            $_SESSION = array();
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            session_destroy();
            header("Location: login.php?deleted=1");
            exit;
        }
        $stmt->close();
    } else {
        $error = 'Password confirmation incorrect';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">SA</div>
                <div class="sidebar-brand">
                    <h1><?php echo APP_NAME; ?></h1>
                    <span>Inventory Hub</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="inventory.php" class="nav-item">
                    <span class="icon">📦</span>
                    <span>Inventory</span>
                </a>
                <a href="settings.php" class="nav-item active">
                    <span class="icon">⚙️</span>
                    <span>Settings</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div></div>
                <div class="header-actions">
                    <div class="user-menu">
                        <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title">Account Settings</h1>
                    <p class="page-subtitle">Manage your profile details, security preferences, and account status.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Profile Information -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3 class="settings-section-title">Profile Information</h3>
                        <p class="settings-section-desc">Update your personal details. This information will be displayed on your inventory logs.</p>
                    </div>
                    <div class="settings-section-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">

                            <?php if ($profileError): ?>
                                <div class="alert alert-error"><?php echo htmlspecialchars($profileError); ?></div>
                            <?php endif; ?>

                            <?php if ($profileSuccess): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($profileSuccess); ?></div>
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>

                                <div class="form-group full-width">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>

                            <div class="settings-section-footer">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3 class="settings-section-title">Security</h3>
                        <p class="settings-section-desc">Ensure your account is using a long, random password to stay secure.</p>
                    </div>
                    <div class="settings-section-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">

                            <?php if ($passwordError): ?>
                                <div class="alert alert-error"><?php echo htmlspecialchars($passwordError); ?></div>
                            <?php endif; ?>

                            <?php if ($passwordSuccess): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($passwordSuccess); ?></div>
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>

                                <div class="form-group full-width">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" minlength="8" required>
                                    <div class="form-hint">Minimum 8 characters</div>
                                </div>
                            </div>

                            <div class="settings-section-footer">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Actions -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3 class="settings-section-title">Account Actions</h3>
                        <p class="settings-section-desc">Manage your active session or permanently remove your account data.</p>
                    </div>
                    <div class="settings-section-body" style="padding: 0;">
                        <!-- Sign Out -->
                        <div style="padding: 24px; background: var(--bg-color); border-bottom: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Sign Out</div>
                                    <div style="font-size: 13px; color: var(--text-secondary);">End your current session on this device.</div>
                                </div>
                                <a href="logout.php" class="btn btn-secondary">Sign Out</a>
                            </div>
                        </div>

                        <!-- Delete Account -->
                        <div class="delete-section" style="padding: 24px;">
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone and all your inventory data will be permanently deleted.');">
                                <input type="hidden" name="action" value="delete_account">

                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                                    <div>
                                        <div style="font-weight: 600; color: #991b1b; margin-bottom: 4px;">Delete Account</div>
                                        <div style="font-size: 13px; color: #991b1b;">Permanently remove your account and all associated inventory data.</div>
                                    </div>
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <input type="password" name="confirm_password" placeholder="Confirm password" style="padding: 10px 14px; border: 1px solid #fca5a5; border-radius: var(--radius); font-size: 13px;" required>
                                        <button type="submit" class="btn btn-danger">Delete Account</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="app.js"></script>
</body>
</html>
