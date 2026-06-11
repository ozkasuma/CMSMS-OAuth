<?php
/**
 * PHPUnit Bootstrap for OAuth Module Tests
 */

require_once __DIR__ . '/../vendor/autoload.php';

// CMSMS constants
if (!defined('CMS_VERSION')) {
    define('CMS_VERSION', '2.2.19');
}

$GLOBALS['gCms'] = new stdClass();

// Load mocks
require_once __DIR__ . '/Mocks/CMSModule.php';
require_once __DIR__ . '/Mocks/CmsApp.php';
require_once __DIR__ . '/Mocks/Database.php';
require_once __DIR__ . '/Mocks/MAMS.php';

// Parameter cleaning constants
if (!defined('CLEAN_STRING')) define('CLEAN_STRING', 1);
if (!defined('CLEAN_INT')) define('CLEAN_INT', 2);
if (!defined('CLEAN_FLOAT')) define('CLEAN_FLOAT', 3);
if (!defined('CLEAN_NONE')) define('CLEAN_NONE', 4);
