<?php
/**
 * OAuth - Visitor Authentication Module
 * 
 * A module for authenticating visitors via OAuth providers (GitHub, Google, etc.)
 * for CMS Made Simple.
 */
if (!isset($gCms)) exit;

class OAuth extends CMSModule
{
    const PROVIDER_GITHUB = 'github';
    const PROVIDER_GOOGLE = 'google';
    const PROVIDER_FACEBOOK = 'facebook';
    const PROVIDER_TWITTER = 'twitter';
    const PROVIDER_GENERIC = 'generic';
    
    const SESSION_COOKIE = 'cmsms_oauth_session';
    const SESSION_DURATION = 86400 * 7; // 7 days

    public function GetName() { return 'OAuth'; }
    public function GetFriendlyName() { return $this->Lang('friendlyname'); }
    public function IsPluginModule() { return true; }
    public function HasAdmin() { return true; }
    public function GetVersion() { return '1.1.0'; }
    public function MinimumCMSVersion() { return '2.2.0'; }
    public function GetAdminDescription() { return $this->Lang('admindescription'); }
    public function GetAdminSection() { return 'usersgroups'; }
    public function AllowSmartyCaching() { return false; }
    public function LazyLoadFrontend() { return true; }
    public function LazyLoadAdmin() { return true; }
    public function GetHelp() { return $this->Lang('help'); }
    public function GetAuthor() { return 'CMSMS Community'; }
    public function GetAuthorEmail() { return 'oauth@cmsmadesimple.org'; }
    
    public function GetChangeLog() 
    { 
        return @file_get_contents(dirname(__FILE__).'/changelog.inc'); 
    }

    public function InitializeFrontend()
    {
        $this->RestrictUnknownParams();
        
        // Action parameter
        $this->SetParameterType('action', CLEAN_STRING);
        
        // OAuth parameters
        $this->SetParameterType('provider', CLEAN_STRING);
        $this->SetParameterType('code', CLEAN_STRING);
        $this->SetParameterType('state', CLEAN_STRING);
        $this->SetParameterType('error', CLEAN_STRING);
        $this->SetParameterType('error_description', CLEAN_STRING);
        
        // Redirect
        $this->SetParameterType('redirect', CLEAN_STRING);
        $this->SetParameterType('return_url', CLEAN_STRING);
        
        // Template
        $this->SetParameterType('logintemplate', CLEAN_STRING);
        $this->SetParameterType('profiletemplate', CLEAN_STRING);
    }

    public function InitializeAdmin()
    {
        $this->CreateParameter('provider', '', $this->Lang('help_provider'));
        $this->CreateParameter('action', 'login', $this->Lang('help_action'));
    }

    public function VisibleToAdminUser()
    {
        return $this->CheckPermission('Manage OAuth') || 
               $this->CheckPermission('Modify Site Preferences');
    }

    public function GetDependencies()
    {
        return [];
    }

    // Provider Management

    public function GetProviders()
    {
        return [
            self::PROVIDER_GITHUB => $this->Lang('provider_github'),
            self::PROVIDER_GOOGLE => $this->Lang('provider_google'),
            self::PROVIDER_FACEBOOK => $this->Lang('provider_facebook'),
            self::PROVIDER_TWITTER => $this->Lang('provider_twitter'),
            self::PROVIDER_GENERIC => $this->Lang('provider_generic'),
        ];
    }

    public function GetEnabledProviders()
    {
        $providers = [];
        foreach ($this->GetProviders() as $key => $label) {
            if ($this->GetPreference('provider_' . $key . '_enabled', 0)) {
                $providers[$key] = $label;
            }
        }
        return $providers;
    }

    public function GetProviderConfig($provider)
    {
        return [
            'client_id' => $this->GetPreference('provider_' . $provider . '_client_id', ''),
            'client_secret' => $this->GetPreference('provider_' . $provider . '_client_secret', ''),
            'enabled' => (bool)$this->GetPreference('provider_' . $provider . '_enabled', 0),
            'scopes' => $this->GetPreference('provider_' . $provider . '_scopes', ''),
        ];
    }

