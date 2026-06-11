<?php
/**
 * Mock MAMS classes for testing OAuth-MAMS integration
 */

if (!class_exists('mams_pure_consumer')) {
    abstract class mams_pure_consumer
    {
        const CAPABILITY_LOGIN = 'login';
        const CAPABILITY_ALTLOGIN = 'altlogin';
        const CAPABILITY_LOGOUT = 'logout';
        const CAPABILITY_USERNAME = 'username';
        const CAPABILITY_GROUPLIST = 'grouplist';
        const CAPABILITY_GROUPASSIGN = 'groupassign';

        abstract public function is_authenticated();
        abstract public function get_capabilities();
        abstract public function get_login_display($id, $returnid, $params);
        abstract public function get_logout_display($id, $returnid, $params);
        abstract public function get_connecting_property_name();
        abstract public function get_unique_identifier();
    }
}

if (!class_exists('MockMAMSManipulator')) {
    class MockMAMSManipulator
    {
        private array $users = [];
        private array $properties = [];
        private array $groups = [];
        private int $nextId = 100;
        private ?int $loggedInId = null;
        private array $calls = [];

        public function GetUserID(string $username): int
        {
            $this->calls[] = ['method' => 'GetUserID', 'args' => [$username]];
            foreach ($this->users as $id => $user) {
                if ($user['username'] === $username) {
                    return $id;
                }
            }
            return -1;
        }

        public function AddUser(string $username, string $password, int $expires, bool $active = true, bool $force = false): array
        {
            $this->calls[] = ['method' => 'AddUser', 'args' => [$username, '[redacted]', $expires, $active, $force]];
            $id = $this->nextId++;
            $this->users[$id] = [
                'username' => $username,
                'password' => $password,
                'expires' => $expires,
                'active' => $active,
            ];
            return [true, $id];
        }

        public function GetDfltGroups(): array
        {
            $this->calls[] = ['method' => 'GetDfltGroups', 'args' => []];
            return $this->groups;
        }

        public function SetUserGroups(int $uid, array $groups): void
        {
            $this->calls[] = ['method' => 'SetUserGroups', 'args' => [$uid, $groups]];
        }

        public function SetUserPropertyFull(string $name, string $value, int $uid): void
        {
            $this->calls[] = ['method' => 'SetUserPropertyFull', 'args' => [$name, $value, $uid]];
            $this->properties[$uid][$name] = $value;
        }

        public function LoggedInId(): ?int
        {
            $this->calls[] = ['method' => 'LoggedInId', 'args' => []];
            return $this->loggedInId;
        }

        public function LogoutUser(int $uid): void
        {
            $this->calls[] = ['method' => 'LogoutUser', 'args' => [$uid]];
            if ($this->loggedInId === $uid) {
                $this->loggedInId = null;
            }
        }

        // Test helpers

        public function setLoggedInId(?int $id): void
        {
            $this->loggedInId = $id;
        }

        public function setDefaultGroups(array $groups): void
        {
            $this->groups = $groups;
        }

        public function seedUser(int $id, string $username): void
        {
            $this->users[$id] = ['username' => $username, 'active' => true];
        }

        public function getProperty(int $uid, string $name): ?string
        {
            return $this->properties[$uid][$name] ?? null;
        }

        public function getCalls(string $method = null): array
        {
            if ($method === null) {
                return $this->calls;
            }
            return array_filter($this->calls, fn($c) => $c['method'] === $method);
        }

        public function reset(): void
        {
            $this->users = [];
            $this->properties = [];
            $this->groups = [];
            $this->nextId = 100;
            $this->loggedInId = null;
            $this->calls = [];
        }
    }
}

if (!class_exists('MockMAMSModule')) {
    class MockMAMSModule
    {
        private MockMAMSManipulator $manipulator;

        public function __construct()
        {
            $this->manipulator = new MockMAMSManipulator();
        }

        public function GetManipulator(): MockMAMSManipulator
        {
            return $this->manipulator;
        }
    }
}
