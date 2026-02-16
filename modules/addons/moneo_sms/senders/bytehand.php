<?php

class bytehand extends MoneoSms implements SmsSenderInterface
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
        $url    = 'http://bytehand.com:3800/send?id=' . urlencode($params->user ?? '') . '&key=' . urlencode($params->pass ?? '') . '&to=' . urlencode($this->gsmnumber) . '&from=' . urlencode($params->senderid ?? '') . '&text=' . urlencode($this->message);
        $result = $this->curlGet($url);

        if ($result === false) {
            $log[]   = 'Could not connect to ByteHand API';
            $error[] = 'Could not connect to ByteHand API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $decoded = json_decode($result);
        if ($decoded && isset($decoded->status) && $decoded->status == 0) {
            $log[] = 'Message sent.';
            $msgid = $decoded->description ?? null;
        } else {
            $log[]   = 'Error sending message.';
            $error[] = 'Check status, looks like problem with a connection or credentials.';
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass)) {
            $result = $this->curlGet('http://bytehand.com:3800/balance?id=' . urlencode($params->user) . '&key=' . urlencode($params->pass));
            if ($result === false) return null;
            $decoded = json_decode($result);
            return ($decoded && $decoded->status == 0) ? ($decoded->description ?? null) : null;
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass) && $msgId) {
            $result = $this->curlGet('http://bytehand.com:3800/status?id=' . urlencode($params->user) . '&key=' . urlencode($params->pass) . '&message=' . urlencode($msgId));
            if ($result === false) return null;
            $decoded = json_decode($result);
            if ($decoded && $decoded->status == 0) {
                return in_array($decoded->description ?? '', ['DELIVERED', 'ACCEPTED']) ? 'success' : 'error';
            }
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
    'value'  => 'bytehand',
    'label'  => 'ByteHand',
    'fields' => ['user', 'pass'],
];