    public function GetProvider($name)
    {
        $config = $this->GetProviderConfig($name);
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            return null;
        }

        $providerClass = null;
        switch ($name) {
            case self::PROVIDER_GITHUB:
                require_once __DIR__ . '/lib/class.OAuthProvider.php';
                require_once __DIR__ . '/lib/class.GitHubProvider.php';
                $providerClass = new GitHubProvider($config['client_id'], $config['client_secret']);
                break;
            case self::PROVIDER_GOOGLE:
                require_once __DIR__ . '/lib/class.OAuthProvider.php';
                require_once __DIR__ . '/lib/class.GoogleProvider.php';
                $providerClass = new GoogleProvider($config['client_id'], $config['client_secret']);
                break;
            case self::PROVIDER_FACEBOOK:
                require_once __DIR__ . '/lib/class.OAuthProvider.php';
                require_once __DIR__ . '/lib/class.FacebookProvider.php';
                $providerClass = new FacebookProvider($config['client_id'], $config['client_secret']);
                break;
            case self::PROVIDER_TWITTER:
                require_once __DIR__ . '/lib/class.OAuthProvider.php';
                require_once __DIR__ . '/lib/class.TwitterProvider.php';
                $providerClass = new TwitterProvider($config['client_id'], $config['client_secret']);
                break;
            case self::PROVIDER_GENERIC:
                require_once __DIR__ . '/lib/class.OAuthProvider.php';
                require_once __DIR__ . '/lib/class.GenericProvider.php';
                $providerClass = new GenericProvider($config['client_id'], $config['client_secret'], [
                    'authorize_url' => $this->GetPreference('provider_generic_authorize_url', ''),
                    'token_url' => $this->GetPreference('provider_generic_token_url', ''),
                    'userinfo_url' => $this->GetPreference('provider_generic_userinfo_url', ''),
                ]);
                break;
        }

        if ($providerClass && $config['scopes']) {
            $providerClass->setScopes(explode(',', $config['scopes']));
        }

