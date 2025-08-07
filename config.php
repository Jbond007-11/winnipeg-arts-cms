<?php
// DB Config
$host = 'localhost';
$dbname = 'wpg_arts_cms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Constants
define('UPLOAD_DIR', 'uploads/');
define('THUMB_DIR', 'uploads/thumbs/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('THUMB_WIDTH', 300);
define('THUMB_HEIGHT', 200);
define('ITEMS_PER_PAGE', 6);

// Create directories if they don't exist
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(THUMB_DIR)) mkdir(THUMB_DIR, 0755, true);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php?action=login');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

//Sanitization and validation
function clean($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateRequired($fields) {
    $errors = [];
    foreach($fields as $name => $value) {
        if (empty(trim($value))) {
            $errors[] = ucfirst(str_replace('_', ' ', $name)) . " is required";
        }
    }
    return $errors;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// slug gen
function generateSlug($text, $table = 'profiles') {
    global $pdo;
    
    // Basic slug creation
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    $originalSlug = $slug;
    $counter = 1;
    
    // Check for uniqueness
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE slug = ?");
        $stmt->execute([$slug]);
        
        if (!$stmt->fetch()) {
            return $slug;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
}

// image handling with resizing
function uploadImage($file, $old_filename = null, $old_thumb = null) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => $old_filename, 'thumbnail' => $old_thumb];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Upload failed or file too large'];
    }
    
    // Validate image
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        return ['success' => false, 'error' => 'Invalid image file'];
    }
    
    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($imageInfo['mime'], $allowed)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF allowed.'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '.' . $ext;
    $thumbName = 'thumb_' . $filename;
    
    $fullPath = UPLOAD_DIR . $filename;
    $thumbPath = THUMB_DIR . $thumbName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    
    // Create thumbnail
    if (!createThumbnail($fullPath, $thumbPath, THUMB_WIDTH, THUMB_HEIGHT)) {
        // If thumbnail creation fails still return success with main image
        error_log("Failed to create thumbnail for: $filename");
    }
    
    // Delete old files
    if ($old_filename && file_exists(UPLOAD_DIR . $old_filename)) {
        unlink(UPLOAD_DIR . $old_filename);
    }
    if ($old_thumb && file_exists(THUMB_DIR . $old_thumb)) {
        unlink(THUMB_DIR . $old_thumb);
    }
    
    return ['success' => true, 'filename' => $filename, 'thumbnail' => $thumbName];
}

// Image resizing function using GD
function createThumbnail($source, $destination, $width, $height) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) return false;
    
    list($origWidth, $origHeight, $type) = $imageInfo;
    
    // Calculate new dimensions maintaining aspect ratio
    $ratio = min($width / $origWidth, $height / $origHeight);
    $newWidth = round($origWidth * $ratio);
    $newHeight = round($origHeight * $ratio);
    
    // Create image resources
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImg = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImg = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $sourceImg = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImg) return false;
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    // Handle transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumb, $sourceImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    
    // Save thumbnail
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumb, $destination, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumb, $destination, 9);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($thumb, $destination);
            break;
    }
    
    imagedestroy($sourceImg);
    imagedestroy($thumb);
    
    return $result;
}

// Get image URLs
function getImageUrl($filename) {
    if (!$filename || !file_exists(UPLOAD_DIR . $filename)) {
        return 'https://via.placeholder.com/400x300/6f42c1/ffffff?text=No+Image';
    }
    return UPLOAD_DIR . $filename;
}

function getThumbUrl($filename) {
    if (!$filename || !file_exists(THUMB_DIR . $filename)) {
        return 'https://via.placeholder.com/300x200/6f42c1/ffffff?text=No+Image';
    }
    return THUMB_DIR . $filename;
}

