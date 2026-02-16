<?php
$hook = [
    'hook'           => 'ClientAdd',
    'function'       => 'ClientAdd_admin',
    'description'    => ['turkish' => 'Yeni müşteri kayıt olduğunda admin bilgilendirilir', 'english' => 'Notify admins when a new client registers'],
    'type'           => 'admin',
    'extra'          => '',
    'defaultmessage' => 'A new client has registered on your website.',
    'variables'      => '',
];

if (!function_exists('ClientAdd_admin')) {
    function ClientAdd_admin($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api) return;

        $admingsm = explode(',', $template->admingsm ?? '');

        foreach ($admingsm as $gsm) {
            $gsm = trim($gsm);
            if (!empty($gsm)) {
                $class->setGsmnumber($gsm);
                $class->setUserid(0);
                $class->setMessage($template->template);
                $class->send();
            }
        }
    }
}

return $hook;
