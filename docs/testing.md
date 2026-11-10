# Smoke test checklist

Moneo SMS has no automated test suite. This manual checklist is how a release gets verified on a real WHMCS installation. Run it on a **test or staging WHMCS**, never on production. Use a gateway account with little credit, and your own phone number as the recipient.

When you are done, report the result (see [Reporting](#reporting)) so that the README's [compatibility table](../README.md#compatibility--testing-status) can be filled in.

## Test environment

Record this at the top of your report:

| Item | Value |
|---|---|
| Moneo SMS version / commit | |
| WHMCS version | |
| PHP version (`php -v`) | |
| Database (MySQL/MariaDB version) | |
| SMS gateway used | |
| Date and tester | |

## 1. Preflight

- [ ] Run `php -l` on every file under `modules/addons/moneo_sms/`; it reports no syntax errors. Example: `find modules/addons/moneo_sms -name '*.php' -print0 | xargs -0 -n1 php -l`.
- [ ] The PHP version is 8.1 or newer and is supported by your WHMCS version (see the [README](../README.md#requirements)).
- [ ] The cURL, JSON and mbstring extensions are loaded (`php -m`). SimpleXML too if you test BirSMS or OneTouchSMS.

## 2. Install and activate

- [ ] Copy `modules/addons/moneo_sms/` into WHMCS and activate *Moneo SMS* under **Configuration > System Settings > Addon Modules**.
- [ ] **Expected:** a success message, and three new tables. `mod_moneosms_templates` contains **29** rows, 20 with `type = 'client'` and 9 with `type = 'admin'`, **all with `active = 1`**. `mod_moneosms_settings` contains 1 row.
- [ ] **Addons > Moneo SMS** opens without PHP errors; check the WHMCS activity log and the PHP error log.

## 3. Custom fields

- [ ] Create a client **Tick Box** field and a **Text Box** field, both with **Show on Order Form** enabled.
- [ ] **Expected:** both appear in the matching dropdowns on the module's **Settings** tab.
- [ ] Negative check: disable *Show on Order Form* on one field. **Expected:** it disappears from the dropdown. Re-enable it afterwards.

## 4. Settings

- [ ] **First**, on the Client Templates and Admin Templates tabs, untick *Activate* on every template you are not about to test, and save. All 29 start active. Once the gateway and fields are set, every active template sends.
- [ ] Change the gateway selection. **Expected:** the page reloads and shows that gateway's credential fields.
- [ ] If you want the gateway that is already shown, click **Save** once instead. **Expected:** its credential fields appear. The list has no empty entry, so picking the shown gateway triggers no reload.
- [ ] Enter the credentials, Sender ID, a Signature, the two field mappings and the date format `%d.%m.%y`, then save.
- [ ] **Expected:** the values are still there after a reload. A credit panel appears if the gateway supports it ([providers.md](providers.md)).

## 5. Test client

- [ ] Create a client with the opt-in tick box **checked** and your mobile number in the GSM field, in the format your gateway expects ([providers.md](providers.md#phone-number-handling)).
- [ ] Create a second client with the opt-in tick box **unchecked**.

## 6. Manual send

- [ ] On **Send SMS**, select the opted-in client, enter a message, tick *Enable Debug Log* and send.
- [ ] **Expected:** the SMS arrives with the signature appended, a success message is shown, the debug log is printed, and a new row appears on **Sent Messages**.
- [ ] Record whether the debug log shows gateway credentials. This is a known issue in 2.0.0; see [providers.md](providers.md#security-notes-as-of-200).
- [ ] Select **two** clients and send. Record that only one SMS is sent. This is a known limitation in 2.0.0.

## 7. Client templates

Make sure each template under test is active (all start active; you unticked the others in section 4). Trigger the event in WHMCS, then check both the phone and **Sent Messages**. Do each one for the opted-in client, then repeat one of them for the non-opted-in client and expect **no** SMS.

- [ ] `ClientAdd`: create a new client with opt-in and a GSM number.
- [ ] `ClientChangePassword`: change the test client's password.
- [ ] `AcceptOrder_SMS`: place an order and accept it in the admin area.
- [ ] `InvoiceCreated`: create an invoice. Check that `{duedate}` uses the date format and `{total}` is filled in.
- [ ] `InvoicePaid`: mark the invoice as paid.
- [ ] `TicketAdminReply`: open a ticket as the client and reply as staff.
- [ ] `TicketClose`: close the ticket.
- [ ] `AfterModuleCreate_Hosting` / `AfterModuleSuspend` / `AfterModuleUnsuspend` / `AfterModuleChangePassword` / `AfterModuleChangePackage`: needs a hosting product with a working server module. Mark "not exercised" if you don't have one.
- [ ] `AfterRegistrarRegistration` / `…Failed` / `AfterRegistrarRenewal`: needs a registrar module (sandbox). Mark "not exercised" if you don't have one.
- [ ] `InvoicePaymentReminder_*`: trigger payment reminders through the cron if feasible, and record which `type` values arrive. Otherwise mark "not exercised".

## 8. Admin templates

- [ ] Enter two comma-separated numbers in **Admin GSM Numbers** on the admin templates you test.
- [ ] `AdminLogin_admin`: log in to the admin area. **Expected:** one SMS per number.
- [ ] `ClientAdd_admin`, `TicketOpen_admin`, `TicketUserReply_admin`: trigger the event.
- [ ] `ClientLogin_admin`: log in to the client area as the opted-in client, then as the non-opted-in client. **Record whether any SMS is sent.** WHMCS's current hook index does not list `ClientLogin`.
- [ ] Clear the Admin GSM Numbers on one template and trigger it. **Expected:** no SMS.

## 9. DomainRenewalNotice (cron)

This template runs on the `DailyCronJob` hook. WHMCS fires that hook "at the very end of the daily automation cron execution" ([WHMCS cron hooks](https://developers.whmcs.com/hooks-reference/cron/#dailycronjob)). A plain `cron.php` run does **not** repeat a daily run that already happened today.

- [ ] Make sure the template is active and leave **Extra** at `15`.
- [ ] Give an **Active** test domain of the opted-in client an expiry date exactly 15 days from today.
- [ ] Force the daily automation with `php -q /path/to/crons/cron.php all --force -vvv`. The `-F` / `--force` option runs tasks "regardless of whether the task is due" ([WHMCS system cron options](https://docs.whmcs.com/9-0/system/automation/system-cron/#options)); your `crons` folder may have been moved. **Expected:** one SMS, in which `{expirydate}` shows as `YYYY-MM-DD`.
- [ ] **Record whether the forced run fired `DailyCronJob`.** This is not verified. If no SMS and no new *Sent Messages* row appear, check the WHMCS activity log, then wait for the next scheduled daily run (*Time of Day* in Automation Settings) and note which method worked.
- [ ] Change the expiry date to 16 days from today and trigger the daily run again the same way. **Expected:** no SMS, because the module sends only when *expiry date − Extra* is exactly today.

## 10. Sent Messages tab

- [ ] Rows show the client, number, text, time and status.
- [ ] For a gateway with delivery reports, the status is filled in when you reopen the tab.
- [ ] Pagination works with more than 10 messages.
- [ ] Deleting a message removes the row.

## 11. Template edits persist

- [ ] Edit a template text, untick *Activate* on another, and save. **Expected:** both changes persist after a reload, and the deactivated template no longer sends.
- [ ] **Upgrade path.** WHMCS calls an addon's upgrade function only when the version number in its config changes ([WHMCS addon upgrades](https://developers.whmcs.com/addon-modules/upgrades/)), so re-uploading the same version tests nothing.
  - On a test copy, raise `"version"` in `moneo_sms_config()` (`moneo_sms.php:23`).
  - Upload it and open the addon page.
  - **Expected:** your edited texts and *Activate* states are **not** replaced by the defaults, because `checkHooks()` only adds missing templates and refreshes `variables` (`smsclass.php:427-470`).

## 12. Deactivate (destructive; do this last)

- [ ] **Back up the database first.**
- [ ] Deactivate *Moneo SMS*. **Expected:** the three `mod_moneosms_*` tables are gone. See the warning in the [README](../README.md#configure-the-module).
- [ ] Activate again. **Expected:** fresh default settings and the 29 default templates; the earlier edits and history are gone.

## Reporting

Open an issue or pull request with:

1. the environment table above;
2. the checklist with each item marked **pass**, **fail** (with the error or log excerpt; remove any credentials) or **not exercised**.

A WHMCS + PHP combination may be marked as verified in the README only when sections 1–8 and 10–12 pass. Note in the same row whether section 9 (cron) was exercised.
