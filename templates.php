<?php
// Layout wrapper with Bootstrap
function layout($title, $content, $user = null, $breadcrumbs = []) {
    $nav = buildNavigation($user);
    $breadcrumbHtml = buildBreadcrumbs($breadcrumbs);
    
    return "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$title - Winnipeg Arts Collective</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/tinymce@6/skins/ui/oxide/skin.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='custom.css'>
</head>
<body>
    <header>
        <nav class='navbar navbar-expand-lg navbar-light bg-white shadow-sm'>
            <div class='container'>
                <a href='index.php' class='navbar-brand'>
                    <i class='bi bi-palette2'></i> Winnipeg Arts
                </a>
                <button class='navbar-toggler' type='button' data-bs-toggle='collapse' data-bs-target='#navbarNav'>
                    <span class='navbar-toggler-icon'></span>
                </button>
                <div class='collapse navbar-collapse' id='navbarNav'>
                    <ul class='navbar-nav ms-auto'>
                        $nav
                    </ul>
                </div>
            </div>
        </nav>
        $breadcrumbHtml
    </header>
    
    <main class='py-4'>
        <div class='container'>
            $content
        </div>
    </main>
    
    <footer class='py-4 mt-5'>
        <div class='container text-center text-muted'>
            <p>&copy; " . date('Y') . " Winnipeg Arts Collective. Supporting local artists since 1999.</p>
        </div>
    </footer>
    
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js'></script>
    <script>
        // Initialize TinyMCE for WYSIWYG editing
        tinymce.init({
            selector: '.wysiwyg-editor',
            height: 300,
            menubar: false,
            plugins: 'advlist autolink lists link image charmap print preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }'
        });
        
        // CAPTCHA refresh functionality
        function refreshCaptcha() {
            document.getElementById('captcha-image').src = 'captcha.php?generate=1&' + Math.random();
        }
        
        // Search with live suggestions
        function setupSearch() {
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                });
            }
        }
        
        // AJAX function for toggling favorites
        function toggleFavorite(button) {
            const profileId = button.dataset.profileId;
            const icon = button.querySelector('i');
            const countSpan = button.querySelector('.favorite-count');
            
            const formData = new FormData();
            formData.append('id', profileId);
            
            fetch('index.php?action=toggle_favorite_ajax', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the favorite count displayed on the button
                    countSpan.textContent = data.favoriteCount;
                    
                    // Toggle the heart icon's appearance
                    if (data.isFavorited) {
                        icon.classList.remove('bi-heart');
                        icon.classList.add('bi-heart-fill', 'text-danger');
                    } else {
                        icon.classList.remove('bi-heart-fill', 'text-danger');
                        icon.classList.add('bi-heart');
                    }
                } else {
                    alert('An error occurred. Please try again.');
                }
            })
            .catch(error => {
                console.error('Favorite toggle error:', error);
                alert('A network error occurred. Please try again.');
            });
        }
        
        // Initialize components
        document.addEventListener('DOMContentLoaded', function() {
            setupSearch();
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>";
}

function buildNavigation($user) {
    $nav = "<li class='nav-item'><a class='nav-link' href='index.php'><i class='bi bi-house'></i> Gallery</a></li>";
    
    if ($user) {
        $nav .= "<li class='nav-item'><a class='nav-link' href='index.php?action=profile'><i class='bi bi-person'></i> My Profile</a></li>";
        
        if (isAdmin()) {
            $nav .= "
                <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' role='button' data-bs-toggle='dropdown'>
                        <i class='bi bi-gear'></i> Admin
                    </a>
                    <ul class='dropdown-menu'>
                        <li><a class='dropdown-item' href='user_management.php'><i class='bi bi-people'></i> Users</a></li>
                        <li><a class='dropdown-item' href='manage.php?action=moderate'><i class='bi bi-shield-check'></i> Moderate</a></li>
                        <li><a class='dropdown-item' href='manage.php?action=categories'><i class='bi bi-tags'></i> Categories</a></li>
                    </ul>
                </li>";
        } else {
            $nav .= "<li class='nav-item'><a class='nav-link' href='manage.php'><i class='bi bi-plus-circle'></i> Publish Art</a></li>";
        }
        
        $nav .= "<li class='nav-item'><a class='nav-link' href='index.php?action=logout'><i class='bi bi-box-arrow-right'></i> Logout</a></li>";
    } else {
        $nav .= "
            <li class='nav-item'><a class='nav-link' href='index.php?action=login'><i class='bi bi-box-arrow-in-right'></i> Login</a></li>
            <li class='nav-item'><a class='nav-link' href='index.php?action=register'><i class='bi bi-person-plus'></i> Register</a></li>";
    }
    
    return $nav;
}

function buildBreadcrumbs($breadcrumbs) {
    if (empty($breadcrumbs)) return '';
    
    $html = "<nav aria-label='breadcrumb' class='bg-light py-2'>
        <div class='container'>
            <ol class='breadcrumb mb-0'>";
    
    foreach ($breadcrumbs as $crumb) {
        if (isset($crumb['url'])) {
            $html .= "<li class='breadcrumb-item'><a href='{$crumb['url']}'>{$crumb['title']}</a></li>";
        } else {
            $html .= "<li class='breadcrumb-item active' aria-current='page'>{$crumb['title']}</li>";
        }
    }
    
    $html .= "</ol></div></nav>";
    return $html;
}

//  Message display
function showMessage($type, $message) {
    if (!$message) return '';
    
    $iconMap = [
        'success' => 'check-circle',
        'error' => 'exclamation-triangle',
        'danger' => 'exclamation-triangle',
        'warning' => 'exclamation-circle',
        'info' => 'info-circle'
    ];
    
    $alertType = $type === 'error' ? 'danger' : $type;
    $icon = $iconMap[$type] ?? 'info-circle';
    
    return "<div class='alert alert-$alertType alert-dismissible fade show' role='alert'>
        <i class='bi bi-$icon me-2'></i>$message
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

// search form
function searchForm($query = '', $categoryId = '', $categories = []) {
    $categoryOptions = '<option value="">All Categories</option>';
    foreach ($categories as $cat) {
        $selected = $categoryId == $cat['id'] ? 'selected' : '';
        $categoryOptions .= "<option value='{$cat['id']}' $selected>{$cat['name']}</option>";
    }
    
    return "
    <div class='card mb-4'>
        <div class='card-body'>
            <form method='GET' class='row g-3' id='search-form'>
                <div class='col-md-6'>
                    <label for='search-input' class='form-label'>Search Artists</label>
                    <div class='input-group'>
                        <input type='text' class='form-control' id='search-input' name='search' 
                               value='" . clean($query) . "' placeholder='Search by name, bio, or specialty...'>
                        <button class='btn btn-outline-primary' type='submit'>
                            <i class='bi bi-search'></i>
                        </button>
                    </div>
                </div>
                <div class='col-md-4'>
                    <label for='category-filter' class='form-label'>Category</label>
                    <select class='form-select' id='category-filter' name='category' onchange='document.getElementById(\"search-form\").submit();'>
                        $categoryOptions
                    </select>
                </div>
                <div class='col-md-2 d-flex align-items-end'>
                    <a href='index.php' class='btn btn-outline-secondary w-100'>
                        <i class='bi bi-arrow-clockwise'></i> Clear
                    </a>
                </div>
                <input type='hidden' name='action' value='search'>
            </form>
        </div>
    </div>";
}

// auth forms
function loginForm($error = '', $data = []) {
    $username = clean($data['username'] ?? '');
    
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'Login']
    ];
    
    $content = "
        <div class='row justify-content-center'>
            <div class='col-md-6 col-lg-5'>
                <div class='card shadow'>
                    <div class='card-body p-5'>
                        <div class='text-center mb-4'>
                            <h1 class='h3'><i class='bi bi-box-arrow-in-right'></i> Welcome Back</h1>
                            <p class='text-muted'>Sign in to your account</p>
                        </div>
                        
                        " . showMessage('error', $error) . "
                        
                        <form method='POST'>
                            <div class='mb-3'>
                                <label class='form-label'>Username</label>
                                <div class='input-group'>
                                    <span class='input-group-text'><i class='bi bi-person'></i></span>
                                    <input type='text' class='form-control' name='username' value='$username' required>
                                </div>
                            </div>
                            <div class='mb-4'>
                                <label class='form-label'>Password</label>
                                <div class='input-group'>
                                    <span class='input-group-text'><i class='bi bi-lock'></i></span>
                                    <input type='password' class='form-control' name='password' required>
                                </div>
                            </div>
                            <button type='submit' class='btn btn-primary w-100 mb-3'>
                                <i class='bi bi-box-arrow-in-right'></i> Sign In
                            </button>
                        </form>
                        
                        <div class='text-center'>
                            <p class='mb-0'>
                                <a href='index.php' class='text-decoration-none'>← Back to Gallery</a>
                            </p>
                            <p class='mt-2'>
                                Don't have an account? 
                                <a href='?action=register' class='text-primary'>Register here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>";
        
    return layout('Login', $content, null, $breadcrumbs);
}

