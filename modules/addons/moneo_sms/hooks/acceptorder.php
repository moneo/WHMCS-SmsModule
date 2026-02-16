<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'AcceptOrder',
    'function'       => 'AcceptOrder_SMS',
    'description'    => ['turkish' => 'Sipariş onaylandığında mesaj gönderir', 'english' => 'Send SMS when an order is accepted'],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Dear {firstname} {lastname}, your order #{orderid} has been accepted.',
    'variables'      => '{firstname},{lastname},{orderid}',
];

if (!function_exists('AcceptOrder_SMS')) {
    function AcceptOrder_SMS($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $user = Capsule::table('tblclients as a')
            ->join('tblcustomfieldsvalues as b', 'b.relid', '=', 'a.id')
            ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.id')
            ->whereIn('a.id', function ($query) use ($args) {
                $query->select('userid')->from('tblorders')->where('id', $args['orderid'] ?? 0);
            })
            ->where('b.fieldid', (int) $settings->gsmnumberfield)
            ->where('c.fieldid', (int) $settings->wantsmsfield)
            ->where('c.value', 'on')
            ->select('a.id', 'a.firstname', 'a.lastname', 'b.value as gsmnumber')
            ->first();

        if ($user) {
            $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname, $args['orderid'] ?? '']);

            $class->setGsmnumber($user->gsmnumber);
            $class->setUserid($user->id);
            $class->setMessage($message);
            $class->send();
        }
    }
}

return $hook;
