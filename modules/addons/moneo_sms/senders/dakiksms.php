<?php

class dakiksms extends MoneoSms implements SmsSenderInterface
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
            . '<SMS><oturum><kullanici>' . ($params->user ?? '') . '</kullanici><sifre>' . ($params->pass ?? '') . '</sifre></oturum>'
            . '<mesaj><baslik>' . ($params->senderid ?? '') . '</baslik><metin>' . $this->message . '</metin>'
            . '<alicilar>' . $this->gsmnumber . '</alicilar></mesaj></SMS>';

        $result = $this->curlPost('http://www.dakiksms.com/api/xml_api.php', $xml_data, ['Content-Type: text/xml']);
        if ($result === false) {
            $log[]   = 'Could not connect to Dakik SMS API';
            $error[] = 'Could not connect to Dakik SMS API';
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
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass)) {
            $xml_data = '<?xml version="1.0" encoding="UTF-8"?><RAPOR><oturum><kullanici>' . $params->user . '</kullanici><sifre>' . $params->pass . '</sifre></oturum></RAPOR>';
            $output = $this->curlPost('http://www.dakiksms.com/api/xml_bakiye.php', $xml_data, ['Content-Type: text/xml']);
            return ($output !== false && $output) ? substr($output, 24, -1) : null;
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass) && $msgId) {
            $xml_data = '<?xml version="1.0" encoding="UTF-8"?><RAPOR><oturum><kullanici>' . $params->user . '</kullanici><sifre>' . $params->pass . '</sifre></oturum><rapor><raporid>' . $msgId . '</raporid></rapor></RAPOR>';
            $output = $this->curlPost('http://www.dakiksms.com/api/xml_rapor.php', $xml_data, ['Content-Type: text/xml']);
            return ($output !== false && $output) ? 'success' : 'error';
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
    'value'  => 'dakiksms',
    'label'  => 'Dakik SMS',
    'fields' => ['user', 'pass'],
];
