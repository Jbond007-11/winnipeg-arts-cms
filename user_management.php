<?php
require_once 'config.php';
require_once 'templates.php';

requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$search = clean($_GET['search'] ?? '');

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
            $data = [
                'username' => clean($_POST['username']),
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'email' => clean($_POST['email']),
                'full_name' => clean($_POST['full_name']),
                'role' => clean($_POST['role']),
                'approved' => isset($_POST['approved']) ? 1 : 0
            ];
            
            $errors = validateRequired([
                'username' => $data['username'],
                'email' => $data['email'],
                'full_name' => $data['full_name']
            ]);
            
            if (!validateEmail($data['email'])) {
                $errors[] = 'Invalid email format';
            }
            
            if (empty($_POST['password'])) {
                $errors[] = 'Password is required';
            }
            
            if (empty($errors)) {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$data['username'], $data['email']]);
                
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    if (insert('users', $data)) {
                        $success = 'User created successfully!';
                        $_POST = []; // Clear form
                    } else {
                        $error = 'Failed to create user';
                    }
                }
            } else {
                $error = implode(', ', $errors);
            }
            break;
            
        case 'edit':
            $data = [
                'username' => clean($_POST['username']),
                'email' => clean($_POST['email']),
                'full_name' => clean($_POST['full_name']),
                'role' => clean($_POST['role']),
                'approved' => isset($_POST['approved']) ? 1 : 0
            ];
            
            // Add password if provided
            if (!empty($_POST['password'])) {
                $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            $errors = validateRequired([
                'username' => $data['username'],
                'email' => $data['email'],
                'full_name' => $data['full_name']
            ]);
            
            if (!validateEmail($data['email'])) {
                $errors[] = 'Invalid email format';
            }
            
            if (empty($errors)) {
                // Check if username or email already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$data['username'], $data['email'], $id]);
                
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    if (update('users', $data, $id)) {
                        $success = 'User updated successfully!';
                    } else {
                        $error = 'Failed to update user';
                    }
                }
            } else {
                $error = implode(', ', $errors);
            }
            break;
            
        case 'delete':
            if (isset($_POST['confirm_delete'])) {
                $user = getOne('users', $id);
                
                if (!$user) {
                    $error = 'User not found';
                } elseif ($user['role'] === 'admin' && $id == $_SESSION['user_id']) {
                    $error = 'Cannot delete your own admin account';
                } else {
                    if (delete('users', $id)) {
                        $success = 'User deleted successfully!';
                    } else {
                        $error = 'Failed to delete user';
                    }
                }
            }
            break;
            
        case 'bulk_action':
            $selectedUsers = $_POST['selected_users'] ?? [];
            $bulkAction = $_POST['bulk_action'] ?? '';
            
            if (empty($selectedUsers)) {
                $error = 'No users selected';
            } else {
                $affectedCount = 0;
                
                foreach ($selectedUsers as $userId) {
                    $userId = (int)$userId;
                    
                    // Skip admin users and current user
                    $user = getOne('users', $userId);
                    if (!$user || ($user['role'] === 'admin' && $userId == $_SESSION['user_id'])) {
                        continue;
                    }
                    
                    switch ($bulkAction) {
                        case 'approve':
                            update('users', ['approved' => 1], $userId);
                            $affectedCount++;
                            break;
                        case 'unapprove':
                            update('users', ['approved' => 0], $userId);
                            $affectedCount++;
                            break;
                        case 'delete':
                            delete('users', $userId);
                            $affectedCount++;
                            break;
                    }
                }
                
                if ($affectedCount > 0) {
                    $success = "Successfully processed $affectedCount users";
                } else {
                    $error = "No users were processed";
                }
            }
            break;
    }
}

// Handle different actions
switch ($action) {
    case 'create':
        echo userForm('create', null, $error, $success, $_POST ?? []);
        break;
        
    case 'edit':
        $user = getOne('users', $id);
        if (!$user) {
            header('Location: user_management.php');
            exit;
        }
        echo userForm('edit', $user, $error, $success, $_POST ?? []);
        break;
        
    case 'delete':
        $user = getOne('users', $id);
        if (!$user) {
            header('Location: user_management.php');
            exit;
        }
        echo userDeletePage($user, $error, $success);
        break;
        
    default: // list
        echo userListPage($search, $page, $error, $success);
        break;
}

