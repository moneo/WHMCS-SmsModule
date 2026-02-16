<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'InvoicePaymentReminder',
    'function'       => 'InvoicePaymentReminder_secondoverdue',
    'description'    => ['turkish' => 'Ödenmemiş faturanın ikinci zaman aşımında mesaj gönderir', 'english' => 'Send SMS on second overdue invoice reminder'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, your invoice due on {duedate} is still unpaid. Please make payment immediately.',
    'variables'      => '{firstname},{lastname},{duedate}',
];

if (!function_exists('InvoicePaymentReminder_secondoverdue')) {
    function InvoicePaymentReminder_secondoverdue($args)
    {
        if (($args['type'] ?? '') !== 'secondoverdue') return;

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
