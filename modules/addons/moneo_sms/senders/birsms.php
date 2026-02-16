<?php

class birsms extends MoneoSms implements SmsSenderInterface
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

        $params   = $this->getParams();
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Submit xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="SmsApi">'
            . '<Credential><Password>' . ($params->pass ?? '') . '</Password><Username>' . ($params->user ?? '') . '</Username></Credential>'
            . '<DataCoding>Default</DataCoding>'
            . '<Header><From>' . ($params->senderid ?? '') . '</From></Header>'
            . '<Message>' . $this->message . '</Message>'
            . '<To xmlns:d2p1="http://schemas.microsoft.com/2003/10/Serialization/Arrays">'
            . '<d2p1:string>' . $this->gsmnumber . '</d2p1:string></To></Submit>';

        $result = $this->curlPost('http://api.1sms.com.tr/v1/xml/syncreply/Submit', $xml_data, ['Content-Type: text/xml']);
        if ($result === false) {
            $log[]   = 'Could not connect to BirSMS API';
            $error[] = 'Could not connect to BirSMS API';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $parsed = $this->xmlToArray($result);
        $code   = $parsed['SUBMITRESPONSE']['RESPONSE']['STATUS']['CODE'] ?? '';
        $msgid  = $parsed['SUBMITRESPONSE']['RESPONSE']['MESSAGEID'] ?? null;

        $log[] = 'Response code: ' . $code;

        if ($code != 200) {
            $log[]   = 'Error: Message could not be sent.';
            $error[] = 'Message could not be sent. Code: ' . $code;
        } else {
            $log[] = 'Message sent successfully.';
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass)) {
            $xml_data = '<GetBalance xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="SmsApi">'
                . '<Credential><Password>' . $params->pass . '</Password><Username>' . $params->user . '</Username></Credential></GetBalance>';

            $output = $this->curlPost('http://api.1sms.com.tr/v1/xml/syncreply/GetBalance', $xml_data, ['Content-Type: text/xml']);
            if ($output === false) return null;

            $parsed = $this->xmlToArray($output);
            $balance = $parsed['GETBALANCERESPONSE']['RESPONSE']['BALANCE']['MAIN'] ?? null;
            return $balance ? substr($balance, 0, -5) : null;
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $params = $this->getParams();
        if (!empty($params->user) && !empty($params->pass) && $msgId) {
            $xml_data = '<Query xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="SmsApi">'
                . '<Credential><Password>' . $params->pass . '</Password><Username>' . $params->user . '</Username></Credential>'
                . '<MessageId>' . $msgId . '</MessageId></Query>';

            $output = $this->curlPost('http://api.1sms.com.tr/v1/xml/syncreply/Query', $xml_data, ['Content-Type: text/xml']);
            if ($output === false) return null;

            $parsed = $this->xmlToArray($output);
            $code = $parsed['QUERYRESPONSE']['RESPONSE']['STATUS']['CODE'] ?? '';
            return ($code == 200) ? 'success' : 'error';
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
        return $message;
    }

    private function xmlToArray(string $xml): array
    {
        if (empty($xml)) return [];
        $parsed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($parsed === false) return [];
        $json = json_encode($parsed);
        return json_decode($json, true) ?: [];
    }
}

return [
    'value'  => 'birsms',
    'label'  => 'BirSMS',
    'fields' => ['user', 'pass'],
];