function registerForm($error = '', $success = false, $data = []) {
    if ($success) {
        $content = "
            <div class='row justify-content-center'>
                <div class='col-md-6 col-lg-5'>
                    <div class='card shadow'>
                        <div class='card-body p-5 text-center'>
                            <i class='bi bi-check-circle text-success' style='font-size: 3rem;'></i>
                            <h1 class='h3 mt-3 mb-3'>Registration Successful!</h1>
                            <p class='text-muted mb-4'>Your account has been created and is pending approval by our administrators.</p>
                            <a href='?action=login' class='btn btn-primary'>
                                <i class='bi bi-box-arrow-in-right'></i> Go to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>";
        return layout('Registration Complete', $content);
    }
    
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'Register']
    ];
    
    $content = "
        <div class='row justify-content-center'>
            <div class='col-md-8 col-lg-6'>
                <div class='card shadow'>
                    <div class='card-body p-5'>
                        <div class='text-center mb-4'>
                            <h1 class='h3'><i class='bi bi-person-plus'></i> Join Our Community</h1>
                            <p class='text-muted'>Create your artist account</p>
                        </div>
                        
                        " . showMessage('error', $error) . "
                        
                        <form method='POST'>
                            <div class='row'>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Full Name</label>
                                    <input type='text' class='form-control' name='full_name' 
                                           value='" . clean($data['full_name'] ?? '') . "' required>
                                </div>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Username</label>
                                    <input type='text' class='form-control' name='username' 
                                           value='" . clean($data['username'] ?? '') . "' required>
                                </div>
                            </div>
                            <div class='mb-3'>
                                <label class='form-label'>Email Address</label>
                                <input type='email' class='form-control' name='email' 
                                       value='" . clean($data['email'] ?? '') . "' required>
                            </div>
                            <div class='row'>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Password</label>
                                    <input type='password' class='form-control' name='password' required>
                                </div>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Confirm Password</label>
                                    <input type='password' class='form-control' name='confirm_password' required>
                                </div>
                            </div>
                            
                            " . buildCaptchaField() . "
                            
                            <button type='submit' class='btn btn-primary w-100 mb-3'>
                                <i class='bi bi-person-plus'></i> Create Account
                            </button>
                        </form>
                        
                        <div class='text-center'>
                            <p class='mb-0'>
                                <a href='index.php' class='text-decoration-none'>← Back to Gallery</a>
                            </p>
                            <p class='mt-2'>
                                Already have an account? 
                                <a href='?action=login' class='text-primary'>Sign in here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>";
        
    return layout('Register', $content, null, $breadcrumbs);
}

