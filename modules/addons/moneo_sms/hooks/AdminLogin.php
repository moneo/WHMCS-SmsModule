<?php
$hook = [
    'hook'           => 'AdminLogin',
    'function'       => 'AdminLogin_admin',
    'description'    => ['turkish' => 'Admin paneline giriş yapıldığında mesaj gönderir', 'english' => 'Send SMS to admins when an admin logs in'],
    'type'           => 'admin',
    'extra'          => '',
    'defaultmessage' => '{username} logged into the admin panel.',
    'variables'      => '{username}',
];

if (!function_exists('AdminLogin_admin')) {
    function AdminLogin_admin($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api) return;

        $admingsm = explode(',', $template->admingsm ?? '');

        $message = $class->replaceTemplateVars($template->template, $template->variables, [$args['username'] ?? '']);

        foreach ($admingsm as $gsm) {
            $gsm = trim($gsm);
            if (!empty($gsm)) {
                $class->setGsmnumber($gsm);
                $class->setUserid(0);
                $class->setMessage($message);
                $class->send();
            }
        }
    }
}

return $hook;
