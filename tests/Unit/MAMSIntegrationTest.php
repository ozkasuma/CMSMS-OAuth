<?php
/**
 * Unit tests for MAMS integration in OAuth module and action files
 */

namespace OAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MAMSIntegrationTest extends TestCase
{
    private string $moduleDir;

    protected function setUp(): void
    {
        $this->moduleDir = dirname(__DIR__, 2);
    }

    // ── OAuth.module.php ──

    public function testModuleHasIsMAMSAvailableMethod(): void
    {
        $content = file_get_contents($this->moduleDir . '/OAuth.module.php');
        $this->assertStringContainsString('function IsMAMSAvailable()', $content);
    }

    public function testModuleHasGetMAMSAuthConsumerMethod(): void
    {
        $content = file_get_contents($this->moduleDir . '/OAuth.module.php');
        $this->assertStringContainsString('function GetMAMSAuthConsumer()', $content);
    }

    public function testGetMAMSAuthConsumerRequiresConsumerFile(): void
    {
        $content = file_get_contents($this->moduleDir . '/OAuth.module.php');

        preg_match('/function\s+GetMAMSAuthConsumer\s*\(\)\s*\{(.*?)\n    \}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $body = $matches[1];

        $this->assertStringContainsString('class.oauth_mams_consumer.php', $body);
        $this->assertStringContainsString('new oauth_mams_consumer', $body);
    }

    public function testIsMAMSAvailableChecksModule(): void
    {
        $content = file_get_contents($this->moduleDir . '/OAuth.module.php');

        preg_match('/function\s+IsMAMSAvailable\s*\(\)\s*\{(.*?)\n    \}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $body = $matches[1];

        $this->assertStringContainsString("get_module('MAMS')", $body);
    }

    // ── action.callback.php ──

    public function testCallbackBridgesToMAMS(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        // Must check MAMS availability before bridging
        $this->assertStringContainsString('IsMAMSAvailable()', $content);
    }

    public function testCallbackLooksUpMAMSUserByEmail(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        $this->assertStringContainsString('GetUserID', $content);
        $this->assertStringContainsString('$email', $content);
    }

    public function testCallbackCreatesMAMSUserIfNotFound(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        $this->assertStringContainsString('AddUser', $content);
    }

    public function testCallbackSetsDefaultGroups(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        $this->assertStringContainsString('GetDfltGroups', $content);
        $this->assertStringContainsString('SetUserGroups', $content);
    }

    public function testCallbackStoresOAuthPropertiesInMAMS(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        $this->assertStringContainsString('SetUserPropertyFull', $content);
        $this->assertStringContainsString("'oauth_email'", $content);
        $this->assertStringContainsString("'oauth_provider'", $content);
    }

    public function testCallbackStoresMAMSUserIdInOAuthTable(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        $this->assertStringContainsString('mams_user_id', $content);
    }

    public function testCallbackMAMSBridgeIsAfterSessionCreation(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        $sessionPos = strpos($content, 'CreateSession($userId)');
        $mamsPos = strpos($content, 'IsMAMSAvailable()');

        $this->assertNotFalse($sessionPos, 'CreateSession must be called');
        $this->assertNotFalse($mamsPos, 'MAMS bridge must exist');
        $this->assertGreaterThan(
            $sessionPos,
            $mamsPos,
            'MAMS bridge should happen after OAuth session is established'
        );
    }

    public function testCallbackMAMSBridgeIsGuarded(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.callback.php');

        // The MAMS block should be inside an if(IsMAMSAvailable()) guard
        preg_match('/if\s*\(\s*\$this->IsMAMSAvailable\(\)\s*\)/', $content, $matches);
        $this->assertNotEmpty($matches, 'MAMS code must be guarded by IsMAMSAvailable()');
    }

    // ── action.logout.php ──

    public function testLogoutAlsoLogsOutMAMS(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.logout.php');

        $this->assertStringContainsString('IsMAMSAvailable()', $content);
        $this->assertStringContainsString('LogoutUser', $content);
    }

    public function testLogoutMAMSHappensBeforeClearSession(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.logout.php');

        $mamsPos = strpos($content, 'IsMAMSAvailable()');
        $clearPos = strpos($content, 'ClearSession()');

        $this->assertNotFalse($mamsPos);
        $this->assertNotFalse($clearPos);
        $this->assertLessThan(
            $clearPos,
            $mamsPos,
            'MAMS logout must happen BEFORE clearing OAuth session (needs user context)'
        );
    }

    public function testLogoutMAMSIsGuarded(): void
    {
        $content = file_get_contents($this->moduleDir . '/action.logout.php');

        preg_match('/if\s*\(\s*\$this->IsMAMSAvailable\(\)\s*\)/', $content, $matches);
        $this->assertNotEmpty($matches, 'MAMS logout must be guarded by IsMAMSAvailable()');
    }

    // ── method.install.php ──

    public function testInstallIncludesMAMSUserIdColumn(): void
    {
        $content = file_get_contents($this->moduleDir . '/method.install.php');

        $this->assertStringContainsString('mams_user_id', $content);
    }

    public function testInstallHasUpgradePath(): void
    {
        $content = file_get_contents($this->moduleDir . '/method.install.php');

        // Should use MetaColumnNames to check and add column for upgrades
        $this->assertStringContainsString('MetaColumnNames', $content);
        $this->assertStringContainsString('ALTER TABLE', $content);
        $this->assertStringContainsString('mams_user_id', $content);
    }

    public function testInstallMAMSColumnIsNullable(): void
    {
        $content = file_get_contents($this->moduleDir . '/method.install.php');

        // mams_user_id should default to NULL (standalone OAuth still works)
        $this->assertMatchesRegularExpression(
            '/mams_user_id\s+INT\s+DEFAULT\s+NULL/i',
            $content,
            'mams_user_id must be nullable (DEFAULT NULL) for standalone OAuth compatibility'
        );
    }
}