function buildCaptchaField() {
    return "
        <div class='captcha-container text-center'>
            <label class='form-label'>Security Check</label>
            <div class='mb-3'>
                <img id='captcha-image' src='captcha.php?generate=1' alt='CAPTCHA' class='border rounded'>
                <button type='button' class='btn btn-sm btn-outline-secondary ms-2' onclick='refreshCaptcha()'>
                    <i class='bi bi-arrow-clockwise'></i>
                </button>
            </div>
            <input type='text' class='form-control' name='captcha' placeholder='Enter the code above' required>
            <small class='form-text text-muted'>Click the refresh button if you can't read the code</small>
        </div>";
}

//Main gallery page
function mainPage($profiles, $categories, $categoryFilter, $user, $searchQuery = '', $currentPage = 1, $totalPages = 1, $totalItems = 0) {
    $searchFormHtml = searchForm($searchQuery, $categoryFilter, $categories);
    
    // Results summary
    $resultsSummary = '';
    if ($searchQuery || $categoryFilter) {
        $categoryName = $categoryFilter ? getOne('categories', $categoryFilter)['name'] : 'All Categories';
        $searchText = $searchQuery ? "for \"$searchQuery\"" : '';
        $categoryText = $categoryFilter ? "in $categoryName" : '';
        $resultsSummary = "
            <div class='alert alert-info'>
                <i class='bi bi-search'></i> 
                Found $totalItems " . ($totalItems == 1 ? 'result' : 'results') . " $searchText $categoryText
            </div>";
    }
    
    // Featured artists section
    $featuredHtml = '';
    if (!$searchQuery && !$categoryFilter && $currentPage == 1) {
        $featured = getAll('profiles', 'approved = 1 AND featured = 1', [], 'created_at DESC', 3);
        if ($featured) {
            $featuredHtml = "<div class='mb-5'>
                <h2 class='mb-4'><i class='bi bi-star-fill text-warning'></i> Featured Artists</h2>
                <div class='row'>";
            
            foreach ($featured as $profile) {
                $featuredHtml .= buildProfileCard($profile, $user, true);
            }
            
            $featuredHtml .= "</div></div>";
        }
    }
    
    // Profile grid
    $gridHtml = '';
    if (!empty($profiles)) {
        $gridHtml = "<div class='row'>";
        foreach ($profiles as $profile) {
            $gridHtml .= buildProfileCard($profile, $user);
        }
        $gridHtml .= "</div>";
    } else {
        $gridHtml = "
            <div class='text-center py-5'>
                <i class='bi bi-search' style='font-size: 4rem; color: #ccc;'></i>
                <h3 class='mt-3 text-muted'>No artists found</h3>
                <p class='text-muted'>Try adjusting your search criteria</p>
            </div>";
    }
    
    // Pagination
    $paginationHtml = '';
    if ($totalPages > 1) {
        $baseUrl = 'index.php?1=1' . ($searchQuery ? '&search=' . urlencode($searchQuery) : '') . 
                   ($categoryFilter ? '&category=' . $categoryFilter : '');
        $paginationHtml = getPagination($currentPage, $totalItems, ITEMS_PER_PAGE, $baseUrl);
    }
    
    $publishBtn = ($user && !isAdmin()) ? "
        <div class='text-center mb-5'>
            <a href='manage.php' class='btn btn-primary btn-lg'>
                <i class='bi bi-plus-circle'></i> Publish Your Art
            </a>
        </div>" : "";
    
    $content = "
        <div class='row mb-4'>
            <div class='col'>
                <h1 class='display-4 text-center mb-2'>Winnipeg Arts Collective</h1>
                <p class='lead text-center text-muted'>Discover and connect with local artists</p>
            </div>
        </div>
        
        $searchFormHtml
        $resultsSummary
        $featuredHtml
        $gridHtml
        $paginationHtml
        $publishBtn";
        
    return layout('Gallery', $content, $user);
}

