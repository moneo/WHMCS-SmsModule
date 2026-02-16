<?php
$hook = [
    'hook'           => 'AfterRegistrarRegistrationFailed',
    'function'       => 'AfterRegistrarRegistrationFailed_admin',
    'description'    => ['turkish' => 'Domain kayıt başarısız olduğunda admin bilgilendirilir', 'english' => 'Notify admins when domain registration fails'],
    'type'           => 'admin',
    'extra'          => '',
    'defaultmessage' => 'Domain registration failed: {domain}',
    'variables'      => '{domain}',
];

if (!function_exists('AfterRegistrarRegistrationFailed_admin')) {
    function AfterRegistrarRegistrationFailed_admin($args)
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
