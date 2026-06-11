<?php
/**
 * Unit tests for OAuth MAMS auth consumer
 */

namespace OAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MAMSConsumerTest extends TestCase
{
    private string $consumerFile;
    private string $moduleDir;

    protected function setUp(): void
    {
        $this->moduleDir = dirname(__DIR__, 2);
        $this->consumerFile = $this->moduleDir . '/lib/class.oauth_mams_consumer.php';
    }

    public function testConsumerFileExists(): void
    {
        $this->assertFileExists($this->consumerFile);
    }

    public function testConsumerExtendsMamsPureConsumer(): void
    {
        $content = file_get_contents($this->consumerFile);
        $this->assertStringContainsString(
            'class oauth_mams_consumer extends mams_pure_consumer',
            $content,
            'Consumer must extend mams_pure_consumer'
        );
    }

    public function testConsumerDoesNotDeclareLoginCapability(): void
    {
        $content = file_get_contents($this->consumerFile);

        // Extract the get_capabilities method body
        preg_match('/function\s+get_capabilities\s*\(\s*\)\s*\{(.*?)\}/s', $content, $matches);
        $this->assertNotEmpty($matches, 'get_capabilities() must be defined');

        $body = $matches[1];
        $this->assertStringNotContainsString(
            'CAPABILITY_LOGIN',
            $body,
            'Consumer must NOT declare CAPABILITY_LOGIN — MAMS should use external auth path'
        );
    }

    public function testConsumerDeclaresRequiredCapabilities(): void
    {
        $content = file_get_contents($this->consumerFile);

        preg_match('/function\s+get_capabilities\s*\(\s*\)\s*\{(.*?)\}/s', $content, $matches);
        $body = $matches[1];

        $this->assertStringContainsString('CAPABILITY_ALTLOGIN', $body);
        $this->assertStringContainsString('CAPABILITY_LOGOUT', $body);
        $this->assertStringContainsString('CAPABILITY_USERNAME', $body);
    }

    public function testConsumerImplementsRequiredMethods(): void
    {
        $content = file_get_contents($this->consumerFile);

        $requiredMethods = [
            'is_authenticated',
            'get_capabilities',
            'get_login_display',
            'get_logout_display',
            'get_connecting_property_name',
            'get_unique_identifier',
            'get_username',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertMatchesRegularExpression(
                '/function\s+' . preg_quote($method) . '\s*\(/',
                $content,
                "Consumer must implement $method()"
            );
        }
    }

    public function testConnectingPropertyIsOAuthEmail(): void
    {
        $content = file_get_contents($this->consumerFile);

        preg_match("/function\s+get_connecting_property_name\s*\(\)\s*\{(.*?)\}/s", $content, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString("'oauth_email'", $matches[1]);
    }

    public function testLoginDisplayUsesOAuthModule(): void
    {
        $content = file_get_contents($this->consumerFile);

        preg_match('/function\s+get_login_display\s*\(.*?\)\s*\{(.*?)\n    \}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $body = $matches[1];

        // Must get the OAuth module
        $this->assertStringContainsString("get_module('OAuth')", $body);
        // Must call GetEnabledProviders
        $this->assertStringContainsString('GetEnabledProviders', $body);
    }

    public function testLogoutDisplayGeneratesLink(): void
    {
        $content = file_get_contents($this->consumerFile);

        preg_match('/function\s+get_logout_display\s*\(.*?\)\s*\{(.*?)\n    \}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $body = $matches[1];

        $this->assertStringContainsString("get_module('OAuth')", $body);
        $this->assertStringContainsString('logout', $body);
    }

    public function testUniqueIdentifierUsesEmail(): void
    {
        $content = file_get_contents($this->consumerFile);

        preg_match('/function\s+get_unique_identifier\s*\(.*?\)\s*\{(.*?)\n    \}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $body = $matches[1];

        $this->assertStringContainsString('GetCurrentUser', $body);
        $this->assertStringContainsString("['email']", $body);
    }

    public function testIsAuthenticatedChecksCurrentUser(): void
    {
        $content = file_get_contents($this->consumerFile);

        preg_match('/function\s+is_authenticated\s*\(.*?\)\s*\{(.*?)\n    \}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $body = $matches[1];

        $this->assertStringContainsString('GetCurrentUser', $body);
    }

    public function testValidateUsernameAlwaysReturnsTrue(): void
    {
        $content = file_get_contents($this->consumerFile);

        // validate_username should always return TRUE since OAuth handles its own validation
        preg_match('/function\s+validate_username\s*\(.*?\)\s*\{(.*?)\}/s', $content, $matches);
        $this->assertNotEmpty($matches, 'validate_username() should be defined');
        $this->assertStringContainsString('TRUE', $matches[1]);
    }
}
