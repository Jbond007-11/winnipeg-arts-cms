<?php
require_once 'config.php';
require_once 'templates.php';

requireLogin();

$action = $_GET['action'] ?? 'create';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Handle user approval/rejection (admin only)
if (isAdmin() && $action === 'approve_user' && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    update('users', ['approved' => 1], $user_id);
    header('Location: ?action=moderate');
    exit;
}

if (isAdmin() && $action === 'reject_user' && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id]);
    header('Location: ?action=moderate');
    exit;
}

// Handle comment moderation (admin only)
if (isAdmin() && $action === 'disemvowel_comment' && $id) {
    moderateComment($id, 'disemvowel');
    $profile_id = (int)$_GET['profile_id'];
    header("Location: index.php?action=view&id=$profile_id");
    exit;
}

if (isAdmin() && $action === 'restore_comment' && $id) {
    moderateComment($id, 'restore');
    $profile_id = (int)$_GET['profile_id'];
    header("Location: index.php?action=view&id=$profile_id");
    exit;
}

// Handle category management (admin only)
if (isAdmin() && $action === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => clean($_POST['name']),
        'description' => clean($_POST['description']),
        'slug' => generateSlug($_POST['name'], 'categories')
    ];
    
    $errors = validateRequired(['name' => $data['name']]);
    
    if (empty($errors)) {
        if (insert('categories', $data)) {
            $success = 'Category added successfully!';
        } else {
            $error = 'Failed to add category';
        }
    } else {
        $error = implode(', ', $errors);
    }
}

if (isAdmin() && $action === 'edit_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => clean($_POST['name']),
        'description' => clean($_POST['description']),
        'slug' => generateSlug($_POST['name'], 'categories')
    ];
    
    $errors = validateRequired(['name' => $data['name']]);
    
    if (empty($errors)) {
        if (update('categories', $data, $id)) {
            $success = 'Category updated successfully!';
        } else {
            $error = 'Failed to update category';
        }
    } else {
        $error = implode(', ', $errors);
    }
}