function buildProfileCard($profile, $user, $featured = false) {
    $cardClass = $featured ? 'col-lg-4' : 'col-md-6 col-lg-4';
    $imageUrl = $profile['thumbnail_filename'] ? getThumbUrl($profile['thumbnail_filename']) : getImageUrl($profile['image_filename']);
    
    $favoriteBtn = '';
    $favoriteCount = getFavoriteCount($profile['id']);
    
    if ($user) {
        $isFav = isFavorited($user['user_id'], $profile['id']);
        $heartClass = $isFav ? 'bi-heart-fill text-danger' : 'bi-heart';
        
        // button with data attributes and an onclick event
        $favoriteBtn = "
            <button type='button' class='btn btn-sm btn-outline-danger' 
                    data-profile-id='{$profile['id']}' 
                    onclick='toggleFavorite(this)'>
                <i class='bi $heartClass'></i>
                <span class='favorite-count'>$favoriteCount</span>
            </button>";
    } else {
        $favoriteBtn = "<span class='btn btn-sm btn-outline-secondary disabled'>
            <i class='bi bi-heart'></i> $favoriteCount
        </span>";
    }
    
    $featuredBadge = $profile['featured'] ? 
        "<span class='badge bg-warning text-dark featured-badge'>
            <i class='bi bi-star-fill'></i> Featured
        </span>" : '';
    
    $seoUrl = getSEOUrl('profile', $profile['id'], $profile['slug']);
    
    return "
        <div class='$cardClass mb-4'>
            <div class='card h-100 position-relative'>
                $featuredBadge
                <a href='$seoUrl'>
                    <img src='$imageUrl' class='card-img-top' style='height: 200px; object-fit: cover;' alt='Art'>
                </a>
                <div class='card-body d-flex flex-column'>
                    <h5 class='card-title'>
                        <a href='$seoUrl' class='text-decoration-none text-dark'>{$profile['artist_name']}</a>
                    </h5>
                    <p class='card-text flex-grow-1'>" . truncate(strip_tags($profile['bio']), 100) . "</p>
                    <div class='d-flex justify-content-between align-items-center mt-auto'>
                        <div class='stats-badge'>
                            <span class='badge bg-light text-dark me-1'>
                                <i class='bi bi-chat'></i> " . getCommentCount($profile['id']) . "
                            </span>
                            <span class='badge bg-light text-dark'>
                                <i class='bi bi-eye'></i> {$profile['view_count']}
                            </span>
                        </div>
                        $favoriteBtn
                    </div>
                </div>
            </div>
        </div>";
}

