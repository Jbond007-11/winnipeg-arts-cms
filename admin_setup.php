<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    
    $errors = [];
    
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($full_name)) $errors[] = 'Full name is required';
    
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    if (empty($errors)) {
        try {
            // Check if admin already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $errors[] = 'An admin account already exists. Delete this file.';
            } else {
                // Create admin account
                $adminData = [
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'email' => $email,
                    'full_name' => $full_name,
                    'role' => 'admin',
                    'approved' => 1
                ];
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, approved) VALUES (?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([
                    $adminData['username'],
                    $adminData['password'],
                    $adminData['email'],
                    $adminData['full_name'],
                    $adminData['role'],
                    $adminData['approved']
                ]);
                
                if ($success) {
                    $success_message = "Admin account created successfully!<br>
                                     Username: <strong>$username</strong><br>
                                     Password: <strong>[hidden]</strong><br>
                                     <br><strong>IMPORTANT:</strong> Delete this admin_setup.php file now for security!<br>
                                     <a href='index.php?action=login' class='btn btn-success'>Go to Login</a>";
                } else {
                    $errors[] = 'Failed to create admin account';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Check if admin exists
$adminExists = false;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn() > 0;
} catch (PDOException $e) {
    $errors[] = 'Cannot connect to database: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - Winnipeg Arts CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white text-center">
                        <h4><i class="bi bi-shield-lock"></i> Admin Account Setup</h4>
                        <small>⚠️ Delete this file after creating admin account!</small>
                    </div>
                    <div class="card-body">
                        
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?= $success_message ?>
                            </div>
                        <?php elseif ($adminExists): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <strong>Admin account already exists!</strong><br>
                                For security reasons, please delete this file immediately.<br><br>
                                <a href="index.php?action=login" class="btn btn-primary">Go to Login</a>
                            </div>
                        <?php else: ?>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <strong>Errors:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?= htmlspecialchars($_POST['full_name'] ?? 'Administrator') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? 'admin@example.com') ?>" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Password * (minimum 6 characters)</label>
                                    <input type="password" class="form-control" name="password" minlength="6" required>
                                </div>
                                
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-person-plus"></i> Create Admin Account
                                </button>
                            </form>
                            
                            <div class="alert alert-info mt-4">
                                <strong><i class="bi bi-info-circle"></i> Security Notice:</strong><br>
                                This script should only be used once during initial setup.
                                Delete this file immediately after creating your admin account.
                            </div>
                            
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>