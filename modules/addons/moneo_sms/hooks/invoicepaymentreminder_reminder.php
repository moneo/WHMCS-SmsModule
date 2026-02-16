<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'InvoicePaymentReminder',
    'function'       => 'InvoicePaymentReminder_Reminder',
    'description'    => ['turkish' => 'Ödenmemiş fatura için bilgi mesajı gönderir', 'english' => 'Send SMS as invoice payment reminder'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, you have an unpaid invoice due on {duedate}. Please visit our website for details.',
    'variables'      => '{firstname},{lastname},{duedate}',
];

if (!function_exists('InvoicePaymentReminder_Reminder')) {
    function InvoicePaymentReminder_Reminder($args)
    {
        if (($args['type'] ?? '') !== 'reminder') return;

        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $invoice = Capsule::table('tblinvoices as a')
            ->join('tblclients as b', 'b.id', '=', 'a.userid')
            ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.userid')
            ->join('tblcustomfieldsvalues as d', 'd.relid', '=', 'a.userid')
            ->where('a.id', $args['invoiceid'] ?? 0)
            ->where('c.fieldid', (int) $settings->gsmnumberfield)
            ->where('d.fieldid', (int) $settings->wantsmsfield)
            ->where('d.value', 'on')
            ->select('a.duedate', 'b.id as userid', 'b.firstname', 'b.lastname', 'c.value as gsmnumber')
            ->first();

        if ($invoice) {
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$invoice->firstname, $invoice->lastname, $class->changeDateFormat($invoice->duedate)]);

            $class->setGsmnumber($invoice->gsmnumber);
            $class->setMessage($message);
            $class->setUserid($invoice->userid);
            $class->send();
        }
    }
}

return $hook;