// db helpers with sorting and pagination
function getAll($table, $where = '', $params = [], $orderBy = 'created_at DESC', $limit = null, $offset = null) {
    global $pdo;
    
    $sql = "SELECT * FROM $table";
    if ($where) $sql .= " WHERE $where";
    if ($orderBy) $sql .= " ORDER BY $orderBy";
    if ($limit) $sql .= " LIMIT $limit";
    if ($offset) $sql .= " OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getCount($table, $where = '', $params = []) {
    global $pdo;
    $sql = "SELECT COUNT(*) FROM $table" . ($where ? " WHERE $where" : "");
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function getOne($table, $id, $idField = 'id') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $idField = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function insert($table, $data) {
    global $pdo;
    $columns = implode(',', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

function update($table, $data, $id, $idField = 'id') {
    global $pdo;
    $set = implode(' = ?, ', array_keys($data)) . ' = ?';
    $sql = "UPDATE $table SET $set WHERE $idField = ?";
    $values = array_values($data);
    $values[] = $id;
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

function delete($table, $id, $idField = 'id') {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM $table WHERE $idField = ?");
    return $stmt->execute([$id]);
}

// search functionality
function searchProfiles($query, $categoryId = null, $limit = ITEMS_PER_PAGE, $offset = 0) {
    global $pdo;
    
    // p.approved to resolve ambiguity cuz both users and profiles tables have an 'approved' colum
    $where = "p.approved = 1";
    $params = [];
    
    if ($query) {
        $where .= " AND (p.artist_name LIKE ? OR p.bio LIKE ? OR p.specialty LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($categoryId) {
        $where .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }
    
    //  p.created_at in ORDER BY to resolve ambiguity
    $sql = "SELECT p.*, u.full_name as artist_full_name, c.name as category_name
            FROM profiles p 
            JOIN users u ON p.user_id = u.id 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE $where 
            ORDER BY p.created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function searchCount($query, $categoryId = null) {
    global $pdo;
    
    // This query only uses one table
    $where = "approved = 1";
    $params = [];
    
    if ($query) {
        $where .= " AND (artist_name LIKE ? OR bio LIKE ? OR specialty LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($categoryId) {
        $where .= " AND category_id = ?";
        $params[] = $categoryId;
    }
    
    $sql = "SELECT COUNT(*) FROM profiles WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Pagination helper
function getPagination($currentPage, $totalItems, $itemsPerPage, $baseUrl) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    if ($totalPages <= 1) return '';
    
    $pagination = '<nav><ul class="pagination justify-content-center">';
    
    // Previous
    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        $pagination .= "<li class='page-item'><a class='page-link' href='$baseUrl&page=$prevPage'>Previous</a></li>";
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $pagination .= "<li class='page-item'><a class='page-link' href='$baseUrl&page=1'>1</a></li>";
        if ($start > 2) $pagination .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $pagination .= "<li class='page-item $active'><a class='page-link' href='$baseUrl&page=$i'>$i</a></li>";
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $pagination .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        $pagination .= "<li class='page-item'><a class='page-link' href='$baseUrl&page=$totalPages'>$totalPages</a></li>";
    }
    
    // Next
    if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        $pagination .= "<li class='page-item'><a class='page-link' href='$baseUrl&page=$nextPage'>Next</a></li>";
    }
    
    $pagination .= '</ul></nav>';
    return $pagination;
}

// Favorite system
function toggleFavorite($user_id, $profile_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND profile_id = ?");
    $stmt->execute([$user_id, $profile_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND profile_id = ?");
    } else {
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, profile_id) VALUES (?, ?)");
    }
    return $stmt->execute([$user_id, $profile_id]);
}

function isFavorited($user_id, $profile_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND profile_id = ?");
    $stmt->execute([$user_id, $profile_id]);
    return $stmt->fetch() !== false;
}

// Get counts
function getFavoriteCount($profile_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE profile_id = ?");
    $stmt->execute([$profile_id]);
    return $stmt->fetchColumn();
}

function getCommentCount($profile_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE profile_id = ? AND approved = 1");
    $stmt->execute([$profile_id]);
    return $stmt->fetchColumn();
}

// comment moderation
function disemvowel($text) {
    return preg_replace('/[aeiouAEIOU]/', '', $text);
}

function moderateComment($comment_id, $action) {
    global $pdo;
    
    if ($action === 'disemvowel') {
        $stmt = $pdo->prepare("SELECT comment, original_comment FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();
        
        if ($comment) {
            $disemvoweled = disemvowel($comment['original_comment']);
            $stmt = $pdo->prepare("UPDATE comments SET comment = ?, moderated = 1 WHERE id = ?");
            return $stmt->execute([$disemvoweled, $comment_id]);
        }
    } elseif ($action === 'restore') {
        $stmt = $pdo->prepare("UPDATE comments SET comment = original_comment, moderated = 0 WHERE id = ?");
        return $stmt->execute([$comment_id]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        return $stmt->execute([$comment_id]);
    }
    
    return false;
}

// Utility functions
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2629746) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}

function truncate($text, $length = 150) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

// SEO URL generation
function getSEOUrl($action, $id, $slug = '') {
    switch ($action) {
        case 'profile':
            // The action to view a profile is 'view' in index.php
            return "index.php?action=view&id=$id";
        case 'category':
            // The action to view a category is handled by the default/search case in index.php
            return "index.php?action=search&category=$id";
        case 'user_profile':
            // NEW: Generate URL for user profile
            return "index.php?action=user_profile&user_id=$id";
        default:
            return "index.php?action=$action" . ($id ? "&id=$id" : '');
    }
}

// helper function to generate user profile URL
function getUserProfileUrl($user_id, $username = '') {
    return "index.php?action=user_profile&user_id=$user_id";
}

// ppdate view count
function incrementViewCount($profile_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE profiles SET view_count = view_count + 1 WHERE id = ?");
    return $stmt->execute([$profile_id]);
}
?>