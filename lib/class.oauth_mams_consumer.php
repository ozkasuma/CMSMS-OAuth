<?php
/**
 * OAuth MAMS Auth Consumer
 *
 * Implements the mams_auth_consumer interface so that OAuth-authenticated
 * users are recognized by MAMS (Member Auth Management System).
 *
 * This consumer does NOT provide CAPABILITY_LOGIN, which causes MAMS to
 * use the external auth path: it calls is_authenticated() and
 * get_unique_identifier() on each request rather than relying on its
 * own session/cookie mechanism.
 */

class oauth_mams_consumer extends mams_pure_consumer
{
    /**
     * Check if an OAuth user is currently logged in.
     *
     * @return bool
     */
    public function is_authenticated()
    {
        $mod = \cms_utils::get_module('OAuth');
        if (!$mod) {
            return FALSE;
        }
        $user = $mod->GetCurrentUser();
        return ($user !== null);
    }

    /**
     * Return the capabilities this consumer provides.
     *
     * CAPABILITY_LOGIN is intentionally omitted so MAMS uses
     * the external auth consumer path in LoggedInId().
     *
     * @return array
     */
    public function get_capabilities()
    {
        return [
            self::CAPABILITY_ALTLOGIN,
            self::CAPABILITY_LOGOUT,
            self::CAPABILITY_USERNAME,
        ];
    }

    /**
     * Return HTML for the login display (OAuth login buttons).
     *
     * @param string $id
     * @param int $returnid
     * @param array $params
     * @return string
     */
    public function get_login_display($id, $returnid, $params)
    {
        $mod = \cms_utils::get_module('OAuth');
        if (!$mod) {
            return '';
        }

        $enabledProviders = $mod->GetEnabledProviders();
        if (empty($enabledProviders)) {
            return '';
        }

        $html = '<div class="oauth-mams-login">';
        foreach ($enabledProviders as $key => $label) {
            $loginUrl = $mod->CreateLink($id, 'login', $returnid, '',
                ['provider' => $key], '', true);
            $html .= '<a class="oauth-login-btn oauth-login-' . htmlspecialchars($key) . '" href="' . $loginUrl . '">';
            $html .= 'Login with ' . htmlspecialchars($label);
            $html .= '</a> ';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Return HTML for the logout display.
     *
     * @param string $id
     * @param int $returnid
     * @param array $params
     * @return string
     */
    public function get_logout_display($id, $returnid, $params)
    {
        $mod = \cms_utils::get_module('OAuth');
        if (!$mod) {
            return '';
        }

        $logoutUrl = $mod->CreateLink($id, 'logout', $returnid, '', [], '', true);
        return '<a class="oauth-logout-btn" href="' . $logoutUrl . '">Logout</a>';
    }

    /**
     * Return the MAMS user property name used to connect OAuth users to MAMS users.
     *
     * @return string
     */
    public function get_connecting_property_name()
    {
        return 'oauth_email';
    }

    /**
     * Return the unique identifier for the current OAuth user (their email).
     *
     * @return string|null
     */
    public function get_unique_identifier()
    {
        $mod = \cms_utils::get_module('OAuth');
        if (!$mod) {
            return null;
        }
        $user = $mod->GetCurrentUser();
        if (!$user) {
            return null;
        }
        return $user['email'] ?? null;
    }

    /**
     * Return user info for the current OAuth user.
     *
     * @return array|null
     */
    public function get_user_info()
    {
        $mod = \cms_utils::get_module('OAuth');
        if (!$mod) {
            return null;
        }
        return $mod->GetCurrentUser();
    }

    /**
     * Return the display name for a given MAMS user ID, or the current OAuth user.
     *
     * @param int|null $uid
     * @return string|null
     */
    public function get_username($uid = null)
    {
        $mod = \cms_utils::get_module('OAuth');
        if (!$mod) {
            return null;
        }
        $user = $mod->GetCurrentUser();
        if (!$user) {
            return null;
        }
        return $user['name'] ?? $user['email'] ?? null;
    }

    /**
     * Return the prompt label for the username field.
     *
     * @return string
     */
    public function get_username_prompt()
    {
        return 'Email';
    }

    /**
     * Validate a username. OAuth handles its own validation, so always return TRUE.
     *
     * @param string $username
     * @param bool $check_email_addr
     * @param int $uid
     * @return bool
     */
    public function validate_username($username, $check_email_addr = FALSE, $uid = -1)
    {
        return TRUE;
    }
}
