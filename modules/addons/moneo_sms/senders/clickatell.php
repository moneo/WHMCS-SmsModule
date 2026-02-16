<?php

class clickatell extends MoneoSms implements SmsSenderInterface
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
        $text    = urlencode($this->message);
        $to      = $this->gsmnumber;
        $baseurl = 'https://api.clickatell.com';

        $url = "$baseurl/http/auth?user=$params->user&password=$params->pass&api_id=$params->apiid&from=$params->senderid";
        $ret = $this->curlGet($url);

        if ($ret === false) {
            $log[]   = 'Could not connect to ClickAtell API';
            $error[] = 'Could not connect to ClickAtell API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Response from ClickAtell: ' . $ret;
        $lines = explode("\n", $ret);
        $sess  = explode(':', $lines[0] ?? '');

        if (($sess[0] ?? '') === 'OK') {
            $sess_id = trim($sess[1] ?? '');
            $url = "$baseurl/http/sendmsg?session_id=$sess_id&to=$to&text=$text&from=$params->senderid";
            $sendResult = $this->curlGet($url);

            if ($sendResult === false) {
                $log[]   = 'Could not send message via ClickAtell';
                $error[] = 'Could not send message via ClickAtell';
            } else {
                $send = explode(':', $sendResult);
                if (($send[0] ?? '') === 'ID') {
                    $log[] = 'Message sent.';
                    $msgid = trim($send[1] ?? '');
                } else {
                    $log[]   = 'Message could not be sent. Error: ' . $sendResult;
                    $error[] = 'Message could not be sent. Error: ' . $sendResult;
                }
            }
        } else {
            $log[]   = 'Authentication failed. Error: ' . $ret;
            $error[] = 'Authentication failed. Error: ' . $ret;
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass) && !empty($params->apiid)) {
            $url    = "https://api.clickatell.com/http/getbalance?api_id=$params->apiid&user=$params->user&password=$params->pass";
            $result = $this->curlGet($url);
            if ($result === false) return null;
            $parts = explode(' ', $result);
            return (($parts[1] ?? '') !== '001') ? ($parts[1] ?? null) : null;
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass) && !empty($params->apiid) && $msgId) {
            $url    = "https://api.clickatell.com/http/querymsg?user=$params->user&password=$params->pass&api_id=$params->apiid&apimsgid=$msgId";
            $result = $this->curlGet($url);
            if ($result === false) return null;
            $parts = explode(' ', $result);
            return (($parts[1] ?? '') == '001') ? 'error' : 'success';
        }
        return null;
    }

    public function utilgsmnumber(string $number): string
    {
        return $number;
    }

    public function utilmessage(string $message): string
    {
        return $message;
    }
}

return [
    'value'  => 'clickatell',
    'label'  => 'ClickAtell',
    'fields' => ['user', 'pass', 'apiid'],
];
