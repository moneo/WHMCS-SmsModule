<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'ClientChangePassword',
    'function'       => 'ClientChangePassword',
    'description'    => ['turkish' => 'Müşteri şifresini değiştirdiğinde mesaj gönderir', 'english' => 'Send SMS when client changes account password'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, your password has been changed. If you did not perform this action, please contact us immediately.',
    'variables'      => '{firstname},{lastname}',
];

if (!function_exists('ClientChangePassword')) {
    function ClientChangePassword($args)
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
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname]);

            $class->setGsmnumber($user->gsmnumber);
            $class->setUserid($user->id);
            $class->setMessage($message);
            $class->send();
        }
    }
}

return $hook;
