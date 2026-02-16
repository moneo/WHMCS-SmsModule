<?php
$hook = [
    'hook'           => 'AfterRegistrarRegistration',
    'function'       => 'AfterRegistrarRegistration_admin',
    'description'    => ['turkish' => 'Yeni domain kayıt edildiğinde admin bilgilendirilir', 'english' => 'Notify admins when a domain is registered'],
    'type'           => 'admin',
    'extra'          => '',
    'defaultmessage' => 'A new domain has been registered: {domain}',
    'variables'      => '{domain}',
];

if (!function_exists('AfterRegistrarRegistration_admin')) {
    function AfterRegistrarRegistration_admin($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api) return;

        $admingsm = explode(',', $template->admingsm ?? '');
        $domain   = ($args['params']['sld'] ?? '') . '.' . ($args['params']['tld'] ?? '');

        $message = $class->replaceTemplateVars($template->template, $template->variables, [$domain]);

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
