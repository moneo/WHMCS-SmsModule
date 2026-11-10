# Adding a new SMS gateway

A gateway is **one PHP file** in `modules/addons/moneo_sms/senders/`. The module finds it automatically: the settings page lists every `*.php` file in that folder except `SmsSenderInterface.php` (`smsclass.php:213-243`).

This page describes the contract that the loader in `smsclass.php` enforces in 2.0.0. Line references point to `modules/addons/moneo_sms/`.

## The rules

1. **One name, used three times.** The file name, the class name and the `value` in the config array must be **the same lowercase string**, made only of `a-z`, `0-9` and `_`.

   When sending, the module reads the stored gateway value and lowercases it (`smsclass.php:125`). It then checks it against `^[a-z0-9_]+$` (`:89-92`), includes `senders/<value>.php` (`:104-113`), and requires a class with exactly that name (`:115`).

   Example: the file `senders/examplesms.php` must contain `class examplesms` and return `'value' => 'examplesms'`.

   The class name is a **global, un-namespaced PHP class**. Pick a name that no other WHMCS module or library is likely to declare. A clash causes a fatal "Cannot declare class" error. v1 of this module (`aktuel_sms`) is a known example: it declares the same gateway class names, such as `netgsm` (see [upgrading-from-v1.md](upgrading-from-v1.md)).
2. **Class declaration:** `class <name> extends MoneoSms implements SmsSenderInterface`.
   - Implementing the interface is checked before every send (`smsclass.php:147`).
   - Extending `MoneoSms` gives you `getParams()`, `curlGet()` and `curlPost()`.
3. **Constructor:** `__construct(string $message, string $gsmnumber)` (`senders/SmsSenderInterface.php:17`).
   - It is also called as `new <name>('', '')` to fetch the balance and delivery reports (`smsclass.php:185`, `:209`). It must therefore work with empty strings and **must not make network calls**.
   - Apply your own formatting here, as the existing gateways do: `$this->message = $this->utilmessage($message); $this->gsmnumber = $this->utilgsmnumber($gsmnumber);`.
4. **`send(): array`** must return exactly `['log' => string[], 'error' => string[], 'msgid' => ?string]` (`smsclass.php:152-175`).
   - **Non-empty `error`** means the send failed. The message is stored with status `error`, and the errors are shown to the admin.
   - **Empty `error`** means success. The message is stored with an **empty** status and `msgid`, so that `report()` can fill the status in later.
   - The `log` lines are stored with the message and shown in the *Send SMS* debug panel.
   - Anything you pass to `$this->addLog()` inside the gateway class, including cURL errors from `curlGet()`/`curlPost()`, is **not** shown, because the gateway object has its own log. Put what matters into the returned `log` array.
5. **`balance(): ?string`**: the text for the credit panel. Return `null` if the gateway has no balance API; the panel is then hidden (`moneo_sms.php:604-612`). This is called on **every** tab of the module page.
6. **`report(string $msgId): ?string`**: the delivery status.
   - Use `success`, `sent`, `pending` or `error`. These have translations and colours in the message list (`moneo_sms.php:460-468`, `lang/*.php`). Any other string is shown as-is.
   - If you return `null`, the status stays empty, and the module calls `report()` again every time that message appears on the *Sent Messages* tab (`moneo_sms.php:447-458`).
7. **`utilgsmnumber(string $number): string`**: gateway-specific number formatting.
   - The number arrives with `- ( ) . , +` and spaces already removed (`smsclass.php:352-356`).
   - The convention for an invalid number is to return the string `'numbererror'` and make `send()` check for it. The core does not check this for you.
8. **`utilmessage(string $message): string`**: gateway-specific text conversion. Turkish → ASCII conversion has already been applied to the template text (`smsclass.php:297-302`).
9. **The config array.** The file must **end with** `return ['value' => …, 'label' => …, 'fields' => [...]];`.
   - The settings page loads every sender file with `require_once` and uses this return value (`smsclass.php:234-237`).
   - The file must not output anything or do any work besides declaring the class and returning this array.
