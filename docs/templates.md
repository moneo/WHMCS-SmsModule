# Notification templates

Moneo SMS 2.0.0 ships **29 templates**: 20 send to clients and 9 send to administrators. Each one is defined by one file in [`modules/addons/moneo_sms/hooks/`](../modules/addons/moneo_sms/hooks/). Its `$hook` array, at the top of the file, sets:

- the template name (`function`);
- the WHMCS hook point (`hook`);
- the audience (`type`);
- the variables (`variables`);
- the default text (`defaultmessage`);
- an optional `extra` value.

Everything on this page is taken from those files. File references point to `modules/addons/moneo_sms/`.

## How a message is built and sent

1. **The template must be active.** Each hook function loads its template by name. It stops if the template is missing or has *Activate* unticked (e.g. `hooks/clientadd.php:17-19`).
2. **The recipient is chosen.**
   - **Client templates** look up the client and send only if **both** of these are true:
     - the client's *Subscribe to SMS* tick-box value is `on`;
     - the *GSM Number* field has a row for the client.

     A gateway and both custom fields must also be configured in Settings (e.g. `hooks/clientadd.php:21-32`).
   - **Admin templates** send to every number in the template's **Admin GSM Numbers** field, split on commas (e.g. `hooks/AdminLogin.php:22-33`). They need a gateway in Settings, but not the custom fields. The exception is `ClientLogin_admin`, which also requires the logged-in client to be opted in (`hooks/ClientLogin_admin.php:22-36`).
   - If **Admin GSM Numbers** is empty (the default, `moneo_sms.php:58`), admin templates send nothing.
3. **Variables are replaced** (`smsclass.php:362-375`). The placeholders in the *Variables* column are replaced, in order, by the values the hook supplies.
   - If the template's stored variable list and the number of values don't match, the text is sent **unchanged**, with placeholders still in it.