if (isAdmin() && $action === 'delete_category' && $id) {
    // Check if category is in use
    $count = getCount('profiles', 'category_id = ?', [$id]);
    
    if ($count > 0) {
        $error = 'Cannot delete category - it is being used by ' . $count . ' profiles';
    } else {
        if (delete('categories', $id)) {
            $success = 'Category deleted successfully!';
        } else {
            $error = 'Failed to delete category';
        }
    }
    
    header('Location: ?action=categories&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Delete profile
    if ($action === 'delete' && isset($_POST['confirm_delete'])) {
        $profile = getOne('profiles', $id);
        if ($profile && (isAdmin() || $profile['user_id'] == $_SESSION['user_id'])) {
            // Delete image files
            if ($profile['image_filename'] && file_exists(UPLOAD_DIR . $profile['image_filename'])) {
                unlink(UPLOAD_DIR . $profile['image_filename']);
            }
            if ($profile['thumbnail_filename'] && file_exists(THUMB_DIR . $profile['thumbnail_filename'])) {
                unlink(THUMB_DIR . $profile['thumbnail_filename']);
            }
            
            // Delete from database
            if (delete('profiles', $id)) {
                $success = 'Profile deleted successfully';
            } else {
                $error = 'Failed to delete profile';
            }
        }
    }

    // Create/Edit profile
    else if ($action === 'create' || $action === 'edit') {
        
        // Prevent admins from creating new posts (admin should only moderate)
        if ($action === 'create' && isAdmin()) {
            header('Location: ?action=moderate');
            exit;
        }
        
        $data = [
            'artist_name' => clean($_POST['artist_name']),
            'bio' => $_POST['bio'], // Don't clean this as it may contain HTML from WYSIWYG
            'category_id' => (int)$_POST['category_id'],
            'contact_info' => clean($_POST['contact_info']),
            'specialty' => clean($_POST['specialty'])
        ];
        
        // Generate slug
        if ($action === 'create') {
            $data['slug'] = generateSlug($data['artist_name']);
        }
        
        // Approval and featured status (admin only)
        if (isAdmin()) {
            $data['approved'] = isset($_POST['approved']) ? 1 : 0;
            $data['featured'] = isset($_POST['featured']) ? 1 : 0;
        } else {
            $data['approved'] = 1; // auto-approve for regular users
        }
        
        $errors = validateRequired([
            'artist_name' => $data['artist_name'],
            'bio' => strip_tags($data['bio']),
            'contact_info' => $data['contact_info']
        ]);
        
        if ($data['category_id'] == 0) {
            $errors[] = 'Please select a category';
        }
        
        // Handle image upload with resizing
        $imageResult = null;
        if (isset($_FILES['artwork_image'])) {
            $profile = $action === 'edit' ? getOne('profiles', $id) : null;
            $oldImage = $profile ? $profile['image_filename'] : null;
            $oldThumb = $profile ? $profile['thumbnail_filename'] : null;
            
            // Handle image removal
            if (isset($_POST['remove_image']) && $oldImage) {
                if (file_exists(UPLOAD_DIR . $oldImage)) {
                    unlink(UPLOAD_DIR . $oldImage);
                }
                if ($oldThumb && file_exists(THUMB_DIR . $oldThumb)) {
                    unlink(THUMB_DIR . $oldThumb);
                }
                $data['image_filename'] = null;
                $data['thumbnail_filename'] = null;
            } else {
                $imageResult = uploadImage($_FILES['artwork_image'], $oldImage, $oldThumb);
                if (!$imageResult['success']) {
                    $errors[] = $imageResult['error'];
                } else {
                    $data['image_filename'] = $imageResult['filename'];
                    $data['thumbnail_filename'] = $imageResult['thumbnail'];
                }
            }
        }
        
        if (empty($errors)) {
            if ($action === 'edit') {
                $profile = getOne('profiles', $id);
                if ($profile && (isAdmin() || $profile['user_id'] == $_SESSION['user_id'])) {
                    // Remove null values for update
                    $updateData = array_filter($data, function($value) {
                        return $value !== null;
                    });
                    
                    if (update('profiles', $updateData, $id)) {
                        $success = 'Profile updated successfully!';
                    } else {
                        $error = 'Update failed';
                    }
                }
            } else {
                $data['user_id'] = $_SESSION['user_id'];
                if (insert('profiles', $data)) {
                    $success = 'Profile created successfully!';
                    $_POST = []; // Clear form
                } else {
                    $error = 'Creation failed';
                }
            }
        } else {
            $error = implode(', ', $errors);
        }
    }
}

// Handle different actions
switch ($action) {
    case 'moderate':
        requireAdmin();
        
        $pending_users = getAll('users', 'approved = 0 AND role != "admin"');
        echo moderationPage($pending_users);
        break;
        
    case 'categories':
        requireAdmin();
        
        $error = $_GET['error'] ?? $error;
        $success = $_GET['success'] ?? $success;
        
        echo categoryManagementPage($error, $success, $_POST ?? []);
        break;
        
    case 'edit_category':
        requireAdmin();
        
        $category = getOne('categories', $id);
        if (!$category) {
            header('Location: ?action=categories');
            exit;
        }
        
        echo categoryForm('edit', $category, $error, $success, $_POST);
        break;
        
    case 'edit':
        $profile = getOne('profiles', $id);
        if (!$profile || (!isAdmin() && $profile['user_id'] != $_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
        
        $categories = getAll('categories', '', [], 'name ASC');
        echo profileForm('edit', $profile, $categories, $error, $success, $_POST);
        break;
        
    case 'delete':
        $profile = getOne('profiles', $id);
        if (!$profile || (!isAdmin() && $profile['user_id'] != $_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
        
        echo deleteConfirmationPage($profile, $error, $success);
        break;
        
    default: // create
        // Redirect admins away from create (fck off and moderate)
        if (isAdmin()) {
            header('Location: ?action=moderate');
            exit;
        }
        
        $categories = getAll('categories', '', [], 'name ASC');
        echo profileForm('create', null, $categories, $error, $success, $_POST);
        break;
}

// Additional template functions
function categoryManagementPage($error = '', $success = '', $data = []) {
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'Category Management']
    ];
    
    $categories = getAll('categories', '', [], 'name ASC');
    
    $categoryList = '';
    foreach ($categories as $cat) {
        $profileCount = getCount('profiles', 'category_id = ?', [$cat['id']]);
        $categoryList .= "
            <tr>
                <td>
                    <strong>{$cat['name']}</strong>
                    <br><small class='text-muted'>{$cat['description']}</small>
                </td>
                <td>
                    <span class='badge bg-info'>{$profileCount} profiles</span>
                </td>
                <td>
                    <code>/{$cat['slug']}</code>
                </td>
                <td class='text-end'>
                    <a href='?action=edit_category&id={$cat['id']}' class='btn btn-sm btn-outline-primary'>
                        <i class='bi bi-pencil'></i> Edit
                    </a>
                    " . ($profileCount == 0 ? 
                        "<a href='?action=delete_category&id={$cat['id']}' class='btn btn-sm btn-outline-danger' 
                           onclick='return confirm(\"Delete this category?\")'>
                            <i class='bi bi-trash'></i> Delete
                        </a>" : 
                        "<span class='btn btn-sm btn-secondary disabled'>
                            <i class='bi bi-lock'></i> In Use
                        </span>"
                    ) . "
                </td>
            </tr>";
    }
    
    $content = "
        <div class='d-flex justify-content-between align-items-center mb-4'>
            <h1><i class='bi bi-tags'></i> Category Management</h1>
            <a href='?action=moderate' class='btn btn-outline-secondary'>
                <i class='bi bi-arrow-left'></i> Back to Moderation
            </a>
        </div>
        
        " . showMessage('error', $error) . "
        " . showMessage('success', $success) . "
        
        <div class='row'>
            <div class='col-lg-8'>
                <div class='card'>
                    <div class='card-header'>
                        <h5><i class='bi bi-list'></i> Existing Categories</h5>
                    </div>
                    <div class='card-body'>
                        <div class='table-responsive'>
                            <table class='table table-striped'>
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Usage</th>
                                        <th>Slug</th>
                                        <th class='text-end'>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    $categoryList
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class='col-lg-4'>
                <div class='card'>
                    <div class='card-header'>
                        <h5><i class='bi bi-plus-circle'></i> Add New Category</h5>
                    </div>
                    <div class='card-body'>
                        <form method='POST' action='?action=add_category'>
                            <div class='mb-3'>
                                <label class='form-label'>Category Name *</label>
                                <input type='text' class='form-control' name='name' 
                                       value='" . clean($data['name'] ?? '') . "' required>
                            </div>
                            <div class='mb-3'>
                                <label class='form-label'>Description</label>
                                <textarea class='form-control' name='description' rows='3'>" . 
                                    clean($data['description'] ?? '') . "</textarea>
                            </div>
                            <button type='submit' class='btn btn-primary w-100'>
                                <i class='bi bi-plus-lg'></i> Add Category
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>";
        
    return layout('Category Management', $content, $_SESSION, $breadcrumbs);
}

function categoryForm($action, $category = null, $error = '', $success = '', $data = []) {
    $title = $action === 'edit' ? 'Edit Category' : 'Add Category';
    
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'Category Management', 'url' => 'manage.php?action=categories'],
        ['title' => $title]
    ];
    
    $content = "
        <div class='row justify-content-center'>
            <div class='col-md-6'>
                <div class='card'>
                    <div class='card-body'>
                        <h1 class='card-title'><i class='bi bi-tag'></i> $title</h1>
                        
                        " . showMessage('error', $error) . "
                        " . showMessage('success', $success) . "
                        
                        <form method='POST'>
                            <div class='mb-3'>
                                <label class='form-label'>Category Name *</label>
                                <input type='text' class='form-control' name='name' 
                                       value='" . clean($data['name'] ?? $category['name'] ?? '') . "' required>
                            </div>
                            <div class='mb-4'>
                                <label class='form-label'>Description</label>
                                <textarea class='form-control' name='description' rows='4'>" . 
                                    clean($data['description'] ?? $category['description'] ?? '') . "</textarea>
                            </div>
                            <div class='d-flex gap-2'>
                                <button type='submit' class='btn btn-primary'>
                                    <i class='bi bi-check-lg'></i> " . ($action === 'edit' ? 'Update' : 'Add') . " Category
                                </button>
                                <a href='manage.php?action=categories' class='btn btn-outline-secondary'>
                                    <i class='bi bi-x-lg'></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>";
        
    return layout($title, $content, $_SESSION, $breadcrumbs);
}

