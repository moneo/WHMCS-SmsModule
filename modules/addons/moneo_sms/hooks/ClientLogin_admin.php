<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'ClientLogin',
    'function'       => 'ClientLogin_admin',
    'description'    => ['turkish' => 'Müşteri giriş yaptığında admin bilgilendirilir', 'english' => 'Notify admins when a client logs in'],
    'type'           => 'admin',
    'extra'          => '',
    'defaultmessage' => '{firstname} {lastname} logged into the client area.',
    'variables'      => '{firstname},{lastname}',
];

if (!function_exists('ClientLogin_admin')) {
    function ClientLogin_admin($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $admingsm = explode(',', $template->admingsm ?? '');

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
}

return $hook;
