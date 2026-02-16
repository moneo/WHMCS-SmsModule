<?php
$hook = [
    'hook'           => 'TicketUserReply',
    'function'       => 'TicketUserReply_admin',
    'description'    => ['turkish' => 'Müşteri ticket yanıtladığında admin bilgilendirilir', 'english' => 'Notify admins when a client replies to a ticket'],
    'type'           => 'admin',
    'extra'          => '',
    'defaultmessage' => 'A ticket has been updated by a client: {subject}',
    'variables'      => '{subject}',
];

if (!function_exists('TicketUserReply_admin')) {
    function TicketUserReply_admin($args)
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