4. **Turkish characters are converted** to ASCII: `ı İ ü Ü ö Ö ğ Ğ ç Ç ş Ş` → `i I u U o O g G c C s S` (`smsclass.php:36-39`, `:297-302`).
5. **The signature is appended.** If a *Signature* is set in Settings, it is added to the end of every message after a space (`smsclass.php:131-134`). The signature itself is not converted in step 4; only NetGsm converts the whole final text (`senders/netgsm.php:108-113`).
6. **The phone number is cleaned.** The characters `- ( ) . , +` and spaces are removed (`smsclass.php:352-356`). The gateway then applies its own formatting rules; see [providers.md](providers.md#phone-number-handling).
7. **The message is logged** in `mod_moneosms_messages` (`smsclass.php:273-291`).
   - The stored text is the message **without** the signature.
   - The stored number is the one cleaned in step 6, before the gateway's own formatting.

### Date formatting

The *Date format* setting is applied **only** to `{duedate}`, which is used by the 6 invoice templates (`hooks/invoice*.php`).

It supports exactly three tokens: `%d` (day), `%m` (month) and `%y` (**four-digit** year). For example, `%d.%m.%y` turns `2024-01-27` into `27.01.2024` (`smsclass.php:483-501`). Other tokens, such as `%Y`, are printed literally.

`{expirydate}` in DomainRenewalNotice is always sent in the database format `YYYY-MM-DD` (`hooks/domainrenewalnotice.php:49`).

### Editing templates

- **Every template is created with *Activate* ticked** (`smsclass.php:453`), so all 29 are active straight after the module is activated. Once a gateway and the custom fields are configured, they all send. Untick the ones you don't want first; see [Configure the module](../README.md#configure-the-module).
- The default text is written to the database **only when the template row does not exist yet**: on activation, and when the module is upgraded and finds a missing template. After that, only the variable list is refreshed. Your edited texts are kept (`smsclass.php:427-470`).
- Template text is limited to 1000 characters (`moneo_sms.php:59`).
- The **Extra** field is shown only for templates that have a non-empty `extra` value (`moneo_sms.php:373`). In 2.0.0 that is only *DomainRenewalNotice*.
- If you save Extra as empty or `0`, the field disappears from the form for good, because PHP treats both values as empty. The template then uses 15 days (`hooks/domainrenewalnotice.php:24`). To get the field back, set the `extra` column of that template row in `mod_moneosms_templates` directly.

## Client templates (20)

Every client template sends only to opted-in clients with a GSM number, as described in step 2.

| Template | WHMCS hook | Sent when | Variables | Default message | Source |
|---|---|---|---|---|---|
| `ClientAdd` | `ClientAdd` | A client account is created. The client is looked up by the hook's `userid` parameter; `{email}` and `{password}` come from the hook parameters. | `{firstname},{lastname},{email},{password}` | Dear {firstname} {lastname}, thank you for registering. Email: {email} Password: {password} | `hooks/clientadd.php` |
| `ClientChangePassword` | `ClientChangePassword` | A client's password is changed. | `{firstname},{lastname}` | Dear {firstname} {lastname}, your password has been changed. If you did not perform this action, please contact us immediately. | `hooks/clientchangepassword.php` |
| `AcceptOrder_SMS` | `AcceptOrder` | An order is accepted. The client is the owner of the order (`tblorders.userid`). | `{firstname},{lastname},{orderid}` | Dear {firstname} {lastname}, your order #{orderid} has been accepted. | `hooks/acceptorder.php` |
| `AfterRegistrarRegistration` | `AfterRegistrarRegistration` | A domain registration succeeds. `{domain}` is `sld.tld` from the registrar parameters. | `{firstname},{lastname},{domain}` | Dear {firstname} {lastname}, your domain {domain} has been registered successfully. | `hooks/afterregistrarregistration.php` |
| `AfterRegistrarRegistrationFailed` | `AfterRegistrarRegistrationFailed` | A domain registration fails. | `{firstname},{lastname},{domain}` | Dear {firstname} {lastname}, your domain {domain} could not be registered. Please contact us. | `hooks/afterregistrarregistrationfailed.php` |
| `AfterRegistrarRenewal` | `AfterRegistrarRenewal` | A domain renewal succeeds. | `{firstname},{lastname},{domain}` | Dear {firstname} {lastname}, your domain {domain} has been renewed successfully. | `hooks/afterregistrarrenewal.php` |
| `AfterModuleCreate_Hosting` | `AfterModuleCreate` | A service is provisioned **and** its product type is `hostingaccount`; other product types are ignored. | `{firstname},{lastname},{domain},{username},{password}` | Dear {firstname} {lastname}, your hosting for {domain} is now active. Username: {username} Password: {password} | `hooks/aftermodulecreate_hosting.php` |
| `AfterModuleSuspend` | `AfterModuleSuspend` | A `hostingaccount` service is suspended. | `{firstname},{lastname},{domain}` | Dear {firstname} {lastname}, your service has been suspended. ({domain}) | `hooks/aftermodulesuspend.php` |
| `AfterModuleUnsuspend` | `AfterModuleUnsuspend` | A `hostingaccount` service is unsuspended. | `{firstname},{lastname},{domain}` | Dear {firstname} {lastname}, your service has been reactivated. ({domain}) | `hooks/aftermoduleunsuspend.php` |
| `AfterModuleChangePassword` | `AfterModuleChangePassword` | A `hostingaccount` service's password is changed. | `{firstname},{lastname},{domain},{username},{password}` | Dear {firstname} {lastname}, the password for {domain} has been changed. Username: {username} Password: {password} | `hooks/AfterModuleChangePassword.php` |
| `AfterModuleChangePackage` | `AfterModuleChangePackage` | A `hostingaccount` service's package is changed. | `{firstname},{lastname},{domain}` | Dear {firstname} {lastname}, your service package has been changed. ({domain}) | `hooks/AfterModuleChangePackage.php` |
| `InvoiceCreated` | `InvoiceCreated` | An invoice is created. `{total}` is the invoice total as stored, with no currency formatting. `{duedate}` uses the *Date format* setting. | `{firstname},{lastname},{duedate},{total},{invoiceid}` | Dear {firstname} {lastname}, a new invoice #{invoiceid} for {total} has been created. Due date: {duedate}. | `hooks/invoicecreated.php` |
| `InvoicePaid` | `InvoicePaid` | An invoice is paid. | `{firstname},{lastname},{duedate},{invoiceid}` | Dear {firstname} {lastname}, your invoice #{invoiceid} (due {duedate}) has been paid. Thank you! | `hooks/invoicepaid.php` |
| `InvoicePaymentReminder_Reminder` | `InvoicePaymentReminder` | WHMCS sends a payment reminder with `type` = `reminder`. | `{firstname},{lastname},{duedate}` | Dear {firstname} {lastname}, you have an unpaid invoice due on {duedate}. Please visit our website for details. | `hooks/invoicepaymentreminder_reminder.php` |
| `InvoicePaymentReminder_Firstoverdue` | `InvoicePaymentReminder` | … with `type` = `firstoverdue`. | `{firstname},{lastname},{duedate}` | Dear {firstname} {lastname}, your invoice due on {duedate} is overdue. Please make payment as soon as possible. | `hooks/invoicepaymentreminder_first.php` |
| `InvoicePaymentReminder_secondoverdue` | `InvoicePaymentReminder` | … with `type` = `secondoverdue`. | `{firstname},{lastname},{duedate}` | Dear {firstname} {lastname}, your invoice due on {duedate} is still unpaid. Please make payment immediately. | `hooks/invoicepaymentreminder_second.php` |
| `InvoicePaymentReminder_thirdoverdue` | `InvoicePaymentReminder` | … with `type` = `thirdoverdue`. | `{firstname},{lastname},{duedate}` | Dear {firstname} {lastname}, your invoice due on {duedate} remains unpaid. Service may be suspended. Please pay now. | `hooks/invoicepaymentreminder_third.php` |
| `DomainRenewalNotice` | `DailyCronJob` | See [DomainRenewalNotice](#domainrenewalnotice) below. | `{firstname},{lastname},{domain},{expirydate},{x}` | Dear {firstname} {lastname}, your domain {domain} will expire on {expirydate} ({x} days remaining). Please renew it. | `hooks/domainrenewalnotice.php` |
| `TicketAdminReply` | `TicketAdminReply` | Staff reply to a ticket. The client is the ticket owner (`tbltickets.userid`); `{ticketsubject}` is the hook's `subject` parameter. | `{firstname},{lastname},{ticketsubject}` | Dear {firstname} {lastname}, your support ticket ({ticketsubject}) has been replied to. | `hooks/ticketadminreply.php` |
| `TicketClose` | `TicketClose` | A ticket is closed. `{ticketno}` is the ticket's public number (`tbltickets.tid`). | `{firstname},{lastname},{ticketno}` | Dear {firstname} {lastname}, your ticket #{ticketno} has been closed. | `hooks/ticketclose.php` |

The `type` values of the `InvoicePaymentReminder` hook are not listed in the [WHMCS hook reference](https://developers.whmcs.com/hooks-reference/invoices-and-quotes/#invoicepaymentreminder). The four values above are the ones the module compares against (`hooks/invoicepaymentreminder_*.php:17`). Whether WHMCS sends all four has not been verified.

### DomainRenewalNotice

This template is not tied to a domain event. It runs once per WHMCS daily cron run (`DailyCronJob`), so **the [WHMCS system cron](https://docs.whmcs.com/9-0/system/automation/system-cron/) must be set up**.

- **Extra** = number of days before expiry (default `15`). An empty or `0` value falls back to 15 (`hooks/domainrenewalnotice.php:24`).
- On each run it checks every domain with status `Active` and sends to the domain owner when *expiry date − Extra days* is **exactly today** (`hooks/domainrenewalnotice.php:25-36`). If the cron does not run on that day, that domain gets no notice.
- `{x}` is the Extra value, and `{expirydate}` is the raw `YYYY-MM-DD` date.

## Admin templates (9)

Admin templates go to the numbers in **Admin GSM Numbers** on the *Admin Templates* tab. Separate numbers with commas, e.g. `5321232525,5331002020`; spaces are removed when you save (`moneo_sms.php:335-337`). The number format each gateway expects is covered in [providers.md](providers.md#phone-number-handling).

| Template | WHMCS hook | Sent when | Variables | Default message | Source |
|---|---|---|---|---|---|
| `AdminLogin_admin` | `AdminLogin` | An admin logs in. `{username}` is the hook's `username` parameter. | `{username}` | {username} logged into the admin panel. | `hooks/AdminLogin.php` |
| `ClientLogin_admin` | `ClientLogin` | A client logs in, **only if that client is opted in** with a GSM number. ⚠️ `ClientLogin` is not listed in the current [WHMCS hook index](https://developers.whmcs.com/hooks/hook-index/), so this template may never fire on WHMCS 8+. | `{firstname},{lastname}` | {firstname} {lastname} logged into the client area. | `hooks/ClientLogin_admin.php` |
| `ClientAdd_admin` | `ClientAdd` | A client account is created. | *(none)* | A new client has registered on your website. | `hooks/clientadd_admin.php` |
| `TicketOpen_admin` | `TicketOpen` | A ticket is opened. `{subject}` is the hook's `subject` parameter. | `{subject}` | A new support ticket has been opened: {subject} | `hooks/ticketopen_admin.php` |
| `TicketUserReply_admin` | `TicketUserReply` | A client replies to a ticket. | `{subject}` | A ticket has been updated by a client: {subject} | `hooks/ticketuserreply_admin.php` |
| `AfterRegistrarRegistration_admin` | `AfterRegistrarRegistration` | A domain registration succeeds. | `{domain}` | A new domain has been registered: {domain} | `hooks/afterregistrarregistration_admin.php` |
| `AfterRegistrarRenewal_admin` | `AfterRegistrarRenewal` | A domain renewal succeeds. | `{domain}` | A domain has been renewed: {domain} | `hooks/afterregistrarrenewal_admin.php` |
| `AfterRegistrarRegistrationFailed_admin` | `AfterRegistrarRegistrationFailed` | A domain registration fails. | `{domain}` | Domain registration failed: {domain} | `hooks/afterregistrarregistrationfailed_admin.php` |
| `AfterRegistrarRenewalFailed_admin` | `AfterRegistrarRenewalFailed` | A domain renewal fails. | `{domain}` | Domain renewal failed: {domain} | `hooks/afterregistrarrenewalfailed_admin.php` |

There is no client-facing template for `AfterRegistrarRenewalFailed`.

## Notes and caveats (as of 2.0.0)

- **Passwords in SMS.** `ClientAdd`, `AfterModuleCreate_Hosting` and `AfterModuleChangePassword` can include passwords in plain text. That text is stored in the message log and passes through your SMS gateway. Remove `{password}` from these templates if that is not acceptable.
- **`ClientAdd` reads the `userid` hook parameter.** WHMCS marks it as deprecated in favour of `client_id` / `user_id` ([WHMCS client hooks](https://developers.whmcs.com/hooks-reference/client/#clientadd)).
- **Both hooks fire on one event.** `ClientAdd` and `ClientAdd_admin`, like the client and admin `AfterRegistrar*` templates, run on the same WHMCS hook. One event can therefore send one client SMS plus one SMS per admin number.
