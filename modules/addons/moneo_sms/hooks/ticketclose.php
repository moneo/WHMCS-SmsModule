<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'TicketClose',
    'function'       => 'TicketClose',
    'description'    => ['turkish' => 'Ticket kapatıldığında mesaj gönderir', 'english' => 'Send SMS when a support ticket is closed'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, your ticket #{ticketno} has been closed.',
    'variables'      => '{firstname},{lastname},{ticketno}',
];

if (!function_exists('TicketClose')) {
    function TicketClose($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $ticket = Capsule::table('tbltickets')->where('id', $args['ticketid'] ?? 0)->select('tid', 'userid')->first();
        if (!$ticket) return;

        $user = Capsule::table('tblclients as a')
            ->join('tblcustomfieldsvalues as b', 'b.relid', '=', 'a.id')
            ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.id')
            ->where('a.id', $ticket->userid)
            ->where('b.fieldid', (int) $settings->gsmnumberfield)
            ->where('c.fieldid', (int) $settings->wantsmsfield)
            ->where('c.value', 'on')
            ->select('a.id', 'a.firstname', 'a.lastname', 'b.value as gsmnumber')
            ->first();

        if ($user) {
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname, $ticket->tid]);

            $class->setGsmnumber($user->gsmnumber);
            $class->setMessage($message);
            $class->setUserid($user->id);
            $class->send();
        }
    }
}

return $hook;