        return $providerClass;
    }

    // User Management

    public function GetCurrentUser()
    {
        $sessionId = $_COOKIE[self::SESSION_COOKIE] ?? null;
        if (!$sessionId) {
            return null;
        }

        $db = $this->GetDb();
        $prefix = CMS_DB_PREFIX;

        // Get session
        $session = $db->GetRow(
            "SELECT * FROM {$prefix}module_oauth_sessions WHERE session_id = ? AND expires_at > NOW()",
            [$sessionId]
        );

        if (!$session) {
            $this->ClearSession();
            return null;
        }

        // Get user
        $user = $db->GetRow(
            "SELECT * FROM {$prefix}module_oauth_users WHERE user_id = ?",
            [$session['user_id']]
        );

        if (!$user) {
            $this->ClearSession();
            return null;
        }

        // Get linked providers
        $links = $db->GetArray(
            "SELECT provider, provider_user_id, profile_data FROM {$prefix}module_oauth_links WHERE user_id = ?",
            [$user['user_id']]
        );

        $user['providers'] = [];
        foreach ($links as $link) {
            $user['providers'][$link['provider']] = [
                'id' => $link['provider_user_id'],
                'profile' => json_decode($link['profile_data'], true),
            ];
            // Add convenience accessors
            $user[$link['provider'] . '_id'] = $link['provider_user_id'];
        }

        return $user;
    }

    /**
     * Verify email and password for login
     * @param string $email
     * @param string $password
     * @return array|null User data or null if invalid
     */
    public function VerifyPassword($email, $password)
    {
        $db = $this->GetDb();
        $prefix = CMS_DB_PREFIX;
        
        $user = $db->GetRow(
            "SELECT user_id, email, password_hash, name, avatar_url FROM {$prefix}module_oauth_users WHERE email = ?",
            [$email]
        );
        
        if (!$user || empty($user['password_hash'])) {
            return null;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        
        // Update last login
        $db->Execute(
            "UPDATE {$prefix}module_oauth_users SET last_login = ? WHERE user_id = ?",
            [date('Y-m-d H:i:s'), $user['user_id']]
        );
        
        return $user;
    }

    /**
     * Set session value (wrapper for $_SESSION in module context)
     */
    public function SetSession($key, $value)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['oauth'][$key] = $value;
    }

    public function CreateOrUpdateUser($provider, $providerUserId, $profileData)
    {
        $db = $this->GetDb();
        $prefix = CMS_DB_PREFIX;
        $now = date('Y-m-d H:i:s');

        // Extract standard fields
        $email = $profileData['email'] ?? null;
        $name = $profileData['name'] ?? $profileData['login'] ?? 'Unknown';
        $avatar = $profileData['avatar_url'] ?? $profileData['picture'] ?? null;

        // Check if this provider link already exists
        $link = $db->GetRow(
            "SELECT * FROM {$prefix}module_oauth_links WHERE provider = ? AND provider_user_id = ?",
            [$provider, $providerUserId]
        );

        if ($link) {
            // Update existing user
            $userId = $link['user_id'];
            
            $db->Execute(
                "UPDATE {$prefix}module_oauth_users SET name = ?, avatar_url = ?, last_login = ? WHERE user_id = ?",
                [$name, $avatar, $now, $userId]
            );

            // Update link
            $db->Execute(
                "UPDATE {$prefix}module_oauth_links SET profile_data = ? WHERE link_id = ?",
                [json_encode($profileData), $link['link_id']]
            );
        } else {
            // Check if user with same email exists (merge accounts)
            $existingUser = null;
            if ($email) {
                $existingUser = $db->GetRow(
                    "SELECT * FROM {$prefix}module_oauth_users WHERE email = ?",
                    [$email]
                );
            }

            if ($existingUser) {
                $userId = $existingUser['user_id'];
                
                // Update user
                $db->Execute(
                    "UPDATE {$prefix}module_oauth_users SET name = ?, avatar_url = ?, last_login = ? WHERE user_id = ?",
                    [$name, $avatar, $now, $userId]
                );
            } else {
                // Create new user
                $db->Execute(
                    "INSERT INTO {$prefix}module_oauth_users (email, name, avatar_url, created_at, last_login) VALUES (?, ?, ?, ?, ?)",
                    [$email, $name, $avatar, $now, $now]
                );
                $userId = $db->Insert_ID();
            }

            // Create provider link
            $db->Execute(
                "INSERT INTO {$prefix}module_oauth_links (user_id, provider, provider_user_id, profile_data, created_at) VALUES (?, ?, ?, ?, ?)",
                [$userId, $provider, $providerUserId, json_encode($profileData), $now]
            );
        }

        return $userId;
    }

    public function UpdateTokens($provider, $providerUserId, $accessToken, $refreshToken = null, $expiresAt = null)
    {
        $db = $this->GetDb();
        $prefix = CMS_DB_PREFIX;

        $db->Execute(
            "UPDATE {$prefix}module_oauth_links SET access_token = ?, refresh_token = ?, token_expires = ? WHERE provider = ? AND provider_user_id = ?",
            [$accessToken, $refreshToken, $expiresAt, $provider, $providerUserId]
        );
    }

    // Session Management

    public function CreateSession($userId)
    {
        $db = $this->GetDb();
        $prefix = CMS_DB_PREFIX;
        
        $sessionId = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_DURATION);

        $db->Execute(
            "INSERT INTO {$prefix}module_oauth_sessions (session_id, user_id, created_at, expires_at) VALUES (?, ?, ?, ?)",
            [$sessionId, $userId, $now, $expiresAt]
        );

        // Set cookie
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(
            self::SESSION_COOKIE,
            $sessionId,
            [
                'expires' => time() + self::SESSION_DURATION,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        return $sessionId;
    }

    public function ClearSession()
    {
        $sessionId = $_COOKIE[self::SESSION_COOKIE] ?? null;
        
        if ($sessionId) {
            $db = $this->GetDb();
            $prefix = CMS_DB_PREFIX;
            
            $db->Execute(
                "DELETE FROM {$prefix}module_oauth_sessions WHERE session_id = ?",
                [$sessionId]
            );
        }

        // Clear cookie
        setcookie(self::SESSION_COOKIE, '', time() - 3600, '/');
        unset($_COOKIE[self::SESSION_COOKIE]);
    }

    public function CleanupExpiredSessions()
    {
        $db = $this->GetDb();
        $prefix = CMS_DB_PREFIX;
        
        $db->Execute("DELETE FROM {$prefix}module_oauth_sessions WHERE expires_at < NOW()");
    }

    // CSRF State Management

    public function GenerateState($returnUrl = null)
    {
        $state = bin2hex(random_bytes(16));
        $data = [
            'state' => $state,
            'return_url' => $returnUrl,
            'created' => time(),
        ];
        
        $_SESSION['oauth_state'] = $data;
        
        return $state;
    }

    public function ValidateState($state)
    {
        if (empty($_SESSION['oauth_state'])) {
            return false;
        }
        
        $data = $_SESSION['oauth_state'];
        
        // Check state matches
        if ($data['state'] !== $state) {
            return false;
        }
        
        // Check not expired (10 minutes)
        if (time() - $data['created'] > 600) {
            unset($_SESSION['oauth_state']);
            return false;
        }
        
        return $data;
    }

    public function ClearState()
    {
        unset($_SESSION['oauth_state']);
    }

    // Callback URL

    public function GetCallbackUrl($provider)
    {
        $config = cmsms()->GetConfig();
        $baseUrl = $config['root_url'];
        
        // Auto-detect if root_url is empty
        if (empty($baseUrl)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }
        
        $baseUrl = rtrim($baseUrl, '/');
        
        // Use a dedicated callback endpoint
        return $baseUrl . '/index.php?mact=OAuth,m1_,callback,0&m1_provider=' . urlencode($provider);
    }

    // Template helpers

    public function ProcessOAuthTemplate($type, $template = null)
    {
        if ($template) {
            try {
                $tpl = CmsLayoutTemplate::load($template);
                return $tpl->process();
            } catch (Exception $e) {
                // Fall back
            }
        }
        
        try {
            $type_obj = CmsLayoutTemplateType::load($this->GetName() . '::' . $type);
            $tpl = CmsLayoutTemplate::load_dflt_by_type($type_obj);
            if ($tpl) {
                return $tpl->process();
            }
        } catch (Exception $e) {
            // Fall back
        }
        
        return $this->ProcessTemplate($type . '.tpl');
    }

    public static function template_type_lang_callback($str)
    {
        $mod = \cms_utils::get_module('OAuth');
        if ($mod) {
            $key = 'tpltype_' . $str;
            $result = $mod->Lang($key);
            if ($result !== $key) return $result;
        }
        return $str;
    }

    public static function reset_template_defaults($type)
    {
        $mod = \cms_utils::get_module('OAuth');
        if (!$mod) return '';
        
        // $type is a CmsLayoutTemplateType object, get the name
        $typeName = is_object($type) ? $type->get_name() : $type;
        
        $fn = dirname(__FILE__) . '/templates/' . $typeName . '.tpl';
        if (file_exists($fn)) {
            return file_get_contents($fn);
        }
        return '';
    }

    // Events

    public function SendOAuthEvent($eventName, $params = [])
    {
        \Events::SendEvent($this->GetName(), $eventName, $params);
    }

    // MAMS Integration

    /**
     * Return the MAMS auth consumer object for this module.
     * Called by MAMS when OAuth is configured as the auth_module.
     *
     * @return oauth_mams_consumer
     */
    public function GetMAMSAuthConsumer()
    {
        require_once __DIR__ . '/lib/class.oauth_mams_consumer.php';
        return new oauth_mams_consumer();
    }

    /**
     * Check if MAMS module is installed and available.
     *
     * @return bool
     */
    public function IsMAMSAvailable()
    {
        $mams = \cms_utils::get_module('MAMS');
        return is_object($mams);
    }

    // Utility

    public function IsLoggedIn()
    {
        return $this->GetCurrentUser() !== null;
    }

    public function RequireHttps()
    {
        return (bool)$this->GetPreference('require_https', 1);
    }

    public function GetDefaultRedirect()
    {
        return $this->GetPreference('default_redirect', '/');
    }
}
