<?php

class ucuzsmsal extends MoneoSms implements SmsSenderInterface
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
        $url    = 'http://www.ucuzsmsal.com/api/index.php?act=sendsms&user=' . urlencode($params->user ?? '') . '&pass=' . urlencode($params->pass ?? '') . '&orgin=' . urlencode($params->senderid ?? '') . '&message=' . urlencode($this->message) . '&numbers=' . urlencode($this->gsmnumber);

        $result = $this->curlGet($url);
        if ($result === false) {
            $log[]   = 'Could not connect to UcuzSmsAl API';
            $error[] = 'Could not connect to UcuzSmsAl API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Server response: ' . $result;

        $parts = explode('|', $result);
        if (($parts[0] ?? '') === 'OK') {
            $log[] = 'Message sent.';
            $msgid = $parts[1] ?? null;
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
        if (strlen($number) == 11) {
            $number = substr($number, 1);
        } elseif (strlen($number) == 12) {
            $number = substr($number, 2);
        }

        if (strlen($number) == 10 && substr($number, 0, 1) !== '5') {
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
    'value'  => 'ucuzsmsal',
    'label'  => 'Ucuz Sms Al',
    'fields' => ['user', 'pass'],
];