// Template functions
function userListPage($search = '', $page = 1, $error = '', $success = '') {
    global $pdo;
    
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'User Management']
    ];
    
    // Build search conditions
    $where = "1 = 1";
    $params = [];
    
    if ($search) {
        $where .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    // Get total count
    $totalUsers = getCount('users', $where, $params);
    
    // Get users for current page
    $offset = ($page - 1) * ITEMS_PER_PAGE;
    $users = getAll('users', $where, $params, 'created_at DESC', ITEMS_PER_PAGE, $offset);
    
    // Build search form
    $searchForm = "
        <div class='card mb-4'>
            <div class='card-body'>
                <form method='GET' class='row g-3'>
                    <div class='col-md-10'>
                        <input type='text' class='form-control' name='search' 
                               value='" . clean($search) . "' placeholder='Search users by username, email, or name...'>
                    </div>
                    <div class='col-md-2'>
                        <button type='submit' class='btn btn-primary w-100'>
                            <i class='bi bi-search'></i> Search
                        </button>
                    </div>
                    " . ($search ? "<input type='hidden' name='action' value='list'>" : "") . "
                </form>
                " . ($search ? "<div class='mt-2'><a href='user_management.php' class='btn btn-sm btn-outline-secondary'>Clear Search</a></div>" : "") . "
            </div>
        </div>";
    
    // User statistics
    $stats = [
        'total' => getCount('users'),
        'admins' => getCount('users', 'role = ?', ['admin']),
        'contributors' => getCount('users', 'role = ?', ['contributor']),
        'approved' => getCount('users', 'approved = 1'),
        'pending' => getCount('users', 'approved = 0')
    ];
    
    $statsCards = "
        <div class='row mb-4'>
            <div class='col-md-2'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-primary'>{$stats['total']}</h3>
                        <small class='text-muted'>Total Users</small>
                    </div>
                </div>
            </div>
            <div class='col-md-2'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-success'>{$stats['approved']}</h3>
                        <small class='text-muted'>Approved</small>
                    </div>
                </div>
            </div>
            <div class='col-md-2'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-warning'>{$stats['pending']}</h3>
                        <small class='text-muted'>Pending</small>
                    </div>
                </div>
            </div>
            <div class='col-md-2'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-info'>{$stats['admins']}</h3>
                        <small class='text-muted'>Admins</small>
                    </div>
                </div>
            </div>
            <div class='col-md-4'>
                <div class='d-grid'>
                    <a href='?action=create' class='btn btn-primary'>
                        <i class='bi bi-person-plus'></i> Add New User
                    </a>
                </div>
            </div>
        </div>";
    
    // Build user table
    $userRows = '';
    foreach ($users as $user) {
        $profileCount = getCount('profiles', 'user_id = ?', [$user['id']]);
        $statusBadge = $user['approved'] ? 
            "<span class='badge bg-success'>Approved</span>" : 
            "<span class='badge bg-warning'>Pending</span>";
        
        $roleBadge = $user['role'] === 'admin' ? 
            "<span class='badge bg-info'>Admin</span>" : 
            "<span class='badge bg-secondary'>Contributor</span>";
        
        $actionButtons = "
            <a href='?action=edit&id={$user['id']}' class='btn btn-sm btn-outline-primary'>
                <i class='bi bi-pencil'></i>
            </a>";
        
        // Don't allow deletion of current admin
        if (!($user['role'] === 'admin' && $user['id'] == $_SESSION['user_id'])) {
            $actionButtons .= "
                <a href='?action=delete&id={$user['id']}' class='btn btn-sm btn-outline-danger ms-1'>
                    <i class='bi bi-trash'></i>
                </a>";
        }
        
        $checkbox = $user['role'] === 'admin' && $user['id'] == $_SESSION['user_id'] ? 
            '' : "<input type='checkbox' class='form-check-input' name='selected_users[]' value='{$user['id']}'>";
        
        $userRows .= "
            <tr>
                <td>$checkbox</td>
                <td>
                    <strong>{$user['username']}</strong>
                    <br><small class='text-muted'>{$user['full_name']}</small>
                </td>
                <td>{$user['email']}</td>
                <td>$roleBadge</td>
                <td>$statusBadge</td>
                <td><span class='badge bg-light text-dark'>$profileCount</span></td>
                <td><small class='text-muted'>" . timeAgo($user['created_at']) . "</small></td>
                <td>$actionButtons</td>
            </tr>";
    }
    
    // **MODIFICATION START**
    // Removed the <form> tags from here, as they will now wrap the entire table.
    $bulkActions = "
        <div class='card mb-4'>
            <div class='card-body'>
                <div class='row align-items-end'>
                    <div class='col-md-3'>
                        <label class='form-label'>Bulk Actions</label>
                        <select class='form-select' name='bulk_action' required>
                            <option value=''>Select action...</option>
                            <option value='approve'>Approve Selected</option>
                            <option value='unapprove'>Unapprove Selected</option>
                            <option value='delete'>Delete Selected</option>
                        </select>
                    </div>
                    <div class='col-md-3'>
                        <button type='submit' class='btn btn-warning' onclick='return confirm(\"Apply bulk action to selected users?\")'>
                            <i class='bi bi-lightning'></i> Apply
                        </button>
                        <button type='button' class='btn btn-outline-secondary' onclick='toggleAllCheckboxes()'>
                            <i class='bi bi-check-square'></i> Toggle All
                        </button>
                    </div>
                </div>
            </div>
        </div>";
    
    // Pagination
    $totalPages = ceil($totalUsers / ITEMS_PER_PAGE);
    $baseUrl = 'user_management.php?1=1' . ($search ? '&search=' . urlencode($search) : '');
    $pagination = getPagination($page, $totalUsers, ITEMS_PER_PAGE, $baseUrl);
    
    // **MODIFICATION**
    // The main <form> tag is now placed here, wrapping both bulk actions and the table.
    $content = "
        <div class='d-flex justify-content-between align-items-center mb-4'>
            <h1><i class='bi bi-people'></i> User Management</h1>
            <a href='manage.php?action=moderate' class='btn btn-outline-secondary'>
                <i class='bi bi-arrow-left'></i> Back to Moderation
            </a>
        </div>
        
        " . showMessage('error', $error) . "
        " . showMessage('success', $success) . "
        
        $statsCards
        $searchForm
        
        <form method='POST' action='?action=bulk_action'>
        
        $bulkActions
        
        <div class='card'>
            <div class='card-header d-flex justify-content-between align-items-center'>
                <h5><i class='bi bi-list'></i> Users ($totalUsers total)</h5>
                <small class='text-muted'>Page $page of $totalPages</small>
            </div>
            <div class='card-body'>
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead>
                            <tr>
                                <th width='30'><input type='checkbox' class='form-check-input' onclick='toggleAllCheckboxes()'></th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Profiles</th>
                                <th>Joined</th>
                                <th width='100'>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            $userRows
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        </form>
        
        $pagination
        
        <script>
        function toggleAllCheckboxes() {
            const checkboxes = document.querySelectorAll('input[name=\"selected_users[]\"]');
            const headerCheckbox = document.querySelector('th input[type=\"checkbox\"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = headerCheckbox.checked;
            });
        }
        </script>";
        // **MODIFICATION END**
        
    return layout('User Management', $content, $_SESSION, $breadcrumbs);
}