//profileView function with artist name link and error/message support
function profileView($profile, $comments, $user, $other_profiles = [], $error = '', $message = '') {
    incrementViewCount($profile['id']);
    
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => $profile['artist_name']]
    ];
    
    $imageUrl = $profile['image_filename'] ? getImageUrl($profile['image_filename']) : null;
    $imageHtml = $imageUrl ? 
        "<img src='$imageUrl' class='img-fluid rounded shadow-sm mb-4' alt='Artwork'>" :
        "<div class='bg-light rounded d-flex align-items-center justify-content-center mb-4' style='height: 300px;'>
            <i class='bi bi-image' style='font-size: 4rem; color: #ccc;'></i>
        </div>";
    
    $favoriteBtn = '';
    if ($user) {
        $favoriteCount = getFavoriteCount($profile['id']);
        $isFav = isFavorited($user['user_id'], $profile['id']);
        $heartClass = $isFav ? 'bi-heart-fill text-white' : 'bi-heart text-white';
        $btnClass = $isFav ? 'btn-danger' : 'btn-outline-light';
        
        $favoriteBtn = "
            <a href='?action=toggle_favorite&id={$profile['id']}&redirect=" . urlencode($_SERVER['REQUEST_URI']) . "' 
               class='btn $btnClass'>
                <i class='bi $heartClass'></i> " . ($isFav ? 'Favorited' : 'Add to Favorites') . " ($favoriteCount)
            </a>";
    }
    
    $editBtn = '';
    if ($user && (isAdmin() || $profile['user_id'] == $user['user_id'])) {
        $editBtn = "<a href='manage.php?action=edit&id={$profile['id']}' class='btn btn-primary'>
            <i class='bi bi-pencil'></i> Edit
        </a>";
    }
    
    //Add link to artist's profile
    $artistProfileLink = "
        <a href='index.php?action=user_profile&user_id={$profile['user_id']}' 
           class='text-decoration-none'>
            <i class='bi bi-person-circle'></i> View Artist Profile
        </a>";
    
    $commentForm = '';
    if ($user) {
        $commentForm = "
            <div class='card mb-4'>
                <div class='card-header'><h5><i class='bi bi-chat-left-text'></i> Leave a Comment</h5></div>
                <div class='card-body'>
                    <form method='POST'>
                        <div class='mb-3'>
                            <textarea name='comment' class='form-control' rows='4' 
                                      placeholder='Share your thoughts about this artwork...' required></textarea>
                        </div>
                        
                        " . buildCaptchaField() . "
                        
                        <button type='submit' name='add_comment' class='btn btn-primary'>
                            <i class='bi bi-send'></i> Post Comment
                        </button>
                    </form>
                </div>
            </div>";
    }
    
    $commentsList = '';
    if (!empty($comments)) {
        $commentsList .= "<div class='card'><div class='card-header'>
            <h5><i class='bi bi-chat-dots'></i> Comments (" . count($comments) . ")</h5>
        </div><div class='card-body'>";
        
        foreach ($comments as $comment) {
            $deleteBtn = '';
            $moderateBtn = '';
            
            if ($user) {
                if ($comment['user_id'] == $user['user_id']) {
                    $deleteBtn = " | <a href='?action=delete_comment&comment_id={$comment['id']}&profile_id={$profile['id']}' 
                        class='text-danger' onclick='return confirm(\"Delete this comment?\")'>Delete</a>";
                }
                
                if (isAdmin()) {
                    $moderateBtn = $comment['moderated'] ? 
                        " | <a href='manage.php?action=restore_comment&id={$comment['id']}&profile_id={$profile['id']}' 
                           class='text-success'>Restore</a>" :
                        " | <a href='manage.php?action=disemvowel_comment&id={$comment['id']}&profile_id={$profile['id']}' 
                           class='text-warning'>Disemvowel</a>";
                }
            }
            
            $moderatedBadge = $comment['moderated'] ? 
                "<span class='badge bg-warning text-dark ms-2'>Moderated</span>" : '';
            
            $commentsList .= "
                <div class='border-bottom pb-3 mb-3'>
                    <div class='d-flex justify-content-between align-items-start mb-2'>
                        <strong>{$comment['full_name']}</strong>$moderatedBadge
                        <small class='text-muted'>" . timeAgo($comment['created_at']) . "$deleteBtn$moderateBtn</small>
                    </div>
                    <p class='mb-0'>" . nl2br(clean($comment['comment'])) . "</p>
                </div>";
        }
        
        $commentsList .= "</div></div>";
    }

    $moreFromArtistHtml = '';
    if (!empty($other_profiles)) {
        $moreFromArtistHtml .= "
            <hr class='my-5'>
            <h3 class='mb-4'>More from {$profile['artist_full_name']}</h3>
            <div class='row'>";
        
        foreach ($other_profiles as $other_profile) {
            $imageUrl = getThumbUrl($other_profile['thumbnail_filename']);
            $viewUrl = getSEOUrl('profile', $other_profile['id'], $other_profile['slug']);
            
            $moreFromArtistHtml .= "
                <div class='col-md-6 col-lg-3 mb-4'>
                    <div class='card h-100'>
                        <a href='$viewUrl'>
                            <img src='$imageUrl' class='card-img-top' style='height: 150px; object-fit: cover;' alt='" . clean($other_profile['artist_name']) . "'>
                        </a>
                        <div class='card-body'>
                            <h6 class='card-title mb-0'>
                                <a href='$viewUrl' class='text-decoration-none text-dark'>". clean($other_profile['artist_name']) ."</a>
                            </h6>
                        </div>
                    </div>
                </div>";
        }
        
        $moreFromArtistHtml .= "</div>";
    }
    
    $content = "
        <a href='index.php' class='btn btn-outline-primary mb-4'>
            <i class='bi bi-arrow-left'></i> Back to Gallery
        </a>
        
        " . showMessage('error', $error) . "
        " . showMessage('success', $message) . "
        
        <div class='row'>
            <div class='col-lg-8'>
                $imageHtml
                <h1 class='mb-3'>{$profile['artist_name']}</h1>
                <p class='text-muted mb-3'>by <strong>{$profile['artist_full_name']}</strong> | $artistProfileLink</p>
                <div class='mb-4'>" . $profile['bio'] . "</div>
            </div>
            <div class='col-lg-4'>
                <div class='card bg-primary text-white mb-4'>
                    <div class='card-body text-center'>
                        <h5 class='card-title'>Contact Artist</h5>
                        <p class='card-text'>{$profile['contact_info']}</p>
                        $favoriteBtn
                        $editBtn
                    </div>
                </div>
                
                <div class='card'>
                    <div class='card-body'>
                        <h6>Artist Stats</h6>
                        <ul class='list-unstyled'>
                            <li><i class='bi bi-eye'></i> {$profile['view_count']} views</li>
                            <li><i class='bi bi-heart'></i> " . getFavoriteCount($profile['id']) . " favorites</li>
                            <li><i class='bi bi-chat'></i> " . count($comments) . " comments</li>
                            <li><i class='bi bi-calendar'></i> Joined " . date('M Y', strtotime($profile['created_at'])) . "</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        $moreFromArtistHtml
        
        <hr class='my-5'>
        
        $commentForm
        $commentsList";
        
    return layout($profile['artist_name'], $content, $user, $breadcrumbs);
}