function deleteConfirmationPage($profile, $error = '', $success = '') {
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => $profile['artist_name'], 'url' => "index.php?action=view&id={$profile['id']}"],
        ['title' => 'Delete Profile']
    ];
    
    if ($success) {
        $content = "
            <div class='row justify-content-center'>
                <div class='col-md-6'>
                    <div class='card text-center'>
                        <div class='card-body py-5'>
                            <i class='bi bi-check-circle text-success' style='font-size: 4rem;'></i>
                            <h1 class='mt-3'>Profile Deleted</h1>
                            <p class='text-muted mb-4'>The profile has been successfully deleted.</p>
                            <a href='index.php' class='btn btn-primary'>Return to Gallery</a>
                        </div>
                    </div>
                </div>
            </div>";
    } else {
        $imageUrl = $profile['thumbnail_filename'] ? getThumbUrl($profile['thumbnail_filename']) : getImageUrl($profile['image_filename']);
        
        $content = "
            <div class='row justify-content-center'>
                <div class='col-md-8'>
                    <div class='card'>
                        <div class='card-body'>
                            <h1 class='text-danger'><i class='bi bi-exclamation-triangle'></i> Delete Profile</h1>
                            
                            " . showMessage('error', $error) . "
                            
                            <div class='alert alert-warning'>
                                <strong>Warning:</strong> This action cannot be undone. This will permanently delete 
                                the profile, all associated comments, favorites, and uploaded images.
                            </div>
                            
                            <div class='card mb-4' style='background: #f8f9fa;'>
                                <div class='card-body'>
                                    <div class='row'>
                                        <div class='col-md-4'>
                                            <img src='$imageUrl' class='img-fluid rounded' alt='Preview'>
                                        </div>
                                        <div class='col-md-8'>
                                            <h4>{$profile['artist_name']}</h4>
                                            <p class='text-muted'>" . truncate(strip_tags($profile['bio']), 200) . "</p>
                                            <div class='small text-muted'>
                                                <i class='bi bi-heart'></i> " . getFavoriteCount($profile['id']) . " favorites | 
                                                <i class='bi bi-chat'></i> " . getCommentCount($profile['id']) . " comments |
                                                <i class='bi bi-eye'></i> {$profile['view_count']} views
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <form method='POST'>
                                <input type='hidden' name='confirm_delete' value='1'>
                                <div class='d-flex gap-2 justify-content-end'>
                                    <a href='index.php?action=view&id={$profile['id']}' class='btn btn-outline-secondary'>
                                        <i class='bi bi-arrow-left'></i> Cancel
                                    </a>
                                    <button type='submit' class='btn btn-danger' onclick='return confirm(\"Are you absolutely sure?\")'>
                                        <i class='bi bi-trash'></i> Delete Permanently
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>";
    }
    
    return layout('Delete Profile', $content, $_SESSION, $breadcrumbs);
}
?>