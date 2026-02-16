<?php

class routesms extends MoneoSms implements SmsSenderInterface
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
        $url    = 'http://121.241.242.114:8080/bulksms/bulksms?username=' . urlencode($params->user ?? '') . '&password=' . urlencode($params->pass ?? '') . '&type=1&dlr=0&destination=' . urlencode($this->gsmnumber) . '&source=' . urlencode($params->senderid ?? '') . '&message=' . urlencode($this->message);
        $log[]  = 'Request URL: ' . $url;

        $result = $this->curlGet($url);
        if ($result === false) {
            $log[]   = 'Could not connect to RouteSMS API';
            $error[] = 'Could not connect to RouteSMS API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Server response: ' . $result;

        $errorMap = [
            '1702' => 'Invalid URL Error, a parameter was not provided or left blank',
            '1703' => 'Invalid value in username or password field',
            '1704' => 'Invalid value in type field',
            '1705' => 'Invalid message',
            '1706' => 'Invalid destination',
            '1707' => 'Invalid source (sender)',
            '1708' => 'Invalid value for dlr field',
            '1709' => 'User validation failed',
            '1710' => 'Internal error',
            '1025' => 'Insufficient credit',
        ];

        $parts = explode('|', $result);
        $code  = $parts[0] ?? '';

        if ($code === '1701') {
            $log[] = 'Message sent.';
            $msgid = $parts[2] ?? null;
        } elseif (isset($errorMap[$code])) {
            $log[]   = 'Error: ' . $errorMap[$code];
            $error[] = $errorMap[$code];
        } else {
            $log[]   = 'Failed to send. Response: ' . $result;
            $error[] = 'Failed to send. Response: ' . $result;
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
    'value'  => 'routesms',
    'label'  => 'Route SMS',
    'fields' => ['user', 'pass'],
];