// Public user profile function
function publicUserProfile($viewed_user, $user_profiles, $current_user) {
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => $viewed_user['full_name'] . '\'s Profile']
    ];
    
    $postsGrid = '';
    if (!empty($user_profiles)) {
        $postsGrid = "<div class='row'>";
        foreach ($user_profiles as $post) {
            $imageUrl = $post['thumbnail_filename'] ? getThumbUrl($post['thumbnail_filename']) : getImageUrl($post['image_filename']);
            $seoUrl = getSEOUrl('profile', $post['id'], $post['slug']);
            
            $postsGrid .= "
                <div class='col-md-6 col-lg-4 mb-4'>
                    <div class='card h-100'>
                        <a href='$seoUrl'>
                            <img src='$imageUrl' class='card-img-top' style='height: 200px; object-fit: cover;' alt='Art'>
                        </a>
                        <div class='card-body d-flex flex-column'>
                            <h6 class='card-title'>
                                <a href='$seoUrl' class='text-decoration-none text-dark'>{$post['artist_name']}</a>
                            </h6>
                            <p class='card-text flex-grow-1'>" . truncate(strip_tags($post['bio']), 100) . "</p>
                            <div class='mt-auto'>
                                <div class='mb-2'>
                                    <span class='badge bg-light text-dark me-1'>
                                        <i class='bi bi-heart'></i> " . getFavoriteCount($post['id']) . "
                                    </span>
                                    <span class='badge bg-light text-dark me-1'>
                                        <i class='bi bi-chat'></i> " . getCommentCount($post['id']) . "
                                    </span>
                                    <span class='badge bg-light text-dark'>
                                        <i class='bi bi-eye'></i> {$post['view_count']}
                                    </span>
                                </div>
                                <a href='$seoUrl' class='btn btn-sm btn-primary'>
                                    <i class='bi bi-eye'></i> View Artwork
                                </a>
                            </div>
                        </div>
                    </div>
                </div>";
        }
        $postsGrid .= "</div>";
    } else {
        $postsGrid = "
            <div class='text-center py-5'>
                <i class='bi bi-palette' style='font-size: 4rem; color: #ccc;'></i>
                <h4 class='mt-3 text-muted'>No published artwork yet</h4>
                <p class='text-muted'>This artist hasn't published any artwork yet.</p>
            </div>";
    }
    
    $profileStats = "
        <div class='row mb-4'>
            <div class='col-md-3'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-primary'>" . count($user_profiles) . "</h3>
                        <small class='text-muted'>Artworks</small>
                    </div>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-success'>" . array_sum(array_map(function($p) { return getFavoriteCount($p['id']); }, $user_profiles)) . "</h3>
                        <small class='text-muted'>Total Favorites</small>
                    </div>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-info'>" . array_sum(array_map(function($p) { return $p['view_count']; }, $user_profiles)) . "</h3>
                        <small class='text-muted'>Total Views</small>
                    </div>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='card text-center'>
                    <div class='card-body py-3'>
                        <h3 class='text-warning'>" . array_sum(array_map(function($p) { return getCommentCount($p['id']); }, $user_profiles)) . "</h3>
                        <small class='text-muted'>Total Comments</small>
                    </div>
                </div>
            </div>
        </div>";
    
    $content = "
        <a href='index.php' class='btn btn-outline-primary mb-4'>
            <i class='bi bi-arrow-left'></i> Back to Gallery
        </a>
        
        <div class='row mb-4'>
            <div class='col-md-8'>
                <h1 class='mb-2'><i class='bi bi-person-circle'></i> {$viewed_user['full_name']}</h1>
                <p class='text-muted'>@{$viewed_user['username']} • Member since " . date('M Y', strtotime($viewed_user['created_at'])) . "</p>
            </div>
        </div>
        
        $profileStats
        
        <div class='card'>
            <div class='card-header'>
                <h5><i class='bi bi-palette2'></i> Published Artwork (" . count($user_profiles) . ")</h5>
            </div>
            <div class='card-body'>
                $postsGrid
            </div>
        </div>";
        
    return layout($viewed_user['full_name'] . '\'s Profile', $content, $current_user, $breadcrumbs);
}

