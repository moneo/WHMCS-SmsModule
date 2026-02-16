<?php
use WHMCS\Database\Capsule;

$hook = [
    'hook'           => 'DailyCronJob',
    'function'       => 'DomainRenewalNotice',
    'description'    => ['turkish' => 'Domainin yenilenmesine {x} gün kala mesaj gönderir', 'english' => 'Send SMS {x} days before domain expiry'],
    'type'           => 'client',
    'extra'          => '15',
    'defaultmessage' => 'Dear {firstname} {lastname}, your domain {domain} will expire on {expirydate} ({x} days remaining). Please renew it.',
    'variables'      => '{firstname},{lastname},{domain},{expirydate},{x}',
];

if (!function_exists('DomainRenewalNotice')) {
    function DomainRenewalNotice($args)
    {
        $class    = new MoneoSms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        if (!$template || $template->active == 0) return;

        $settings = $class->getSettings();
        if (!$settings || !$settings->api || !$settings->gsmnumberfield || !$settings->wantsmsfield) return;

        $extra   = (int) ($template->extra ?: 15);
        $today   = date('Y-m-d');
        $domains = Capsule::table('tbldomains')
            ->where('status', 'Active')
            ->select('userid', 'domain', 'expirydate')
            ->get();

        foreach ($domains as $domainRow) {
            $parts = explode('-', $domainRow->expirydate);
            if (count($parts) !== 3) continue;

            $noticeDate = mktime(0, 0, 0, (int)$parts[1], (int)$parts[2] - $extra, (int)$parts[0]);
            if (date('Y-m-d', $noticeDate) !== $today) continue;

            $user = Capsule::table('tblclients as a')
                ->join('tblcustomfieldsvalues as b', 'b.relid', '=', 'a.id')
                ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.id')
                ->where('a.id', $domainRow->userid)
                ->where('b.fieldid', (int) $settings->gsmnumberfield)
                ->where('c.fieldid', (int) $settings->wantsmsfield)
                ->where('c.value', 'on')
                ->select('a.id as userid', 'a.firstname', 'a.lastname', 'b.value as gsmnumber')
                ->first();

            if ($user) {
                $message = $class->replaceTemplateVars($template->template, $template->variables, [$user->firstname, $user->lastname, $domainRow->domain, $domainRow->expirydate, $extra]);

                $class->setGsmnumber($user->gsmnumber);
                $class->setMessage($message);
                $class->setUserid($user->userid);
                $class->send();
            }
        }
    }
}

return $hook;
