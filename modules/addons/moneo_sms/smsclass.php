<?php
/**
 * MoneoSms - Core SMS Class
 *
 * @package    MoneoSms
 * @author     Moneo
 * @license    GPLv3 (https://www.gnu.org/licenses/gpl-3.0.txt)
 */

use WHMCS\Database\Capsule;

class MoneoSms
{
    protected string $sender = '';
    public ?object $params = null;
    public string $gsmnumber = '';
    public string $message = '';
    public int $userid = 0;
    protected array $errors = [];
    protected array $logs = [];

    private static array $loadedSenders = [];
    private static ?array $cachedSenders = null;
    private static ?array $cachedHooks = null;

    public function setGsmnumber(string $gsmnumber): void
    {
        $this->gsmnumber = $this->util_gsmnumber($gsmnumber);
    }

    public function getGsmnumber(): string
    {
        return $this->gsmnumber;
    }

    public function setMessage(string $message): void
    {
        $this->message = $this->util_convert($message);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setUserid(int $userid): void
    {
        $this->userid = $userid;
    }

    public function getUserid(): int
    {
        return $this->userid;
    }

    public function getParams(): ?object
    {
        $settings = $this->getSettings();
        if (!$settings) {
            return null;
        }
        return json_decode($settings->apiparams ?? '{}') ?: null;
    }

    public function getSender(): ?string
    {
        $settings = $this->getSettings();
        if (!$settings || empty($settings->api)) {
            $this->addError("No valid SMS gateway selected");
            $this->addLog("No valid SMS gateway selected");
            return null;
        }
        return $settings->api;
    }

    public function getSettings(): ?object
    {
        try {
            return Capsule::table('mod_moneosms_settings')->first();
        } catch (\Exception $e) {
            $this->addError("Could not load settings: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate that a sender name is safe (alphanumeric + underscore only).
     */
    private function isValidSenderName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9_]+$/', $name);
    }

    /**
     * Load a sender class file safely.
     */
    private function loadSender(string $sender_function): bool
    {
        if (!$this->isValidSenderName($sender_function)) {
            $this->addError("Invalid sender name: " . $sender_function);
            return false;
        }

        $senderFile = __DIR__ . '/senders/' . $sender_function . '.php';
        if (!file_exists($senderFile)) {
            $this->addError("Sender file not found: " . $sender_function);
            return false;
        }

        if (!isset(self::$loadedSenders[$sender_function])) {
            include_once $senderFile;
            self::$loadedSenders[$sender_function] = true;
        }

        if (!class_exists($sender_function)) {
            $this->addError("Sender class not found: " . $sender_function);
            return false;
        }

        return true;
    }

    public function send(): bool
    {
        $sender_function = strtolower($this->getSender() ?? '');
        if (empty($sender_function)) {
            return false;
        }

        $params  = $this->getParams();
        $message = $this->message;
        if ($params && !empty($params->signature)) {
            $message .= " " . $params->signature;
        }

        $this->addLog("Params: " . json_encode($params));
        $this->addLog("To: " . $this->getGsmnumber());
        $this->addLog("Message: " . $message);
        $this->addLog("SenderClass: " . $sender_function);

        if (!$this->loadSender($sender_function)) {
            return false;
        }

        $sender = new $sender_function(trim($message), $this->getGsmnumber());

        if (!($sender instanceof SmsSenderInterface)) {
            $this->addError("Sender class does not implement SmsSenderInterface: " . $sender_function);
            return false;
        }

        $result = $sender->send();

        if (!is_array($result)) {
            $this->addError("Sender returned invalid response");
            $this->saveToDb('', 'error', $this->getErrorsRaw(), $this->getLogsRaw());
            return false;
        }

        if (!empty($result['log']) && is_array($result['log'])) {
            foreach ($result['log'] as $log) {
                $this->addLog($log);
            }
        }

        if (!empty($result['error']) && is_array($result['error'])) {
            foreach ($result['error'] as $error) {
                $this->addError($error);
            }
            $this->saveToDb($result['msgid'] ?? '', 'error', $this->getErrors(), $this->getLogs());
            return false;
        }

        $this->saveToDb($result['msgid'] ?? '', '', null, $this->getLogs());
        return true;
    }

    public function getBalance(): ?string
    {
        $sender_function = strtolower($this->getSender() ?? '');
        if (empty($sender_function) || !$this->loadSender($sender_function)) {
            return null;
        }

        $sender = new $sender_function('', '');
        return $sender->balance();
    }

    public function getReport(string $msgid): ?string
    {
        try {
            $row = Capsule::table('mod_moneosms_messages')
                ->where('msgid', $msgid)
                ->select('sender')
                ->first();
        } catch (\Exception $e) {
            return null;
        }

        if (!$row || empty($row->sender)) {
            return null;
        }

        $sender_function = strtolower($row->sender);
        if (!$this->loadSender($sender_function)) {
            return null;
        }

        $sender = new $sender_function('', '');
        return $sender->report($msgid);
    }

    public function getSenders(): array
    {
        if (self::$cachedSenders !== null) {
            return self::$cachedSenders;
        }

        $senders = [];
        $dir = __DIR__ . '/senders';
        if (!is_dir($dir) || !($handle = opendir($dir))) {
            return $senders;
        }

        while (false !== ($entry = readdir($handle))) {
            if (
                $entry === '.' || $entry === '..' ||
                $entry === 'SmsSenderInterface.php' ||
                pathinfo($entry, PATHINFO_EXTENSION) !== 'php'
            ) {
                continue;
            }
            $filePath = $dir . '/' . $entry;
            $result = require_once $filePath;
            if (is_array($result) && isset($result['value'])) {
                $senders[] = $result;
            }
        }
        closedir($handle);

        self::$cachedSenders = $senders;
        return $senders;
    }

    public function getHooks(): array
    {
        if (self::$cachedHooks !== null) {
            return self::$cachedHooks;
        }

        $hooks = [];
        $dir = __DIR__ . '/hooks';
        if (!is_dir($dir) || !($handle = opendir($dir))) {
            return $hooks;
        }

        while (false !== ($entry = readdir($handle))) {
            if ($entry === '.' || $entry === '..' || pathinfo($entry, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }
            $filePath = $dir . '/' . $entry;
            $result = require_once $filePath;
            if (is_array($result) && isset($result['function'])) {
                $hooks[] = $result;
            }
        }
        closedir($handle);

        self::$cachedHooks = $hooks;
        return $hooks;
    }

    public function saveToDb(string $msgid, string $status, ?string $errors = null, ?string $logs = null): void
    {
        try {
            Capsule::table('mod_moneosms_messages')->insert([
                'sender'   => $this->getSender() ?? '',
                'to'       => $this->getGsmnumber(),
                'text'     => $this->getMessage(),
                'msgid'    => $msgid,
                'status'   => $status,
                'errors'   => $errors,
                'logs'     => $logs,
                'user'     => $this->getUserid(),
                'datetime' => date('Y-m-d H:i:s'),
            ]);
            $this->addLog("Message saved to database");
        } catch (\Exception $e) {
            $this->addLog("Failed to save message to database: " . $e->getMessage());
        }
    }

    /**
     * Convert special characters for SMS compatibility.
     * Can be overridden by individual sender classes.
     */
    public function util_convert(string $message): string
    {
        $changefrom = ['ı', 'İ', 'ü', 'Ü', 'ö', 'Ö', 'ğ', 'Ğ', 'ç', 'Ç', 'ş', 'Ş'];
        $changeto   = ['i', 'I', 'u', 'U', 'o', 'O', 'g', 'G', 'c', 'C', 's', 'S'];
        return str_replace($changefrom, $changeto, $message);
    }

    /**
     * Perform a simple HTTP GET request using cURL.
     * Returns the response body or false on failure.
     */
    public function curlGet(string $url, int $timeout = 15): string|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->addLog('cURL error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $result;
    }

    /**
     * Perform an HTTP POST request using cURL.
     * Returns the response body or false on failure.
     */
    public function curlPost(string $url, string $data, array $headers = [], int $timeout = 30): string|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->addLog('cURL error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $result;
    }

    public function util_gsmnumber(string $number): string
    {
        $replacefrom = ['-', '(', ')', '.', ',', '+', ' '];
        return str_replace($replacefrom, '', $number);
    }

    /**
     * Replace template variables with actual values.
     * Returns the original template if variables are empty or counts mismatch.
     */
    public function replaceTemplateVars(string $templateText, string $variables, array $values): string
    {
        $variables = str_replace(' ', '', $variables);
        if (empty($variables)) {
            return $templateText;
        }

        $from = explode(',', $variables);
        if (count($from) !== count($values)) {
            return $templateText;
        }

        return str_replace($from, $values, $templateText);
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function addLog(string $log): void
    {
        $this->logs[] = $log;
    }

    public function getErrors(): string
    {
        if (empty($this->errors)) {
            return '';
        }
        $res = '<ul class="list-unstyled">';
        foreach ($this->errors as $d) {
            $res .= '<li class="text-danger">' . htmlspecialchars($d) . '</li>';
        }
        $res .= '</ul>';
        return $res;
    }

    public function getErrorsRaw(): string
    {
        return implode('; ', $this->errors);
    }

    public function getLogs(): string
    {
        if (empty($this->logs)) {
            return '';
        }
        $res = '<ul class="list-unstyled">';
        foreach ($this->logs as $d) {
            $res .= '<li>' . htmlspecialchars($d) . '</li>';
        }
        $res .= '</ul>';
        return $res;
    }

    public function getLogsRaw(): string
    {
        return implode('; ', $this->logs);
    }

    /**
     * Ensures all hook templates exist in the database.
     * Runs on install/upgrade.
     */
    public function checkHooks(?array $hooks = null): int
    {
        if ($hooks === null) {
            $hooks = $this->getHooks();
        }

        $count = 0;
        foreach ($hooks as $hook) {
            if (empty($hook['function']) || empty($hook['type'])) {
                continue;
            }

            try {
                $exists = Capsule::table('mod_moneosms_templates')
                    ->where('name', $hook['function'])
                    ->where('type', $hook['type'])
                    ->first();

                if (!$exists) {
                    Capsule::table('mod_moneosms_templates')->insert([
                        'name'        => $hook['function'],
                        'type'        => $hook['type'],
                        'template'    => $hook['defaultmessage'] ?? '',
                        'variables'   => $hook['variables'] ?? '',
                        'extra'       => $hook['extra'] ?? '',
                        'description' => json_encode($hook['description'] ?? []),
                        'active'      => 1,
                    ]);
                    $count++;
                } else {
                    Capsule::table('mod_moneosms_templates')
                        ->where('name', $hook['function'])
                        ->where('type', $hook['type'])
                        ->update(['variables' => $hook['variables'] ?? '']);
                }
            } catch (\Exception $e) {
                if (function_exists('logActivity')) {
                    logActivity('Moneo SMS: Error checking hook ' . ($hook['function'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }

        return $count;
    }

    public function getTemplateDetails(string $template): ?object
    {
        try {
            return Capsule::table('mod_moneosms_templates')
                ->where('name', $template)
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function changeDateFormat(?string $date = null): ?string
    {
        if (empty($date)) {
            return $date;
        }

        $settings = $this->getSettings();
        $dateformat = $settings->dateformat ?? '';
        if (empty($dateformat)) {
            return $date;
        }

        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return $date;
        }

        return str_replace(['%d', '%m', '%y'], [$parts[2], $parts[1], $parts[0]], $dateformat);
    }
}
