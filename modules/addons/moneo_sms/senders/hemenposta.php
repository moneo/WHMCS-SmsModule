<?php

class hemenposta extends MoneoSms implements SmsSenderInterface
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

        $params    = $this->getParams();
        $xmlString = '<SMS><authentification><username>' . ($params->user ?? '') . '</username><password>' . ($params->pass ?? '') . '</password></authentification>'
            . '<message><sender>' . ($params->senderid ?? '') . '</sender></message>'
            . '<recipients><text>' . $this->message . '</text><gsm>' . $this->gsmnumber . '</gsm></recipients></SMS>';

        $result = $this->curlPost('http://sms.modexi.com/service/sendxml', $xmlString);
        if ($result === false) {
            $log[]   = 'Could not connect to HemenPosta API';
            $error[] = 'Could not connect to HemenPosta API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Server response: ' . $result;

        if (preg_match('/<status>(.*?)<\/status>(.*?)<DESC>(.*?)<\/DESC>(.*?)<package>(.*?)<\/package>/si', $result, $matches)) {
            $statusCode    = $matches[1];
            $statusMessage = $matches[3];
            $msgid         = $matches[5];

            if ((int)$statusCode > 0) {
                $log[] = 'Message sent.';
            } else {
                $log[]   = 'Failed to send. Error: ' . $statusMessage;
                $error[] = 'Failed to send. Code: ' . $statusCode;
            }
        } else {
            $log[]   = 'Failed to send. Unexpected response.';
            $error[] = 'Failed to send. Unexpected response: ' . $result;
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
    'value'  => 'hemenposta',
    'label'  => 'HemenPosta',
    'fields' => ['user', 'pass'],
];
