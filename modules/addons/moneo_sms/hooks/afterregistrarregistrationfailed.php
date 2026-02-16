<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'AfterRegistrarRegistrationFailed',
    'function'       => 'AfterRegistrarRegistrationFailed',
    'description'    => ['turkish' => 'Domain kayıt edilirken hata oluşursa mesaj gönderilir', 'english' => 'Send SMS when domain registration fails'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, your domain {domain} could not be registered. Please contact us.',
    'variables'      => '{firstname},{lastname},{domain}',
];

if (!function_exists('AfterRegistrarRegistrationFailed')) {
    function AfterRegistrarRegistrationFailed($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $userId = $args['params']['userid'] ?? 0;
        $domain = ($args['params']['sld'] ?? '') . '.' . ($args['params']['tld'] ?? '');

        $user = Capsule::table('tblclients as a')
            ->join('tblcustomfieldsvalues as b', 'b.relid', '=', 'a.id')
            ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.id')
            ->where('a.id', $userId)
            ->where('b.fieldid', (int) $settings->gsmnumberfield)
            ->where('c.fieldid', (int) $settings->wantsmsfield)
            ->where('c.value', 'on')
            ->select('a.id', 'a.firstname', 'a.lastname', 'b.value as gsmnumber')
            ->first();

        if ($user) {
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname, $domain]);

            $class->setGsmnumber($user->gsmnumber);
            $class->setUserid($userId);
            $class->setMessage($message);
            $class->send();
        }
    }
}

return $hook;
