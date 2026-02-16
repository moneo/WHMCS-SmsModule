<?php

class mutlucell extends MoneoSms implements SmsSenderInterface
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

        $params   = $this->getParams();
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<smspack ka="' . ($params->user ?? '') . '" pwd="' . ($params->pass ?? '') . '" org="' . ($params->senderid ?? '') . '">'
            . '<mesaj><metin>' . $this->message . '</metin><nums>' . $this->gsmnumber . '</nums></mesaj>'
            . '</smspack>';

        $result = $this->curlPost('https://smsgw.mutlucell.com/smsgw-ws/sndblkex', $xml_data, ['Content-Type: text/xml']);
        if ($result === false) {
            $log[]   = 'Could not connect to MutluCell API';
            $error[] = 'Could not connect to MutluCell API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $log[] = 'Response code: ' . $result;

        $errorMap = [
            '20' => 'Posted XML is missing or invalid',
            '21' => 'You do not own this originator',
            '22' => 'Insufficient credits',
            '23' => 'Invalid username or password',
            '24' => 'Another operation is currently active',
            '25' => 'Please try again in 1-2 minutes',
            '30' => 'Account not activated',
        ];

        $parts = explode(' ', $result);
        $code  = $parts[0] ?? '';

        if (isset($errorMap[$code])) {
            $log[]   = 'Error: ' . $errorMap[$code];
            $error[] = $errorMap[$code];
        } else {
            $log[] = 'Message sent successfully.';
            $msgid = substr($code, 1, -4) ?: $code;
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass)) {
            $xml_data = '<?xml version="1.0" encoding="UTF-8"?><smskredi ka="' . $params->user . '" pwd="' . $params->pass . '" />';
            $output = $this->curlPost('https://smsgw.mutlucell.com/smsgw-ws/gtcrdtex', $xml_data, ['Content-Type: text/xml']);
            if ($output === false) return null;

            $parts = explode(' ', $output);
            if (($parts[0] ?? '') != '20' && ($parts[0] ?? '') != '23' && $output) {
                return substr($output, 1, -2);
            }
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass) && $msgId) {
            $xml_data = '<?xml version="1.0" encoding="UTF-8"?><smsrapor ka="' . $params->user . '" pwd="' . $params->pass . '" id="' . $msgId . '" />';
            $output = $this->curlPost('https://smsgw.mutlucell.com/smsgw-ws/gtblkrprtex', $xml_data, ['Content-Type: text/xml']);
            if ($output === false) return null;

            return ($output != '20' && $output != '23' && $output != '30') ? 'success' : 'error';
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
    'value'  => 'mutlucell',
    'label'  => 'MutluCell',
    'fields' => ['user', 'pass'],
];
