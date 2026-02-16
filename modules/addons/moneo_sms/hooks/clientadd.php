<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'ClientAdd',
    'function'       => 'ClientAdd',
    'description'    => ['turkish' => 'Müşteri kayıt olduktan sonra mesaj gönderir', 'english' => 'Send SMS after client registration'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, thank you for registering. Email: {email} Password: {password}',
    'variables'      => '{firstname},{lastname},{email},{password}',
];

if (!function_exists('ClientAdd')) {
    function ClientAdd($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $user = Capsule::table('tblclients as a')
            ->join('tblcustomfieldsvalues as b', 'b.relid', '=', 'a.id')
            ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.id')
            ->where('a.id', $args['userid'] ?? 0)
            ->where('b.fieldid', (int) $settings->gsmnumberfield)
            ->where('c.fieldid', (int) $settings->wantsmsfield)
            ->where('c.value', 'on')
            ->select('a.id', 'a.firstname', 'a.lastname', 'b.value as gsmnumber')
            ->first();

        if ($user) {
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname, $args['email'] ?? '', $args['password'] ?? '']);

            $class->setGsmnumber($user->gsmnumber);
            $class->setMessage($message);
            $class->setUserid((int) ($args['userid'] ?? 0));
            $class->send();
        }
    }
}

return $hook;
