<?php

class smsgateway extends MoneoSms implements SmsSenderInterface
{
    private static string $baseUrl = 'https://smsgateway.me';

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

        $getDevicesResult = $this->makeRequest('/api/v3/devices', 'GET');
        $deviceID = $getDevicesResult['response']['result']['data'][0]['id'] ?? null;

        if (!$deviceID) {
            $json    = json_encode($getDevicesResult, JSON_PRETTY_PRINT);
            $log[]   = 'Cannot get device ID. Error: ' . $json;
            $error[] = 'Cannot get device ID. Error: ' . $json;
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $options = ['expires_at' => strtotime('+20 minutes')];
        $log[]   = 'Sending message to: ' . $this->gsmnumber;

        $result = $this->sendMessageToNumber($this->gsmnumber, $this->message, $deviceID, $options);
        $json   = json_encode($result, JSON_PRETTY_PRINT);
        $log[]  = 'SMS Gateway response: ' . $json;

        if (!empty($result['response']['success'])) {
            $log[] = 'API call successful.';
            $successData = $result['response']['result']['success'][0] ?? [];

            if (empty($result['response']['result']['fails'])) {
                $msgid  = $successData['id'] ?? null;
                $status = $successData['status'] ?? 'unknown';
                $log[]  = 'Message ID: ' . ($msgid ?? 'N/A') . ' Status: ' . $status;
            } else {
                $errMsg  = json_encode($result['response']['result']['fails'], JSON_PRETTY_PRINT);
                $log[]   = 'Error sending message: ' . $errMsg;
                $error[] = 'Error sending message: ' . $errMsg;
            }
        } else {
            $log[]   = 'Failed to send. Response: ' . $json;
            $error[] = 'Failed to send. Response: ' . $json;
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        $result = $this->makeRequest('/api/v3/devices', 'GET');
        $device = $result['response']['result']['data'][0] ?? null;

        if ($device) {
            return 'ID: ' . ($device['id'] ?? '') . ' | '
                . 'Name: ' . ($device['name'] ?? '') . ' ' . ($device['make'] ?? '') . ' ' . ($device['model'] ?? '') . ' | '
                . 'Number: ' . ($device['number'] ?? '') . ' | '
                . 'Battery: ' . ($device['battery'] ?? '') . ' | '
                . 'Signal: ' . ($device['signal'] ?? '') . ' | '
                . 'WiFi: ' . ($device['wifi'] ?? '');
        }
        return null;
    }

    public function report(string $msgId): ?string
    {
        $result = $this->makeRequest('/api/v3/messages/view/' . $msgId, 'GET');
        if (!empty($result['response']['success'])) {
            return $result['response']['result']['status'] ?? 'unknown';
        }
        return null;
    }

    public function utilgsmnumber(string $number): string
    {
        $params      = $this->getParams();
        $countrycode = $params->countrycode ?? '';
        if ($countrycode && strlen($number) > 0 && $number[0] === '0') {
            return $countrycode . substr($number, 1);
        }
        return $countrycode . $number;
    }

    public function utilmessage(string $message): string
    {
        return $message;
    }

    private function sendMessageToNumber(string $to, string $message, $device, array $options = []): array
    {
        $query = array_merge(['number' => $to, 'message' => $message, 'device' => $device], $options);
        return $this->makeRequest('/api/v3/messages/send', 'POST', $query);
    }

    private function makeRequest(string $url, string $method, array $fields = []): array
    {
        $params = $this->getParams();

        $fields['email']    = $params->email ?? '';
        $fields['password'] = $params->pass ?? '';

        $fullUrl      = self::$baseUrl . $url;
        $fieldsString = http_build_query($fields);

        $ch = curl_init();
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        } else {
            $fullUrl .= '?' . $fieldsString;
        }

        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $errMsg = curl_error($ch);
            curl_close($ch);
            return [
                'response' => ['success' => false, 'error' => 'cURL error: ' . $errMsg],
                'status'   => 0,
            ];
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result ?: '', true);

        return [
            'response' => is_array($response) ? $response : $result,
            'status'   => $status,
        ];
    }
}

return [
    'value'  => 'smsgateway',
    'label'  => 'SMS Gateway',
    'fields' => ['email', 'pass', 'countrycode'],
];
