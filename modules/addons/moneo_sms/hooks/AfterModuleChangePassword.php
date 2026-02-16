<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'AfterModuleChangePassword',
    'function'       => 'AfterModuleChangePassword',
    'description'    => ['turkish' => 'Hosting hesabı şifresi değiştiğinde gönderir', 'english' => 'Send SMS after hosting password is changed'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, the password for {domain} has been changed. Username: {username} Password: {password}',
    'variables'      => '{firstname},{lastname},{domain},{username},{password}',
];

if (!function_exists('AfterModuleChangePassword')) {
    function AfterModuleChangePassword($args)
    {
        $type = $args['params']['producttype'] ?? '';
        if ($type !== 'hostingaccount') return;

        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $userId = $args['params']['clientsdetails']['userid'] ?? 0;

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
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname, $args['params']['domain'] ?? '', $args['params']['username'] ?? '', $args['params']['password'] ?? '']);

            $class->setGsmnumber($user->gsmnumber);
            $class->setUserid($userId);
            $class->setMessage($message);
            $class->send();
        }
    }
}

return $hook;
