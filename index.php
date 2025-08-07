<?php
require_once 'config.php';
require_once 'templates.php';

// routing with SEO-friendly URLs
$action = $_GET['action'] ?? 'home';
$id = (int)($_GET['id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$message = '';
$error = '';

// Handle AJAX request for toggling favorites
if ($action === 'toggle_favorite_ajax' && isset($_POST['id']) && isLoggedIn()) {
    header('Content-Type: application/json');
    $profile_id = (int)$_POST['id'];
    $user_id = $_SESSION['user_id'];
    
    // Toggle the favorite status in the database
    $result = toggleFavorite($user_id, $profile_id);
    
    if ($result) {
        // After toggling, get the new status and count
        $isFavorited = isFavorited($user_id, $profile_id);
        $favoriteCount = getFavoriteCount($profile_id);
        
        // Send a JSON response back to the browser
        echo json_encode([
            'success' => true,
            'isFavorited' => $isFavorited,
            'favoriteCount' => $favoriteCount
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit; // stop script execution after sending the JSON response
}

// Handle URL rewriting for SEO-friendly URLs
if (isset($_GET['profile_id']) && isset($_GET['slug'])) {
    $action = 'view';
    $id = (int)$_GET['profile_id'];
}

// Handle logout
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle toggle favorite (primarily be used by non-AJAX fallbacks)
if ($action === 'toggle_favorite' && $id && isLoggedIn()) {
    toggleFavorite($_SESSION['user_id'], $id);
    $redirect = $_GET['redirect'] ?? "?action=view&id=$id";
    header('Location: ' . $redirect);
    exit;
}

// Handle comment deletion
if ($action === 'delete_comment' && isset($_GET['comment_id']) && isLoggedIn()) {
    $comment_id = (int)$_GET['comment_id'];
    $profile_id = (int)$_GET['profile_id'];
    
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $_SESSION['user_id']]);
    
    header("Location: ?action=view&id=$profile_id");
    exit;
}

// Handle login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND approved = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials or account not approved';
    }
}

// registration with CAPTCHA
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CAPTCHA first
    require_once 'captcha.php';
    $captcha = new CaptchaGenerator();
    
    if (!$captcha->verifyCaptcha($_POST['captcha'] ?? '')) {
        $error = 'Invalid security code. Please try again.';
    } else {
        $data = [
            'username' => clean($_POST['username']),
            'password' => $_POST['password'],
            'email' => clean($_POST['email']),
            'full_name' => clean($_POST['full_name'])
        ];
        
        //validation
        $errors = validateRequired($data);
        
        // Check password confirm
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $errors[] = 'Passwords do not match';
        }
        
        // Validate email format
        if (!validateEmail($data['email'])) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($errors)) {
            // ccheck if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$data['username'], $data['email']]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                
                if (insert('users', $data)) {
                    echo registerForm('', true);
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } else {
            $error = implode(', ', $errors);
        }
    }
}

// Comment submission with CAPTCHA
if ($action === 'view' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && isLoggedIn()) {
    // Verify CAPTCHA
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
                'original_comment' => $comment, // Store original for moderation
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

// Handle profile update
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

// Get data for different pages
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
        
        // Get profile with enhanced data
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
        
        // Get comments with enhanced data
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name, u.id as user_id
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.profile_id = ? AND c.approved = 1 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$id]);
        $comments = $stmt->fetchAll();
        
        // Get other approved profiles from the same artist
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
        
        // Pass the new data to the profileView function
        echo profileView($profile, $comments, isLoggedIn() ? $_SESSION : null, $other_profiles, $error, $message);
        break;
        
    case 'user_profile':
        // handle viewing another users profile
        $user_id = (int)($_GET['user_id'] ?? 0);
        if (!$user_id) {
            header('Location: index.php');
            exit;
        }
        
        // Get user info
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND approved = 1");
        $stmt->execute([$user_id]);
        $viewed_user = $stmt->fetch();
        
        if (!$viewed_user) {
            header('Location: index.php');
            exit;
        }
        
        // Get users approved profiles
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
    default: // home and search
        $searchQuery = clean($_GET['search'] ?? '');
        $categoryFilter = (int)($_GET['category'] ?? 0);
        
        // Get categories
        $categories = getAll('categories', '', [], 'name ASC');
        
        //search with pagination
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        
        if ($searchQuery || $categoryFilter) {
            $profiles = searchProfiles($searchQuery, $categoryFilter, ITEMS_PER_PAGE, $offset);
            $totalItems = searchCount($searchQuery, $categoryFilter);
        } else {
            // On homepageexclude featured profiles from main grid to avoid duplicates
            $whereClause = 'p.approved = 1';
            if ($page == 1) {
                // On first page exclude featured profiles since they're shown in featured section
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
            
            // Adjust total count based on whether am excluding featured
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