function userForm($action, $user = null, $error = '', $success = '', $data = []) {
    $title = $action === 'edit' ? 'Edit User' : 'Create User';
    
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'User Management', 'url' => 'user_management.php'],
        ['title' => $title]
    ];
    
    $passwordField = $action === 'edit' ? 
        "<div class='mb-3'>
            <label class='form-label'>New Password (leave empty to keep current)</label>
            <input type='password' class='form-control' name='password'>
            <div class='form-text'>Only enter a password if you want to change it</div>
        </div>" :
        "<div class='mb-3'>
            <label class='form-label'>Password *</label>
            <input type='password' class='form-control' name='password' required>
        </div>";
    
    $roleOptions = "
        <option value='contributor'" . (($data['role'] ?? $user['role'] ?? 'contributor') === 'contributor' ? ' selected' : '') . ">Contributor</option>
        <option value='admin'" . (($data['role'] ?? $user['role'] ?? '') === 'admin' ? ' selected' : '') . ">Administrator</option>";
    
    $approvedChecked = ($data['approved'] ?? $user['approved'] ?? 0) ? 'checked' : '';
    
    $content = "
        <div class='row justify-content-center'>
            <div class='col-md-8'>
                <div class='card'>
                    <div class='card-body'>
                        <h1 class='card-title'><i class='bi bi-person-gear'></i> $title</h1>
                        
                        " . showMessage('error', $error) . "
                        " . showMessage('success', $success) . "
                        
                        <form method='POST'>
                            <div class='row'>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Username *</label>
                                    <input type='text' class='form-control' name='username' 
                                           value='" . clean($data['username'] ?? $user['username'] ?? '') . "' required>
                                </div>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Full Name *</label>
                                    <input type='text' class='form-control' name='full_name' 
                                           value='" . clean($data['full_name'] ?? $user['full_name'] ?? '') . "' required>
                                </div>
                            </div>
                            
                            <div class='mb-3'>
                                <label class='form-label'>Email Address *</label>
                                <input type='email' class='form-control' name='email' 
                                       value='" . clean($data['email'] ?? $user['email'] ?? '') . "' required>
                            </div>
                            
                            $passwordField
                            
                            <div class='row'>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Role</label>
                                    <select class='form-select' name='role' required>
                                        $roleOptions
                                    </select>
                                </div>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Status</label>
                                    <div class='form-check form-switch mt-2'>
                                        <input class='form-check-input' type='checkbox' name='approved' $approvedChecked>
                                        <label class='form-check-label'>Approved</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class='d-flex gap-2'>
                                <button type='submit' class='btn btn-primary'>
                                    <i class='bi bi-check-lg'></i> " . ($action === 'edit' ? 'Update User' : 'Create User') . "
                                </button>
                                <a href='user_management.php' class='btn btn-outline-secondary'>
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

