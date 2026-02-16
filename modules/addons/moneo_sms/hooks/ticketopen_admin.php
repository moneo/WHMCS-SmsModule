<?php
$hook = [
    'hook'           => 'TicketOpen',
    'function'       => 'TicketOpen_admin',
    'description'    => ['turkish' => 'Yeni ticket açıldığında admin bilgilendirilir', 'english' => 'Notify admins when a new ticket is opened'],
    'type'           => 'admin',
    'extra'          => '',
    'defaultmessage' => 'A new support ticket has been opened: {subject}',
    'variables'      => '{subject}',
];

if (!function_exists('TicketOpen_admin')) {
    function TicketOpen_admin($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api) return;

        $admingsm = explode(',', $template->admingsm ?? '');

        $message = $class->replaceTemplateVars($template->template, $template->variables, [$args['subject'] ?? '']);

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
