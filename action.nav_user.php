<?php
/**
 * OAuth Navigation User Display
 * 
 * Shows user info if logged in, or sign in/sign up buttons if not.
 * Used in site templates: {OAuth action="nav_user"}
 */
if (!isset($gCms)) exit;

$user = $this->GetCurrentUser();

if ($user) {
    // User is logged in - show user menu
    $avatarUrl = $user['avatar_url'] ?? '';
    $userName = $user['name'] ?? $user['email'] ?? 'User';
    $logoutUrl = $this->create_url($id, 'logout', $returnid);
    $profileUrl = $this->create_url($id, 'profile', $returnid);
    
    echo '<div class="user-menu">';
    if ($avatarUrl) {
        echo '<img src="' . htmlspecialchars($avatarUrl) . '" alt="" class="user-avatar">';
    }
    echo '<span class="user-name">' . htmlspecialchars($userName) . '</span>';
    echo '<a href="' . htmlspecialchars($logoutUrl) . '" class="btn-logout">Logout</a>';
    echo '</div>';
} else {
    // Not logged in - show sign in/sign up buttons
    $loginUrl = '/index.php?page=login';
    $registerUrl = $this->create_url($id, 'register', $returnid);
    
    echo '<a href="' . htmlspecialchars($loginUrl) . '" class="btn-signin">Sign In</a>';
    echo '<a href="' . htmlspecialchars($registerUrl) . '" class="btn-signup">Sign Up</a>';
}