function userDeletePage($user, $error = '', $success = '') {
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'User Management', 'url' => 'user_management.php'],
        ['title' => 'Delete User']
    ];
    
    if ($success) {
        $content = "
            <div class='row justify-content-center'>
                <div class='col-md-6'>
                    <div class='card text-center'>
                        <div class='card-body py-5'>
                            <i class='bi bi-check-circle text-success' style='font-size: 4rem;'></i>
                            <h1 class='mt-3'>User Deleted</h1>
                            <p class='text-muted mb-4'>The user has been successfully deleted.</p>
                            <a href='user_management.php' class='btn btn-primary'>Back to User Management</a>
                        </div>
                    </div>
                </div>
            </div>";
    } else {
        $profileCount = getCount('profiles', 'user_id = ?', [$user['id']]);
        $commentCount = getCount('comments', 'user_id = ?', [$user['id']]);
        $favoriteCount = getCount('favorites', 'user_id = ?', [$user['id']]);
        
        $content = "
            <div class='row justify-content-center'>
                <div class='col-md-8'>
                    <div class='card'>
                        <div class='card-body'>
                            <h1 class='text-danger'><i class='bi bi-exclamation-triangle'></i> Delete User</h1>
                            
                            " . showMessage('error', $error) . "
                            
                            <div class='alert alert-danger'>
                                <strong>Warning:</strong> This action cannot be undone. Deleting this user will also delete:
                                <ul class='mb-0 mt-2'>
                                    <li>$profileCount profile(s)</li>
                                    <li>$commentCount comment(s)</li>
                                    <li>$favoriteCount favorite(s)</li>
                                    <li>All associated images and data</li>
                                </ul>
                            </div>
                            
                            <div class='card mb-4' style='background: #f8f9fa;'>
                                <div class='card-body'>
                                    <h4>{$user['full_name']} (@{$user['username']})</h4>
                                    <p class='text-muted mb-2'>
                                        <i class='bi bi-envelope'></i> {$user['email']} | 
                                        <i class='bi bi-calendar'></i> Joined " . date('M j, Y', strtotime($user['created_at'])) . "
                                    </p>
                                    <div>
                                        <span class='badge " . ($user['role'] === 'admin' ? 'bg-info' : 'bg-secondary') . "'>{$user['role']}</span>
                                        <span class='badge " . ($user['approved'] ? 'bg-success' : 'bg-warning') . "'>" . ($user['approved'] ? 'Approved' : 'Pending') . "</span>
                                    </div>
                                </div>
                            </div>
                            
                            <form method='POST'>
                                <input type='hidden' name='confirm_delete' value='1'>
                                <div class='d-flex gap-2 justify-content-end'>
                                    <a href='user_management.php' class='btn btn-outline-secondary'>
                                        <i class='bi bi-arrow-left'></i> Cancel
                                    </a>
                                    <button type='submit' class='btn btn-danger' onclick='return confirm(\"Type DELETE to confirm\") && prompt(\"Type DELETE to confirm:\") === \"DELETE\"'>
                                        <i class='bi bi-trash'></i> Delete User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>";
    }
    
    return layout('Delete User', $content, $_SESSION, $breadcrumbs);
}
?>