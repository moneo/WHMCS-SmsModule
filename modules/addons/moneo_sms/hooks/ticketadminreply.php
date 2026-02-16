<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'TicketAdminReply',
    'function'       => 'TicketAdminReply',
    'description'    => ['turkish' => 'Bir ticket güncellendiğinde mesaj gönderir', 'english' => 'Send SMS when admin replies to a support ticket'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, your support ticket ({ticketsubject}) has been replied to.',
    'variables'      => '{firstname},{lastname},{ticketsubject}',
];

if (!function_exists('TicketAdminReply')) {
    function TicketAdminReply($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $ticketUserId = Capsule::table('tbltickets')->where('id', $args['ticketid'] ?? 0)->value('userid');
        if (!$ticketUserId) return;

        $user = Capsule::table('tblclients as a')
            ->join('tblcustomfieldsvalues as b', 'b.relid', '=', 'a.id')
            ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.id')
            ->where('a.id', $ticketUserId)
            ->where('b.fieldid', (int) $settings->gsmnumberfield)
            ->where('c.fieldid', (int) $settings->wantsmsfield)
            ->where('c.value', 'on')
            ->select('a.id', 'a.firstname', 'a.lastname', 'b.value as gsmnumber')
            ->first();

        if ($user) {
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname, $args['subject'] ?? '']);

            $class->setGsmnumber($user->gsmnumber);
            $class->setMessage($message);
            $class->setUserid($user->id);
            $class->send();
        }
    }
}

return $hook;
