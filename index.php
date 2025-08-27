<?php
require_once 'config.php';
require_once 'templates.php';

$action = $_GET['action'] ?? 'home';
$id = (int)($_GET['id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$message = '';
$error = '';

if ($action === 'toggle_favorite_ajax' && isset($_POST['id']) && isLoggedIn()) {
    header('Content-Type: application/json');
    $profile_id = (int)$_POST['id'];
    $user_id = $_SESSION['user_id'];
    
    $result = toggleFavorite($user_id, $profile_id);
    
    if ($result) {
        $isFavorited = isFavorited($user_id, $profile_id);
        $favoriteCount = getFavoriteCount($profile_id);
        
        echo json_encode([
            'success' => true,
            'isFavorited' => $isFavorited,
            'favoriteCount' => $favoriteCount
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

if (isset($_GET['profile_id']) && isset($_GET['slug'])) {
    $action = 'view';
    $id = (int)$_GET['profile_id'];
}

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($action === 'toggle_favorite' && $id && isLoggedIn()) {
    toggleFavorite($_SESSION['user_id'], $id);
    $redirect = $_GET['redirect'] ?? "?action=view&id=$id";
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete_comment' && isset($_GET['comment_id']) && isLoggedIn()) {
    $comment_id = (int)$_GET['comment_id'];
    $profile_id = (int)$_GET['profile_id'];
    
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $_SESSION['user_id']]);
    
    header("Location: ?action=view&id=$profile_id");
    exit;
}

// FIXED LOGIN HANDLING WITH BETTER DEBUG INFO
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            // First check if user exists at all
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Username not found';
            } elseif (!$user['approved']) {
                $error = 'Account is pending approval. Please wait for admin approval.';
            } elseif (!password_verify($password, $user['password'])) {
                $error = 'Incorrect password';
            } else {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Database error occurred. Please try again.';
        }
    }
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'captcha.php';
    $captcha = new CaptchaGenerator();
    
    if (!$captcha->verifyCaptcha($_POST['captcha'] ?? '')) {
        $error = 'Invalid security code. Please try again.';
    } else {
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        $data = [
            'username' => clean($_POST['username']),
            'password' => $password,
            'email' => clean($_POST['email']),
            'full_name' => clean($_POST['full_name'])
        ];
        
        $errors = validateRequired($data);
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        
        if (!validateEmail($data['email'])) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$data['username'], $data['email']]);
                
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                    $data['approved'] = 0; // Set to pending approval
                    
                    if (insert('users', $data)) {
                        echo registerForm('', true);
                        exit;
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
            }
        } else {
            $error = implode(', ', $errors);
        }
    }
}

if ($action === 'view' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && isLoggedIn()) {
    require_once 'captcha.php';
    $captcha = new CaptchaGenerator();
    
    if (!$captcha->verifyCaptcha($_POST['captcha'] ?? '')) {
        $error = 'Invalid security code. Please try again.';
    } else {
        $comment = clean($_POST['comment']);
        
        if (strlen($comment) >= 10) {
            $commentData = [
                'profile_id' => $id,
                'user_id' => $_SESSION['user_id'],
                'comment' => $comment,
                'original_comment' => $comment,
                'approved' => 1,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ];
            
            if (insert('comments', $commentData)) {
                $message = 'Comment posted successfully!';
            } else {
                $error = 'Failed to post comment';
            }
        } else {
            $error = 'Comment must be at least 10 characters long';
        }
    }
}

if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $data = [
        'full_name' => clean($_POST['full_name']),
        'email' => clean($_POST['email'])
    ];
    
    $errors = validateRequired($data);
    
    if (!validateEmail($data['email'])) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($errors)) {
        if (update('users', $data, $_SESSION['user_id'])) {
            $message = 'Profile updated successfully!';
        } else {
            $error = 'Update failed';
        }
    } else {
        $error = implode(', ', $errors);
    }
}

switch ($action) {
    case 'login':
        echo loginForm($error, $_POST ?? []);
        break;
        
    case 'register':
        echo registerForm($error, false, $_POST ?? []);
        break;
        
    case 'view':
        if (!$id) {
            header('Location: index.php');
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as artist_full_name, c.name as category_name
            FROM profiles p 
            JOIN users u ON p.user_id = u.id 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.approved = 1
        ");
        $stmt->execute([$id]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            header('Location: index.php');
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name, u.id as user_id
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.profile_id = ? AND c.approved = 1 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$id]);
        $comments = $stmt->fetchAll();
        
        $other_profiles = [];
        if ($profile['user_id']) {
            $stmt = $pdo->prepare("
                SELECT * FROM profiles 
                WHERE user_id = ? AND id != ? AND approved = 1 
                ORDER BY created_at DESC 
                LIMIT 4
            ");
            $stmt->execute([$profile['user_id'], $id]);
            $other_profiles = $stmt->fetchAll();
        }
        
        echo profileView($profile, $comments, isLoggedIn() ? $_SESSION : null, $other_profiles, $error, $message);
        break;
        
    case 'user_profile':
        $user_id = (int)($_GET['user_id'] ?? 0);
        if (!$user_id) {
            header('Location: index.php');
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND approved = 1");
        $stmt->execute([$user_id]);
        $viewed_user = $stmt->fetch();
        
        if (!$viewed_user) {
            header('Location: index.php');
            exit;
        }
        
        $user_profiles = getAll('profiles', 'user_id = ? AND approved = 1', [$user_id], 'created_at DESC');
        
        echo publicUserProfile($viewed_user, $user_profiles, isLoggedIn() ? $_SESSION : null);
        break;
        
    case 'profile':
        requireLogin();
        
        $user = getOne('users', $_SESSION['user_id']);
        $posts = getAll('profiles', 'user_id = ?', [$_SESSION['user_id']], 'created_at DESC');
        
        echo userProfile($user, $posts, $error, $message, $_POST ?? []);
        break;
        
    case 'search':
    default:
        $searchQuery = clean($_GET['search'] ?? '');
        $categoryFilter = (int)($_GET['category'] ?? 0);
        
        $categories = getAll('categories', '', [], 'name ASC');
        
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        
        if ($searchQuery || $categoryFilter) {
            $profiles = searchProfiles($searchQuery, $categoryFilter, ITEMS_PER_PAGE, $offset);
            $totalItems = searchCount($searchQuery, $categoryFilter);
        } else {
            $whereClause = 'p.approved = 1';
            if ($page == 1) {
                $whereClause .= ' AND p.featured = 0';
            }
            
            $stmt = $pdo->prepare("
                SELECT p.*, u.full_name as artist_full_name, c.name as category_name
                FROM profiles p 
                JOIN users u ON p.user_id = u.id 
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE $whereClause 
                ORDER BY p.created_at DESC 
                LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset
            ");
            $stmt->execute();
            $profiles = $stmt->fetchAll();
            
            if ($page == 1) {
                $totalItems = getCount('profiles', 'approved = 1 AND featured = 0');
            } else {
                $totalItems = getCount('profiles', 'approved = 1');
            }
        }
        
        $totalPages = ceil($totalItems / ITEMS_PER_PAGE);
        
        $user = isLoggedIn() ? $_SESSION : null;
        echo mainPage($profiles, $categories, $categoryFilter, $user, $searchQuery, $page, $totalPages, $totalItems);
        break;
}
?>