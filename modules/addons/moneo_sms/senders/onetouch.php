<?php

class onetouch extends MoneoSms implements SmsSenderInterface
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

        $params = $this->getParams();
        $url    = 'http://api.ottbd.org/api/v3/sendsms/plain?user=' . urlencode($params->user ?? '') . '&password=' . urlencode($params->pass ?? '') . '&GSM=' . urlencode($this->gsmnumber) . '&sender=' . urlencode($params->senderid ?? '') . '&messagetext=' . urlencode($this->message);
        $log[]  = 'Request URL: ' . $url;

        $result = $this->curlGet($url);
        if ($result === false) {
            $log[]   = 'Could not connect to OneTouch API';
            $error[] = 'Could not connect to OneTouch API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $xml = simplexml_load_string($result);
        if ($xml === false) {
            $log[]   = 'Invalid XML response from OneTouch';
            $error[] = 'Invalid XML response from OneTouch';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Server response received';

        $errorMap = [
            '-1'  => 'Error in processing the request',
            '-2'  => 'Not enough credit on account',
            '-3'  => 'Targeted network is not covered',
            '-5'  => 'Invalid username or password',
            '-6'  => 'Destination address is missing',
            '-10' => 'Username is missing',
            '-11' => 'Password is missing',
            '-13' => 'Number not recognized by platform',
            '-33' => 'Duplicated MessageID',
            '-34' => 'Sender name not allowed',
        ];

        $status = (string)($xml->result[0]->status ?? '');
        if ($status === '0') {
            $log[] = 'Message sent.';
            $msgid = (string)($xml->result[0]->messageid ?? '');
        } elseif (isset($errorMap[$status])) {
            $log[]   = 'Error: ' . $errorMap[$status];
            $error[] = $errorMap[$status];
        } else {
            $log[]   = 'Failed to send. Status: ' . $status;
            $error[] = 'Failed to send. Status: ' . $status;
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        return null;
    }

    public function report(string $msgId): ?string
    {
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
    'value'  => 'onetouch',
    'label'  => 'OneTouchSMS',
    'fields' => ['user', 'pass'],
];
