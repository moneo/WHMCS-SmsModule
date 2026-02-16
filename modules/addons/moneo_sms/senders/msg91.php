<?php

class msg91 extends MoneoSms implements SmsSenderInterface
{
    public function __construct(string $message, string $gsmnumber)
    {
        $this->message   = $this->utilmessage($message);
        $this->gsmnumber = $this->utilgsmnumber($gsmnumber);
    }

    public function send(): array
    {
        $log   = [];
        $error = [];
        $msgid = null;

        if ($this->gsmnumber === 'numbererror') {
            $log[]   = 'Number format error: ' . $this->gsmnumber;
            $error[] = 'Number format error: ' . $this->gsmnumber;
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $params  = $this->getParams();
        $authKey = $params->authkey ?? '';
        $baseurl = 'https://control.msg91.com';

        $senderId = substr(trim($params->senderid ?? ''), 0, 6);
        $smsRoute = ctype_digit($params->route ?? '') ? $params->route : '1';
        $unicodeSupport = ctype_digit($params->unicode ?? '') ? $params->unicode : '0';
        $flashSupport   = ctype_digit($params->flash ?? '') ? $params->flash : '0';

        $text = urlencode($this->message);
        $to   = $this->gsmnumber;

        $url = "$baseurl/api/validate.php?authkey=$authKey&type=$smsRoute";
        $ret = $this->curlGet($url);

        if ($ret === false) {
            $log[]   = 'Could not connect to msg91 API';
            $error[] = 'Could not connect to msg91 API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Validation response: ' . $ret;
        $lines = explode("\n", $ret);
        $sess = explode(',', $lines[0] ?? '');

        if (($sess[0] ?? '') === 'Valid') {
            $url = "$baseurl/api/sendhttp.php?authkey=$authKey&mobiles=$to&message=$text&sender=$senderId&route=$smsRoute&unicode=$unicodeSupport&flash=$flashSupport";
            $sendResult = $this->curlGet($url);

            if ($sendResult === false) {
                $log[]   = 'Could not send message via msg91';
                $error[] = 'Could not send message via msg91';
            } else {
                $send = array_map('trim', explode(':', $sendResult));
                if (($send[0] ?? '') !== 'CODE' && ($send[0] ?? '') !== 'Please') {
                    $log[] = 'Message sent.';
                    $msgid = $send[0] ?? null;
                } else {
                    $log[]   = 'Message could not be sent. Error: ' . $sendResult;
                    $error[] = 'Message could not be sent. Error: ' . $sendResult;
                }
            }
        } else {
            $log[]   = 'Authentication failed. Error: ' . ($lines[0] ?? 'Unknown');
            $error[] = 'Authentication failed. ' . ($lines[0] ?? '');
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        $params = $this->getParams();
        if (!empty($params->authkey) && !empty($params->route)) {
            $url    = "https://control.msg91.com/api/balance.php?authkey=$params->authkey&type=$params->route";
            $result = $this->curlGet($url);
            if ($result === false) return null;
            $parts = array_map('trim', explode(':', $result));
            return (($parts[1] ?? '') != '001' && ($parts[1] ?? '') != '002') ? ($parts[0] ?? null) : null;
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $params = $this->getParams();
        if (!empty($params->authkey) && $msgId) {
            $url    = "https://control.msg91.com/api/check_delivery.php?authkey=$params->authkey&requestid=$msgId";
            $result = $this->curlGet($url);
            if ($result === false) return null;
            $parts = array_map('trim', explode(':', $result));
            return (($parts[1] ?? '') == '001' || ($parts[1] ?? '') == '002') ? 'error' : 'success';
        }
        return null;
    }

    public function utilgsmnumber(string $number): string
    {
        if (strlen($number) == 10) {
            $number = '91' . $number;
        }
        if (substr($number, 0, 2) !== '91') {
            return 'numbererror';
        }
        return $number;
    }

    public function utilmessage(string $message): string
    {
        return $message;
    }
}

return [
    'value'  => 'msg91',
    'label'  => 'msg91.com (India)',
    'fields' => ['authkey', 'route', 'flash', 'unicode', 'ignoreNdnc'],
];