10. **Settings fields.**
    - Each entry in `fields` becomes a text input named `params[<field>]` on the Settings tab (`moneo_sms.php:262-268`). Read the value in your class with `$this->getParams()-><field>`.
    - **Sender ID** (`senderid`) and **Signature** (`signature`) are always present and stored in the same place (`moneo_sms.php:291-298`). Read `$this->getParams()->senderid`, but do **not** list `senderid` or `signature` in `fields`.
11. **Field labels.** A field's label is `$_ADDONLANG['<field>']` if it exists, otherwise the raw field name (`moneo_sms.php:265`). Add a line for each new field to all three language files: `lang/english.php`, `lang/turkish.php` and `lang/russian.php`.

## Minimal example

`modules/addons/moneo_sms/senders/examplesms.php`. This is illustrative only: it posts to the non-routable host `api.example.invalid`. Adapt the request and response handling to your gateway's API, and do **not** commit the example itself, because every file in `senders/` shows up in the gateway list.

```php
<?php

class examplesms extends MoneoSms implements SmsSenderInterface
{
    public function __construct(string $message, string $gsmnumber)
    {
        // Also called as new examplesms('', '') for balance() and report(): no network calls here.
        $this->message   = $this->utilmessage($message);
        $this->gsmnumber = $this->utilgsmnumber($gsmnumber);
    }

    public function send(): array
    {
        $log   = [];
        $error = [];
        $msgid = null;

        if ($this->gsmnumber === 'numbererror') {
            $error[] = 'Invalid phone number';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $params = $this->getParams();
        $body   = http_build_query([
            'api_key' => $params->apikey ?? '',
            'from'    => $params->senderid ?? '',
            'to'      => $this->gsmnumber,
            'text'    => $this->message,
        ]);

        // Never put $body, or a URL containing credentials, into $log: it is stored in the database.
        $log[] = 'Sending to ' . $this->gsmnumber;

        $response = $this->curlPost(
            'https://api.example.invalid/v1/sms',
            $body,
            ['Content-Type: application/x-www-form-urlencoded']
        );
        if ($response === false) {
            $error[] = 'Could not connect to Example SMS';
            return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
        }

        $data = json_decode($response, true);
        if (is_array($data) && !empty($data['id'])) {
            $msgid = (string) $data['id'];
            $log[] = 'Accepted, message id ' . $msgid;
        } else {
            $error[] = 'Example SMS rejected the message';
        }

        return ['log' => $log, 'error' => $error, 'msgid' => $msgid];
    }

    public function balance(): ?string
    {
        // No balance API: the credit panel stays hidden.
        return null;
    }

    public function report(string $msgId): ?string
    {
        // Return 'success', 'sent', 'pending' or 'error' if the API offers delivery reports.
        return null;
    }

    public function utilgsmnumber(string $number): string
    {
        return ctype_digit($number) ? $number : 'numbererror';
    }

    public function utilmessage(string $message): string
    {
        return $message;
    }
}

return [
    'value'  => 'examplesms',
    'label'  => 'Example SMS',
    'fields' => ['apikey'],
];
```

Then add the label for the new `apikey` field to each language file, for example in `lang/english.php`:

```php
$_ADDONLANG['apikey'] = "API Key";
```

## Checklist before opening a pull request

- [ ] The file name, class name and `value` are identical and match `^[a-z0-9_]+$`.
- [ ] `php -l senders/<name>.php` passes on PHP 8.1 or newer.
- [ ] The constructor works with `('', '')` and makes no network calls.
- [ ] No credentials appear in the returned `log` lines.
- [ ] HTTPS is used, if the gateway offers it.
- [ ] The language keys for every new field have been added to all three `lang/*.php` files.
- [ ] The gateway has been added to the matrix in [providers.md](providers.md) and to the README gateway table.
- [ ] Tested with a real account. Record the result in the PR, as described in [testing.md](testing.md).
