# Moneo SMS – WHMCS SMS Notification Module

An open-source WHMCS addon module that sends SMS messages to clients and administrators when WHMCS events happen (new order, invoice created, ticket reply, domain about to expire, …). Messages are built from editable templates and sent through one of 13 supported SMS gateways.

> **Status of v2.0.0:** this release has **not yet been verified on a live WHMCS installation**. The information below is derived from the source code and from the official WHMCS documentation. See [Compatibility & testing status](#compatibility--testing-status) and help us by running the [smoke test](docs/testing.md).

## Features

- **29 notification templates**: 20 for clients and 9 for administrators ([full list](docs/templates.md)).
  - Client templates go only to clients who ticked your "Subscribe to SMS" custom field and have an entry in your "GSM Number" field.
  - Admin templates go to the comma-separated numbers you enter on each admin template.
- **13 SMS gateways** ([capability matrix](docs/providers.md)).
- **Manual sending**: the *Send SMS* tab sends one message to **one** opted-in client at a time.
- **Sent message log** with delivery status. Status is looked up when you open the *Sent Messages* tab, for gateways that support delivery reports (8 of 13).
- **Credit balance** panel for gateways that support it (8 of 13).
- **Admin interface** in English, Turkish and Russian.

## Requirements

| Component | Requirement |
|---|---|
| WHMCS | A version that WHMCS still supports **and** that runs PHP 8.1 or newer. Today that is **8.13 LTS, 9.0 LTS or 9.1** ([WHMCS supported versions](https://docs.whmcs.com/9-1/installation-guide/system-requirements/#supported-versions)). |
| PHP | **8.1 or newer**, within the range your WHMCS version supports (see the table below). The module uses PHP 8.0 syntax (`string\|false` return types, `smsclass.php:308`), and WHMCS has never supported PHP 8.0 ([WHMCS PHP upgrades](https://docs.whmcs.com/8-13/system/php/php-upgrades/)). PHP 7.x will not work. |
| PHP extensions | cURL, JSON and mbstring. SimpleXML too if you use BirSMS or OneTouchSMS. WHMCS itself already requires cURL and JSON ([WHMCS system requirements](https://docs.whmcs.com/9-1/installation-guide/system-requirements/)). |
| WHMCS cron | The [system cron](https://docs.whmcs.com/9-0/system/automation/system-cron/) must run. The *DomainRenewalNotice* template is sent from WHMCS's daily cron run. |
| SMS gateway | An account with one of the [supported gateways](docs/providers.md). |

## Installation

1. Copy `modules/addons/moneo_sms/` into the `modules/addons/` folder of your WHMCS installation.
2. In the WHMCS Admin Area go to **Configuration (🔧) > System Settings > Addon Modules** ([WHMCS docs](https://docs.whmcs.com/8-13/addon-modules/addon-modules/)), find **Moneo SMS** and click **Activate**.

   Activation creates three database tables (`mod_moneosms_settings`, `mod_moneosms_templates` and `mod_moneosms_messages`) and fills in the 29 default templates (`moneo_sms.php:29-98`).
3. Click **Configure** on the same page and give access to the admin role groups that should manage the module.

> **Upgrading from v1 (`aktuel_sms`)?** Read [docs/upgrading-from-v1.md](docs/upgrading-from-v1.md) **before** uploading anything. v2 is a separate module with new tables, and the two versions must not be active at the same time.

### Create the custom client fields

The module reads the mobile number and the SMS opt-in from two client custom fields that you create in WHMCS.

Go to **Configuration (🔧) > System Settings > Custom Client Fields**. Depending on your WHMCS version this page is labelled *Custom Fields* ([WHMCS docs](https://docs.whmcs.com/9-0/clients/the-client-area/custom-fields/)). Add:

| Field | Type | Settings |
|---|---|---|
| e.g. `Subscribe to SMS` | Tick Box | **Show on Order Form: required** |
| e.g. `GSM Number` | Text Box | **Show on Order Form: required** |

**"Show on Order Form" is required, not optional.** The module's settings page only lists fields that have it enabled (`moneo_sms.php:228-245`).

Those dropdowns also don't filter by field category. If you have product custom fields of the same type with *Show on Order Form* on, they appear in the list too, so pick the client fields.

A client receives client SMS only when their tick box is checked and a GSM Number entry exists for them. The module does not check that the number is non-empty or valid; the gateway's own rules apply ([docs/providers.md](docs/providers.md#phone-number-handling)).

### Configure the module

Go to **Addons > Moneo SMS**.

> ⚠️ **All 29 templates are active straight after activation.** Every template is created with *Activate* ticked (`smsclass.php:453`).
>
> As soon as a gateway and the two custom fields are configured, **every active client template starts sending SMS** to opted-in clients. That includes templates that put passwords in plain text: `ClientAdd`, `AfterModuleCreate_Hosting` and `AfterModuleChangePassword`.
>
> Do step 1 below **before** step 2.

1. **Client Templates / Admin Templates tabs.** Untick **Activate** on every template you don't want, review the texts, and click **Save** on each tab.
   - Admin templates send nothing until you enter at least one number in **Admin GSM Numbers**. Separate numbers with commas; spaces are removed when you save (`moneo_sms.php:335-337`).
   - Only *DomainRenewalNotice* has an **Extra** field: the number of days before expiry (default 15). Don't save it empty or `0`. The field is then hidden from that point on (`moneo_sms.php:373`), and the template silently falls back to 15 days (`hooks/domainrenewalnotice.php:24`).
2. **Settings tab**
   1. Choose your **SMS Gateway**. When you change the selection, the page reloads and shows that gateway's credential fields (`moneo_sms.php:275`).

      The list has no empty entry, so on a fresh install the first gateway is already shown as selected. If you want that one, click **Save** once to make its credential fields appear.
   2. Enter the credentials and click **Save**.
   3. **Sender ID** and **Signature** apply to every gateway. The signature is appended to **every** message, separated by a space (`smsclass.php:131-134`).
   4. Map the **Subscribe to SMS** and **GSM Number** fields you created above.
   5. **Date format** supports only the tokens `%d`, `%m` and `%y`, where `%y` is the **4-digit** year: `%d.%m.%y` → `27.01.2024` (`smsclass.php:483-501`). It is applied only to the `{duedate}` variable of the invoice templates.

The phone number format each gateway expects is different. See [docs/providers.md](docs/providers.md#phone-number-handling).

> ⚠️ **Deactivating the module deletes all of its data.**
>
> **Deactivate** drops `mod_moneosms_templates`, `mod_moneosms_settings` and `mod_moneosms_messages` (`moneo_sms.php:100-117`). Your gateway credentials, every edited template and the whole message history are permanently lost.
>
> To stop sending temporarily, untick **Activate** on the templates instead. Back up your database before you deactivate.

## Notification templates

A summary of all 29 templates. Variables, default texts and exact trigger conditions are in **[docs/templates.md](docs/templates.md)**.

| Template | WHMCS hook | Audience | Notes |
|---|---|---|---|
| `ClientAdd` | `ClientAdd` | Client | Welcome message, can include the password |
| `ClientChangePassword` | `ClientChangePassword` | Client | |
| `AcceptOrder_SMS` | `AcceptOrder` | Client | |
| `AfterRegistrarRegistration` | `AfterRegistrarRegistration` | Client | |
| `AfterRegistrarRegistrationFailed` | `AfterRegistrarRegistrationFailed` | Client | |
| `AfterRegistrarRenewal` | `AfterRegistrarRenewal` | Client | |
| `AfterModuleCreate_Hosting` | `AfterModuleCreate` | Client | Hosting accounts only; can include username/password |
| `AfterModuleSuspend` | `AfterModuleSuspend` | Client | Hosting accounts only |
| `AfterModuleUnsuspend` | `AfterModuleUnsuspend` | Client | Hosting accounts only |
| `AfterModuleChangePassword` | `AfterModuleChangePassword` | Client | Hosting accounts only; can include the password |
| `AfterModuleChangePackage` | `AfterModuleChangePackage` | Client | Hosting accounts only |
| `InvoiceCreated` | `InvoiceCreated` | Client | |
| `InvoicePaid` | `InvoicePaid` | Client | |
| `InvoicePaymentReminder_Reminder` | `InvoicePaymentReminder` | Client | Reminder type `reminder` |
| `InvoicePaymentReminder_Firstoverdue` | `InvoicePaymentReminder` | Client | Reminder type `firstoverdue` |
| `InvoicePaymentReminder_secondoverdue` | `InvoicePaymentReminder` | Client | Reminder type `secondoverdue` |
| `InvoicePaymentReminder_thirdoverdue` | `InvoicePaymentReminder` | Client | Reminder type `thirdoverdue` |
| `DomainRenewalNotice` | `DailyCronJob` | Client | Needs the WHMCS cron; sent `{x}` days before expiry (default 15) |
| `TicketAdminReply` | `TicketAdminReply` | Client | |
| `TicketClose` | `TicketClose` | Client | |
| `AdminLogin_admin` | `AdminLogin` | Admin | |
| `ClientLogin_admin` | `ClientLogin` | Admin | Only for opted-in clients; see [Known limitations](#known-limitations) |
| `ClientAdd_admin` | `ClientAdd` | Admin | |
| `TicketOpen_admin` | `TicketOpen` | Admin | |
| `TicketUserReply_admin` | `TicketUserReply` | Admin | |
| `AfterRegistrarRegistration_admin` | `AfterRegistrarRegistration` | Admin | |
| `AfterRegistrarRenewal_admin` | `AfterRegistrarRenewal` | Admin | |
| `AfterRegistrarRegistrationFailed_admin` | `AfterRegistrarRegistrationFailed` | Admin | |
| `AfterRegistrarRenewalFailed_admin` | `AfterRegistrarRenewalFailed` | Admin | |

## Supported SMS gateways

| Gateway | Balance shown | Delivery reports |
|---|---|---|
| BirSMS | ✓ | ✓ |
| ByteHand | ✓ | ✓ |
| ClickAtell | ✓ | ✓ |
| Dakik SMS | ✓ | ✓ |
| HemenPosta | – | – |
| msg91.com | ✓ | ✓ |
| MutluCell | ✓ | ✓ |
| NetGsm | ✓ | ✓ |
| OneTouchSMS | – | – |
| Route SMS | – | – |
| SendSms.ro | – | – |
| SMS Gateway (smsgateway.me) | ✓ (device info, not credit) | ✓ |
| Ucuz Sms Al | – | – |

Settings fields, phone number handling, transport security and website status for each gateway are in **[docs/providers.md](docs/providers.md)**. Whether each gateway's API still accepts the requests this module sends has **not** been verified.

## Upgrading from v1.x (`aktuel_sms`)

v2.0.0 is a new module (`moneo_sms`) with new database tables (`mod_moneosms_*`). **Nothing is migrated automatically from v1**: settings, templates and message history stay in the old `mod_aktuelsms_*` tables.

Back up first, remove the old module, then set v2 up from scratch. Never run both at the same time. Step-by-step guide: **[docs/upgrading-from-v1.md](docs/upgrading-from-v1.md)**.

## Adding a new SMS gateway

A gateway is a single PHP file in `modules/addons/moneo_sms/senders/`. The class name, the file name and the stored setting value must all be identical. See **[docs/adding-a-provider.md](docs/adding-a-provider.md)** for the full contract and a working example.

## Compatibility & testing status

| WHMCS | PHP versions supported by WHMCS | PHP versions usable with this module | Verified on a live install |
|---|---|---|---|
| 8.13 LTS | 7.2, 7.3, 7.4, 8.1, 8.2, 8.3 ([source](https://docs.whmcs.com/8-13/installation-guide/system-requirements/)) | 8.1, 8.2, 8.3 | — not yet verified |
| 9.0 LTS | 8.2, 8.3 ([source](https://docs.whmcs.com/9-0/installation-guide/system-requirements/)) | 8.2, 8.3 | — not yet verified |
| 9.1 | 8.2, 8.3, 8.4 ([source](https://docs.whmcs.com/9-1/installation-guide/system-requirements/)) | 8.2, 8.3, 8.4 | — not yet verified |

WHMCS releases older than 8.13 are end-of-life ([WHMCS releases](https://docs.whmcs.com/releases/)) and are not covered here.

"Usable" means the PHP version is allowed by both WHMCS and the module's syntax. It does **not** mean tested. If you run the [smoke test](docs/testing.md) on one of these combinations, please open a pull request that fills in the last column.

## Known limitations

These describe the behaviour **as of 2.0.0**. Fixes are planned in a follow-up.

- **Send SMS sends to one client only.** The client list allows selecting several entries, but the form field is named `client` (not `client[]`), so only one value is submitted (`moneo_sms.php:499-508`, `:562`).
- **Deleting a message is a plain GET link without a security token** (`moneo_sms.php:401-411`, `:477`). The forms include a WHMCS token, but the module itself only checks that the field exists (`moneo_sms.php:209`, `:325`, `:499`).
- **Gateway passwords end up in the message log.** Every send logs the full gateway settings (`smsclass.php:136`). NetGsm, OneTouchSMS, Route SMS and SendSms.ro also log the full request URL, which contains the password (`senders/*.php:25`). These logs are stored in `mod_moneosms_messages.logs` and shown in the *Send SMS* debug panel.
- **Some gateways are called over plain HTTP**: BirSMS, ByteHand, Dakik SMS, HemenPosta, OneTouchSMS, Route SMS and Ucuz Sms Al. See [docs/providers.md](docs/providers.md).
- **No data import from v1** (`aktuel_sms`). See [docs/upgrading-from-v1.md](docs/upgrading-from-v1.md).
- **`ClientLogin_admin` may never fire.** WHMCS's current hook index has no `ClientLogin` hook; login hooks are `UserLogin`, `UserLogout` and `ClientLoginShare` ([WHMCS hook index](https://developers.whmcs.com/hooks/hook-index/)).
- **`ClientAdd` reads the `userid` parameter**, which WHMCS marks as deprecated in favour of `client_id` / `user_id` (`hooks/clientadd.php:27`; [WHMCS client hooks](https://developers.whmcs.com/hooks-reference/client/#clientadd)).
- **DomainRenewalNotice only sends on the exact day** that is `{x}` days before expiry. If the daily cron does not run that day, the SMS for that domain is skipped (`hooks/domainrenewalnotice.php:31-36`).

## Contributing

Contributions are welcome! You are free to add new hooks, gateways, features, or bug fixes. Fork the repository, make your changes, and submit a pull request.

## Contributors

- [Güven Atbakan](https://github.com/shibby)
- [Emir Karşıyakalı](https://github.com/emir)
- [Turgay Çoşkun](https://github.com/adalim61)
- [Hakan Ersu](https://github.com/hakanersu)

## License

This project is licensed under the [MIT License](LICENSE.md).
