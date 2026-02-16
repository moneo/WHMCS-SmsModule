<?php

class netgsm extends MoneoSms implements SmsSenderInterface
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

        if ($this->gsmnumber === 'numbererror' || $this->gsmnumber === 'error') {
            $log[]   = 'Number format error: ' . $this->gsmnumber;
            $error[] = 'Number format error: ' . $this->gsmnumber;
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $params = $this->getParams();
        $url    = "https://api.netgsm.com.tr/bulkhttppost.asp?usercode=$params->user&password=$params->pass&gsmno=$this->gsmnumber&message=" . urlencode($this->message) . "&msgheader=$params->senderid";
        $log[]  = 'Request URL: ' . $url;

        $result = $this->curlGet($url);
        if ($result === false) {
            $log[]   = 'Could not connect to NetGsm API';
            $error[] = 'Could not connect to NetGsm API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $return = $result;
        $log[]  = 'Server response: ' . $result;

        $parts = explode(' ', $result);
        $code  = $parts[0] ?? '';

        $errorMap = [
            '10' => 'Invalid phone number',
            '20' => 'Message text is empty or too long',
            '30' => 'User credentials not found',
            '40' => 'Invalid message header',
            '50' => 'Insufficient credits',
            '60' => 'Phone number not defined',
            '70' => 'Invalid message header',
        ];

        if (in_array($code, ['00', '01', '02'])) {
            $log[] = 'Message sent.';
            $msgid = $parts[1] ?? null;
            $log[] = 'Message ID: ' . ($msgid ?? 'N/A');
        } elseif (isset($errorMap[$code])) {
            $log[]   = 'Failed to send. Error: ' . $errorMap[$code];
            $error[] = 'Failed to send. Error: ' . $errorMap[$code];
        } else {
            $log[]   = 'Failed to send. Response: ' . $return;
            $error[] = 'Failed to send. Response: ' . $return;
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass)) {
            $xmlString = "<mainbody><header><company>Netgsm</company><usercode>$params->user</usercode><password>$params->pass</password><stip>1</stip></header></mainbody>";
            $result = $this->curlPost('https://api.netgsm.com.tr/xmlpaketkampanya.asp', $xmlString);

            $url     = "https://api.netgsm.com.tr/get_kredi.asp?usercode=$params->user&password=$params->pass";
            $result2 = $this->curlGet($url);
            $result2 = explode(' ', $result2 ?: '');

            $result = str_replace(['|', '<BR>'], ' ', $result ?: '');
            return $result . ' | ' . ($result2[1] ?? '0') . ' TL';
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass) && $msgId) {
            $url    = "https://api.netgsm.com.tr/httpbulkrapor.asp?usercode=$params->user&password=$params->pass&bulkid=$msgId&type=0&status=1";
            $result = $this->curlGet($url);
            return ($result !== false && $result != '30' && $result != '60') ? 'success' : 'error';
        }
        return null;
    }

    public function utilgsmnumber(string $number): string
    {
        if (strlen($number) == 10) {
            $number = '90' . $number;
        } elseif (strlen($number) == 11) {
            $number = '9' . $number;
        }

        if (substr($number, 0, 3) !== '905') {
            return 'error';
        }

        return $number;
    }

    public function utilmessage(string $message): string
    {
        $changefrom = ['ı', 'İ', 'ü', 'Ü', 'ö', 'Ö', 'ğ', 'Ğ', 'ç', 'Ç', 'ş', 'Ş'];
        $changeto   = ['i', 'I', 'u', 'U', 'o', 'O', 'g', 'G', 'c', 'C', 's', 'S'];
        return str_replace($changefrom, $changeto, $message);
    }
}

return [
    'value'  => 'netgsm',
    'label'  => 'NetGsm',
    'fields' => ['user', 'pass'],
];
