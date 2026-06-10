<?php
/**
 * OAuth Module Installation
 */
if (!isset($gCms)) exit;

$db = $this->GetDb();
$prefix = CMS_DB_PREFIX;

// Create tables
$tables = [
    // OAuth users (standalone user accounts)
    "CREATE TABLE IF NOT EXISTS {$prefix}module_oauth_users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255),
        name VARCHAR(255),
        avatar_url VARCHAR(500),
        created_at DATETIME,
        last_login DATETIME,
        mams_user_id INT DEFAULT NULL,
        KEY idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // OAuth provider links (connects users to their OAuth providers)
    "CREATE TABLE IF NOT EXISTS {$prefix}module_oauth_links (
        link_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(50) NOT NULL,
        provider_user_id VARCHAR(255) NOT NULL,
        access_token TEXT,
        refresh_token TEXT,
        token_expires DATETIME,
        profile_data JSON,
        created_at DATETIME,
        KEY idx_user (user_id),
        UNIQUE KEY idx_provider_user (provider, provider_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // OAuth sessions
    "CREATE TABLE IF NOT EXISTS {$prefix}module_oauth_sessions (
        session_id VARCHAR(64) PRIMARY KEY,
        user_id INT NOT NULL,
        created_at DATETIME,
        expires_at DATETIME,
        KEY idx_user (user_id),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// Execute table creation
foreach ($tables as $sql) {
    $db->Execute($sql);
}

// Upgrade: add mams_user_id column if not exists (for upgrades from pre-MAMS versions)
$columns = $db->MetaColumnNames($prefix . 'module_oauth_users');
if (is_array($columns) && !in_array('mams_user_id', array_map('strtolower', $columns))) {
    $db->Execute("ALTER TABLE {$prefix}module_oauth_users ADD COLUMN mams_user_id INT DEFAULT NULL");
}

// Create permissions
$this->CreatePermission('Manage OAuth', 'Manage OAuth module settings and view users');

// Grant permission to Admin group
$perm_id = $db->GetOne("SELECT permission_id FROM {$prefix}permissions WHERE permission_name = 'Manage OAuth'");
$group_id = $db->GetOne("SELECT group_id FROM `{$prefix}groups` WHERE group_name = 'Admin'");
if ($perm_id && $group_id) {
    $count = $db->GetOne("SELECT count(*) FROM {$prefix}group_perms WHERE group_id = ? AND permission_id = ?", [$group_id, $perm_id]);
    if ($count == 0) {
        $db->Execute("INSERT INTO {$prefix}group_perms (group_id, permission_id, create_date, modified_date) VALUES (?, ?, NOW(), NOW())", [$group_id, $perm_id]);
    }
}

// Set default preferences
$this->SetPreference('require_https', 1);
$this->SetPreference('default_redirect', '/');
$this->SetPreference('session_duration', 604800); // 7 days

// Provider defaults (all disabled by default)
$providers = ['github', 'google', 'facebook', 'twitter', 'generic'];
foreach ($providers as $provider) {
    $this->SetPreference('provider_' . $provider . '_enabled', 0);
    $this->SetPreference('provider_' . $provider . '_client_id', '');
    $this->SetPreference('provider_' . $provider . '_client_secret', '');
}

// Default scopes
$this->SetPreference('provider_github_scopes', 'read:user,user:email');
$this->SetPreference('provider_google_scopes', 'openid,email,profile');
$this->SetPreference('provider_facebook_scopes', 'email,public_profile');
$this->SetPreference('provider_twitter_scopes', 'tweet.read,users.read');

// Setup template types
$uid = get_userid(false) ?: 1;

// Login template type
try {
    $loginType = new CmsLayoutTemplateType();
    $loginType->set_originator($this->GetName());
    $loginType->set_name('login');
    $loginType->set_dflt_flag(true);
    $loginType->set_lang_callback('OAuth::template_type_lang_callback');
    $loginType->set_content_callback('OAuth::reset_template_defaults');
    $loginType->reset_content_to_factory();
    $loginType->save();
    
    $fn = dirname(__FILE__).'/templates/login.tpl';
    if (file_exists($fn)) {
        $tpl = new CmsLayoutTemplate();
        $tpl->set_name('OAuth Login Buttons');
        $tpl->set_owner($uid);
        $tpl->set_content(file_get_contents($fn));
        $tpl->set_type($loginType);
        $tpl->set_type_dflt(true);
        $tpl->save();
    }
} catch (Exception $e) {
    audit('', $this->GetName(), 'Error creating login template: '.$e->getMessage());
}

// Profile template type
try {
    $profileType = new CmsLayoutTemplateType();
    $profileType->set_originator($this->GetName());
    $profileType->set_name('profile');
    $profileType->set_dflt_flag(true);
    $profileType->set_lang_callback('OAuth::template_type_lang_callback');
    $profileType->set_content_callback('OAuth::reset_template_defaults');
    $profileType->reset_content_to_factory();
    $profileType->save();
    
    $fn = dirname(__FILE__).'/templates/profile.tpl';
    if (file_exists($fn)) {
        $tpl = new CmsLayoutTemplate();
        $tpl->set_name('OAuth User Profile');
        $tpl->set_owner($uid);
        $tpl->set_content(file_get_contents($fn));
        $tpl->set_type($profileType);
        $tpl->set_type_dflt(true);
        $tpl->save();
    }
} catch (Exception $e) {
    audit('', $this->GetName(), 'Error creating profile template: '.$e->getMessage());
}

// Setup events
$this->CreateEvent('OAuthUserLogin');
$this->CreateEvent('OAuthUserLogout');
$this->CreateEvent('OAuthUserCreated');
$this->CreateEvent('OAuthProviderLinked');

// Register as plugin module for frontend
$this->RegisterModulePlugin(true);
