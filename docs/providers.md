# SMS gateways

Moneo SMS 2.0.0 includes **13 gateways**, one PHP file each in [`modules/addons/moneo_sms/senders/`](../modules/addons/moneo_sms/senders/). Everything below is read from those files; line references point to `modules/addons/moneo_sms/senders/`.

> **Not verified:** whether each gateway's API endpoint still accepts the requests this module sends. Several gateways use older API styles. Test with your own account before relying on a gateway; see [testing.md](testing.md).

## Capability matrix

| Gateway (label / settings value) | Settings fields | Balance panel | Delivery report | Transport | Source |
|---|---|---|---|---|---|
| BirSMS / `birsms` | `user`, `pass` | ✓ | ✓ | **HTTP** (`api.1sms.com.tr`) | `birsms.php:33,56-89` |
| ByteHand / `bytehand` | `user`, `pass` | ✓ | ✓ (`DELIVERED`/`ACCEPTED` → success) | **HTTP** (`bytehand.com:3800`) | `bytehand.php:24,45-69` |
| ClickAtell / `clickatell` | `user`, `pass`, `apiid` | ✓ | ✓ | HTTPS (`api.clickatell.com`, legacy `/http/` API) | `clickatell.php:26,67-91` |
| Dakik SMS / `dakiksms` | `user`, `pass` | ✓ | ✓ (any non-empty reply → success) | **HTTP** (`www.dakiksms.com`) | `dakiksms.php:29,50-70` |
| HemenPosta / `hemenposta` | `user`, `pass` | – | – | **HTTP** (`sms.modexi.com`) | `hemenposta.php:28,56-64` |
| msg91.com (India) / `msg91` | `authkey`, `route`, `flash`, `unicode`, `ignoreNdnc` | ✓ (needs `route`) | ✓ | HTTPS (`control.msg91.com`, legacy `/api/` endpoints) | `msg91.php:25,73-97` |
| MutluCell / `mutlucell` | `user`, `pass` | ✓ | ✓ | HTTPS (`smsgw.mutlucell.com`) | `mutlucell.php:29,62-89` |
| NetGsm / `netgsm` | `user`, `pass` | ✓ (package info + TL credit) | ✓ | HTTPS (`api.netgsm.com.tr`; credentials in the query string) | `netgsm.php:24,65-91` |
| OneTouchSMS / `onetouch` | `user`, `pass` | – | – | **HTTP** (`api.ottbd.org`) | `onetouch.php:24,71-79` |
| Route SMS / `routesms` | `user`, `pass` | – | – | **HTTP** (fixed IP `121.241.242.114:8080`) | `routesms.php:24,66-74` |
| SendSms.ro / `sendsmsro` | `user`, `pass` | – | – | HTTPS (`api.sendsms.ro`) | `sendsmsro.php:24,48-56` |
| SMS Gateway / `smsgateway` | `email`, `pass`, `countrycode` | ✓ (shows device info, not credit) | ✓ (returns the provider's status text) | HTTPS (`smsgateway.me`, API v3) | `smsgateway.php:5,65-88` |
| Ucuz Sms Al / `ucuzsmsal` | `user`, `pass` | – | – | **HTTP** (`www.ucuzsmsal.com`) | `ucuzsmsal.php:24,47-55` |

Settings fields are each sender file's `return ['fields' => …]` array at the end of the file. **Sender ID** and **Signature** are separate global settings that every gateway receives (`moneo_sms.php:291-298`).

A gateway whose `balance()` returns nothing shows no credit panel. A gateway whose `report()` returns nothing leaves the message status empty, and the module asks again every time the *Sent Messages* tab is opened (`moneo_sms.php:447-458`, `:604-612`).

## Phone number handling

Every number first has `- ( ) . , +` and spaces removed (`smsclass.php:352-356`). Each gateway then applies its own rule:

| Gateway | Rule (`utilgsmnumber`) | Enter numbers as |
|---|---|---|
| BirSMS, NetGsm | 10 digits → `90` is prefixed; 11 digits → `9` is prefixed. The result **must start with `905`**, otherwise the send fails with a number-format error. | Turkish mobile numbers only: `5XXXXXXXXX`, `05XXXXXXXXX` or `905XXXXXXXXX` |
| msg91 | 10 digits → `91` is prefixed. The result must start with `91`, otherwise the send fails. | Indian numbers: `XXXXXXXXXX` or `91XXXXXXXXXX` |
| Ucuz Sms Al | 11 digits → the first digit is dropped; 12 digits → the first two are dropped. A 10-digit number that does not start with `5` fails. | Turkish mobile numbers; the country code is removed |
| SMS Gateway | A leading `0` is replaced by the **Country Code Prefix** setting. Otherwise the prefix is put in front of the number, even if it already has a country code. | National format with a leading `0`, e.g. `05XXXXXXXXX`, with *Country Code Prefix* set to e.g. `90` |
| All others (ByteHand, ClickAtell, Dakik SMS, HemenPosta, MutluCell, OneTouchSMS, Route SMS, SendSms.ro) | Sent exactly as entered, after the cleanup above. | Whatever format the gateway's own API expects. Check the gateway's documentation. |

Sources: `birsms.php:91-102`, `netgsm.php:93-106`, `msg91.php:99-108`, `ucuzsmsal.php:57-70`, `smsgateway.php:90-98`, and the `utilgsmnumber()` method in each other sender file.

The same rules apply to the **Admin GSM Numbers** on admin templates.

## Message text handling

- All gateways receive the template text after Turkish characters are converted to ASCII and the signature is appended (see [templates.md](templates.md#how-a-message-is-built-and-sent)).
- NetGsm also converts the signature (`netgsm.php:108-113`). All other gateways pass the text through unchanged (`utilmessage()` returns its input).
- msg91 cuts the Sender ID to its first 6 characters. Its `ignoreNdnc` setting is shown but **not used** by the code (`msg91.php:27`, `:49`).

## Security notes (as of 2.0.0)

- **Credentials are written to the message log.**
  - Every send logs the full gateway settings, including passwords and keys (`smsclass.php:136`).
  - NetGsm, OneTouchSMS, Route SMS and SendSms.ro also log the full request URL, which contains the password (`*.php:25`).
  - The log is stored in `mod_moneosms_messages.logs` and shown in the *Send SMS* debug panel.
  - Treat database backups and admin access accordingly.
- **Plain-HTTP transports.** BirSMS, ByteHand, Dakik SMS, HemenPosta, OneTouchSMS, Route SMS and Ucuz Sms Al send credentials and message text over unencrypted HTTP.

## Gateway websites

These links are for orientation only. They were checked on **2026-11-10** with a plain HTTPS request from a single location; a failed check can be a temporary or regional problem.

| Gateway | Website | Check result |
|---|---|---|
| BirSMS | [1sms.com.tr](https://www.1sms.com.tr) | Redirects to `interaktifsms.com.tr`; the relationship to the BirSMS API is unknown |
| ByteHand | [bytehand.com](https://www.bytehand.com) | Reachable |
| ClickAtell | [clickatell.com](https://www.clickatell.com) | Reachable |
| Dakik SMS | [dakiksms.com](https://www.dakiksms.com) | Timed out; could not verify |
| HemenPosta | – | No website known; the API host is `sms.modexi.com` |
| msg91.com | [msg91.com](https://msg91.com) | Reachable |
| MutluCell | [mutlucell.com](https://www.mutlucell.com) | Redirects to `mutlucell.com.tr`, which could not be loaded; could not verify |
| NetGsm | [netgsm.com.tr](https://www.netgsm.com.tr) | Reachable |
| OneTouchSMS | – | No website known; the API host is `api.ottbd.org` |
| Route SMS | – | No website known; the API is a fixed IP address |
| SendSms.ro | [sendsms.ro](https://www.sendsms.ro) | Reachable |
| SMS Gateway | [smsgateway.me](https://smsgateway.me) | Responds, but the page returned no readable content |
| Ucuz Sms Al | `ucuzsmsal.com` | **Domain does not resolve**; the gateway is probably discontinued |

The *region* of each gateway is not recorded in the code and is not listed here.