// rofile creation/editing form
function profileForm($action, $profile = null, $categories = [], $error = '', $success = '', $data = []) {
    $title = $action === 'edit' ? 'Edit Artwork' : 'Publish Your Art';
    
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => $title]
    ];
    
    $categoryOptions = '<option value="">Select Category</option>';
    foreach ($categories as $cat) {
        $selected = ($data['category_id'] ?? $profile['category_id'] ?? 0) == $cat['id'] ? 'selected' : '';
        $categoryOptions .= "<option value='{$cat['id']}' $selected>{$cat['name']}</option>";
    }
    
    $currentImage = '';
    if ($action === 'edit' && $profile && $profile['image_filename']) {
        $currentImage = "
            <div class='mb-3'>
                <label class='form-label'>Current Image</label>
                <div>
                    <img src='" . getImageUrl($profile['image_filename']) . "' class='img-thumbnail' style='max-width: 200px;'>
                    <div class='form-check mt-2'>
                        <input class='form-check-input' type='checkbox' name='remove_image' id='remove_image'>
                        <label class='form-check-label' for='remove_image'>Remove current image</label>
                    </div>
                </div>
            </div>";
    }
    
    $approvalField = '';
    if (isAdmin()) {
        $checked = ($data['approved'] ?? $profile['approved'] ?? 1) ? 'checked' : '';
        $featuredChecked = ($data['featured'] ?? $profile['featured'] ?? 0) ? 'checked' : '';
        $approvalField = "
            <div class='row'>
                <div class='col-md-6'>
                    <div class='form-check'>
                        <input class='form-check-input' type='checkbox' name='approved' id='approved' $checked>
                        <label class='form-check-label' for='approved'>Approved for public display</label>
                    </div>
                </div>
                <div class='col-md-6'>
                    <div class='form-check'>
                        <input class='form-check-input' type='checkbox' name='featured' id='featured' $featuredChecked>
                        <label class='form-check-label' for='featured'>Featured artist</label>
                    </div>
                </div>
            </div>";
    }
    
    $content = "
        <div class='row justify-content-center'>
            <div class='col-lg-8'>
                <div class='card shadow'>
                    <div class='card-body p-4'>
                        <h1 class='card-title'><i class='bi bi-palette'></i> $title</h1>
                        
                        " . showMessage('error', $error) . "
                        " . showMessage('success', $success) . "
                        
                        <form method='POST' enctype='multipart/form-data'>
                            <div class='row'>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Artist/Artwork Name *</label>
                                    <input type='text' class='form-control' name='artist_name' 
                                           value='" . clean($data['artist_name'] ?? $profile['artist_name'] ?? '') . "' required>
                                </div>
                                <div class='col-md-6 mb-3'>
                                    <label class='form-label'>Category *</label>
                                    <select class='form-select' name='category_id' required>
                                        $categoryOptions
                                    </select>
                                </div>
                            </div>
                            
                            <div class='mb-3'>
                                <label class='form-label'>Bio/Description *</label>
                                <textarea class='form-control wysiwyg-editor' name='bio' rows='6'>" . 
                                    clean($data['bio'] ?? $profile['bio'] ?? '') . "</textarea>
                                <div class='form-text'>Describe your artwork, technique, inspiration, etc.</div>
                            </div>
                            
                            <div class='mb-3'>
                                <label class='form-label'>Contact Information *</label>
                                <input type='text' class='form-control' name='contact_info' 
                                       value='" . clean($data['contact_info'] ?? $profile['contact_info'] ?? '') . "' 
                                       placeholder='email@example.com | 204-555-0123' required>
                            </div>
                            
                            <div class='mb-3'>
                                <label class='form-label'>Specialty (Optional)</label>
                                <input type='text' class='form-control' name='specialty' 
                                       value='" . clean($data['specialty'] ?? $profile['specialty'] ?? '') . "' 
                                       placeholder='e.g., Oil Painting, Digital Art, Photography'>
                            </div>
                            
                            $currentImage
                            
                            <div class='mb-4'>
                                <label class='form-label'>Artwork Image</label>
                                <input type='file' class='form-control' name='artwork_image' accept='image/*'>
                                <div class='form-text'>Upload a high-quality image of your artwork (JPG, PNG, GIF - Max 10MB)</div>
                            </div>
                            
                            $approvalField
                            
                            <div class='d-flex gap-2'>
                                <button type='submit' class='btn btn-primary'>
                                    <i class='bi bi-check-lg'></i> " . ($action === 'edit' ? 'Update Artwork' : 'Publish Artwork') . "
                                </button>
                                <a href='index.php' class='btn btn-outline-secondary'>
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

