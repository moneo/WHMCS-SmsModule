<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'InvoicePaymentReminder',
    'function'       => 'InvoicePaymentReminder_Firstoverdue',
    'description'    => ['turkish' => 'Ödenmemiş faturanın ilk zaman aşımında mesaj gönderir', 'english' => 'Send SMS on first overdue invoice reminder'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, your invoice due on {duedate} is overdue. Please make payment as soon as possible.',
    'variables'      => '{firstname},{lastname},{duedate}',
];

if (!function_exists('InvoicePaymentReminder_Firstoverdue')) {
    function InvoicePaymentReminder_Firstoverdue($args)
    {
        if (($args['type'] ?? '') !== 'firstoverdue') return;

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
