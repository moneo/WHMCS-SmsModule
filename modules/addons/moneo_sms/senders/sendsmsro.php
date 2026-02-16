<?php

class sendsmsro extends MoneoSms implements SmsSenderInterface
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
        $url    = 'https://api.sendsms.ro/json?action=message_send&username=' . urlencode($params->user ?? '') . '&password=' . urlencode($params->pass ?? '') . '&to=' . urlencode($this->gsmnumber) . '&text=' . urlencode($this->message) . '&from=' . urlencode($params->senderid ?? '');
        $log[]  = 'Request URL: ' . $url;

        $result = $this->curlGet($url);
        if ($result === false) {
            $log[]   = 'Could not connect to SendSms.ro API';
            $error[] = 'Could not connect to SendSms.ro API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Server response: ' . $result;

        $decoded = json_decode($result, true);
        if (isset($decoded['status']) && $decoded['status'] == 1) {
            $log[] = 'Message sent.';
            $msgid = $decoded['details'] ?? null;
        } else {
            $log[]   = 'Error: ' . ($decoded['message'] ?? 'Unknown error');
            $error[] = 'Error: ' . ($decoded['message'] ?? 'Unknown error');
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
    'value'  => 'sendsmsro',
    'label'  => 'SendSms.ro',
    'fields' => ['user', 'pass'],
];