// user profile page
function userProfile($user, $posts, $error = '', $success = '', $data = []) {
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'My Profile']
    ];
    
    $updateForm = "
        <div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-person-gear'></i> Update Profile</h5>
            </div>
            <div class='card-body'>
                <form method='POST'>
                    <div class='row'>
                        <div class='col-md-6 mb-3'>
                            <label class='form-label'>Full Name</label>
                            <input type='text' class='form-control' name='full_name' 
                                   value='" . clean($data['full_name'] ?? $user['full_name']) . "' required>
                        </div>
                        <div class='col-md-6 mb-3'>
                            <label class='form-label'>Email</label>
                            <input type='email' class='form-control' name='email' 
                                   value='" . clean($data['email'] ?? $user['email']) . "' required>
                        </div>
                    </div>
                    <button type='submit' class='btn btn-primary'>
                        <i class='bi bi-check-lg'></i> Update Profile
                    </button>
                </form>
            </div>
        </div>";
    
    $postsGrid = '';
    if (!empty($posts)) {
        $postsGrid = "<div class='row'>";
        foreach ($posts as $post) {
            $imageUrl = $post['thumbnail_filename'] ? getThumbUrl($post['thumbnail_filename']) : getImageUrl($post['image_filename']);
            $seoUrl = getSEOUrl('profile', $post['id'], $post['slug']);
            
            $postsGrid .= "
                <div class='col-md-6 col-lg-4 mb-4'>
                    <div class='card h-100'>
                        <a href='$seoUrl'>
                            <img src='$imageUrl' class='card-img-top' style='height: 150px; object-fit: cover;'>
                        </a>
                        <div class='card-body d-flex flex-column'>
                            <h6 class='card-title'>{$post['artist_name']}</h6>
                            <div class='mb-2'>
                                <span class='badge bg-light text-dark me-1'>
                                    <i class='bi bi-heart'></i> " . getFavoriteCount($post['id']) . "
                                </span>
                                <span class='badge bg-light text-dark me-1'>
                                    <i class='bi bi-chat'></i> " . getCommentCount($post['id']) . "
                                </span>
                                <span class='badge bg-light text-dark'>
                                    <i class='bi bi-eye'></i> {$post['view_count']}
                                </span>
                            </div>
                            <div class='mt-auto'>
                                <a href='$seoUrl' class='btn btn-sm btn-outline-primary me-1'>
                                    <i class='bi bi-eye'></i> View
                                </a>
                                <a href='manage.php?action=edit&id={$post['id']}' class='btn btn-sm btn-outline-secondary'>
                                    <i class='bi bi-pencil'></i> Edit
                                </a>
                            </div>
                        </div>
                    </div>
                </div>";
        }
        $postsGrid .= "</div>";
    } else {
        $postsGrid = "
            <div class='text-center py-5'>
                <i class='bi bi-palette' style='font-size: 4rem; color: #ccc;'></i>
                <h4 class='mt-3 text-muted'>No artwork published yet</h4>
                <p class='text-muted'>Share your art with the community</p>
                <a href='manage.php' class='btn btn-primary'>
                    <i class='bi bi-plus-circle'></i> Publish Your First Artwork
                </a>
            </div>";
    }
    
    $content = "
        <h1 class='mb-4'><i class='bi bi-person-circle'></i> Welcome, {$user['full_name']}</h1>
        
        " . showMessage('error', $error) . "
        " . showMessage('success', $success) . "
        
        $updateForm
        
        <div class='card'>
            <div class='card-header d-flex justify-content-between align-items-center'>
                <h5><i class='bi bi-palette2'></i> My Artwork (" . count($posts) . ")</h5>
                <a href='manage.php' class='btn btn-primary btn-sm'>
                    <i class='bi bi-plus-circle'></i> Add New
                </a>
            </div>
            <div class='card-body'>
                $postsGrid
            </div>
        </div>";
        
    return layout('My Profile', $content, $_SESSION, $breadcrumbs);
}

// Admin moderation page
function moderationPage($pendingUsers, $pendingProfiles = [], $flaggedComments = []) {
    $breadcrumbs = [
        ['title' => 'Gallery', 'url' => 'index.php'],
        ['title' => 'Moderation']
    ];
    
    $usersList = '';
    if (!empty($pendingUsers)) {
        foreach ($pendingUsers as $user) {
            $usersList .= "
                <div class='card mb-3'>
                    <div class='card-body'>
                        <div class='row align-items-center'>
                            <div class='col-md-8'>
                                <h6 class='mb-1'>{$user['full_name']} (@{$user['username']})</h6>
                                <p class='text-muted mb-1'>
                                    <i class='bi bi-envelope'></i> {$user['email']} | 
                                    <i class='bi bi-calendar'></i> Registered " . timeAgo($user['created_at']) . "
                                </p>
                            </div>
                            <div class='col-md-4 text-end'>
                                <a href='?action=approve_user&user_id={$user['id']}' class='btn btn-success btn-sm me-1'>
                                    <i class='bi bi-check-lg'></i> Approve
                                </a>
                                <a href='?action=reject_user&user_id={$user['id']}' class='btn btn-danger btn-sm'
                                   onclick='return confirm(\"Reject this user?\")'>
                                    <i class='bi bi-x-lg'></i> Reject
                                </a>
                            </div>
                        </div>
                    </div>
                </div>";
        }
    } else {
        $usersList = "<div class='text-center py-4 text-muted'>
            <i class='bi bi-check-circle' style='font-size: 2rem;'></i>
            <p class='mt-2'>No pending user registrations</p>
        </div>";
    }
    
    $content = "
        <h1><i class='bi bi-shield-check'></i> Content Moderation</h1>
        
        <div class='row'>
            <div class='col-lg-6'>
                <div class='card'>
                    <div class='card-header'>
                        <h5><i class='bi bi-people'></i> Pending User Registrations</h5>
                    </div>
                    <div class='card-body'>
                        $usersList
                    </div>
                </div>
            </div>
            <div class='col-lg-6'>
                <div class='card'>
                    <div class='card-header'>
                        <h5><i class='bi bi-gear'></i> Quick Actions</h5>
                    </div>
                    <div class='card-body'>
                        <div class='d-grid gap-2'>
                            <a href='user_management.php' class='btn btn-primary'>
                                <i class='bi bi-people'></i> Manage All Users
                            </a>
                            <a href='manage.php?action=categories' class='btn btn-secondary'>
                                <i class='bi bi-tags'></i> Manage Categories
                            </a>
                            <a href='index.php' class='btn btn-outline-primary'>
                                <i class='bi bi-house'></i> Back to Gallery
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>";
        
    return layout('Moderation', $content, $_SESSION, $breadcrumbs);
}
?>