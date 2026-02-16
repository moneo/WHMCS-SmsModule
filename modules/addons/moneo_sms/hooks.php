<?php
/**
 * Moneo SMS - Hook Registration
 *
 * @package    MoneoSms
 * @license    GPLv3 (https://www.gnu.org/licenses/gpl-3.0.txt)
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . '/smsclass.php';
require_once __DIR__ . '/senders/SmsSenderInterface.php';

try {
    $class = new MoneoSms();
    $hooks = $class->getHooks();

    foreach ($hooks as $hook) {
        if (
            !empty($hook['hook']) && !empty($hook['function'])
            && is_string($hook['hook']) && is_string($hook['function'])
        ) {
            add_hook($hook['hook'], 1, $hook['function']);
        }
    }
} catch (\Exception $e) {
    if (function_exists('logActivity')) {
        logActivity('Moneo SMS: Failed to register hooks: ' . $e->getMessage());
    }
}
