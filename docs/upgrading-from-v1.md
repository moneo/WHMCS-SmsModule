# Upgrading from v1.x (`aktuel_sms`) to v2.0.0 (`moneo_sms`)

v2.0.0 is not an in-place update. It is a **new addon module** with a new folder name and new database tables:

| | v1.x (last: 1.1.8) | v2.0.0 |
|---|---|---|
| Module folder | `modules/addons/aktuel_sms/` | `modules/addons/moneo_sms/` |
| Name in *Addon Modules* | Aktuel Sms | Moneo SMS |
| Database tables | `mod_aktuelsms_settings`, `mod_aktuelsms_templates`, `mod_aktuelsms_messages` | `mod_moneosms_settings`, `mod_moneosms_templates`, `mod_moneosms_messages` |

Sources: `aktuel_sms.php` at commit `a0600dc`, and `moneo_sms.php:18-98` in v2.

**Nothing is migrated automatically.** v2's upgrade routine only alters `mod_moneosms_*` tables (`moneo_sms.php:119-175`). Uploading v2 next to v1 does not overwrite v1, because the folder name is different.

Your v1 gateway settings, edited templates, admin numbers and message history stay in the `mod_aktuelsms_*` tables, and v2 does not read them.

## ⚠️ Do not run both versions at the same time

v1 and v2 declare the **same global PHP names**:

- the interface `SmsSenderInterface`;
- gateway classes such as `netgsm`;
- hook functions such as `ClientAdd`.

The interface and the gateway classes are declared without any existence check. WHMCS loads an addon module's `hooks.php` on every page ([WHMCS docs](https://developers.whmcs.com/addon-modules/hooks/)). If both modules are activated and their hook files are loaded in the same request, PHP is expected to stop with a fatal *"Cannot declare interface/class … because the name is already in use"* error.

This conclusion comes from reading the code; it has not been reproduced on a live install. Either way, remove v1 completely before you activate v2.

## Before you start

1. **Take a full database backup** and a copy of your WHMCS files.
2. **Write down your v1 configuration**, because you will enter it again in v2:
   - **Settings tab:** the selected gateway, every credential field, Sender ID, Signature, the two custom-field mappings and the date format.
   - **Client and Admin template tabs:** for every template, the text, the *Active* state, the **Extra** value (DomainRenewalNotice) and the **Admin GSM numbers**.
3. **Optional:** export the three `mod_aktuelsms_*` tables with your database tool if you want to keep the message history. v2 cannot display it.

> **Security note:** v1 also wrote the gateway settings, including passwords, into each message's log (`smsclass.php` line 116 at commit `a0600dc`). Treat old backups and exports of `mod_aktuelsms_messages` as sensitive.

## Upgrade steps

1. **Back up**, as described above.
2. **Deactivate v1.** Go to **Configuration (🔧) > System Settings > Addon Modules**, find *Aktuel Sms* and click **Deactivate**.

   ⚠️ v1's deactivate routine runs `DROP TABLE` on all three `mod_aktuelsms_*` tables (`aktuel_sms.php`, function `aktuel_sms_deactivate`, at commit `a0600dc`). After this step the v1 data exists only in your backup.
3. **Delete the folder** `modules/addons/aktuel_sms/` from your server.
4. **Upload v2** (`modules/addons/moneo_sms/`) and activate *Moneo SMS*. See [Installation](../README.md#installation).
5. **Enter your templates again, before anything else.** v2 creates all 29 templates **active** (`smsclass.php:453`), with English default texts. As soon as steps 6 and 7 are done, every active client template starts sending.
   - On the **Client Templates** and **Admin Templates** tabs, untick *Activate* on every template that was inactive in v1, or that you don't want.
   - Paste your v1 texts back in. The template names are identical in v1 and v2, all 29 of them (e.g. `ClientAdd`, `InvoicePaymentReminder_Firstoverdue`).
   - Set the DomainRenewalNotice **Extra** value and the Admin GSM numbers.
   - Save each tab.

   Check each template's *Variables* list in v2 before you paste a v1 text, and use only the placeholders listed there ([docs/templates.md](templates.md)).
6. **Map the custom fields again.** Your existing client custom fields (*Subscribe to SMS*, *GSM Number*) belong to WHMCS, not to the module, so they and the clients' values still exist. Select them again on the v2 **Settings** tab. "Show on Order Form" must be enabled on both fields, or they won't appear in the list.
7. **Enter the gateway and credentials again**, plus Sender ID, Signature and date format.
8. **Run the smoke test** in [docs/testing.md](testing.md).

### Copying the data with SQL instead (advanced)

The v1 and v2 tables use the same column names for settings (`api`, `apiparams`, `wantsmsfield`, `gsmnumberfield`, `dateformat`) and for templates (`name`, `type`, `admingsm`, `template`, `variables`, `active`, `extra`, `description`). Both versions store the gateway settings in `apiparams` as JSON. An experienced database administrator can therefore move the data by hand, from a backup.

**Do not INSERT the v1 rows.** Activating v2 already creates one settings row and one row per template. The module reads them with `->first()` and no ordering (`smsclass.php:79`, `:475-477`), so extra copied rows would most likely be ignored. **UPDATE the existing v2 rows instead**, after activating v2. Update the template rows first and the settings row last, because filling in the settings row is what switches sending on.

1. **`mod_moneosms_templates`**: for each v1 template row, update the v2 row with the same `name` **and** `type`, setting `template`, `active`, `admingsm` and `extra`. Do not copy:
   - `id`;
   - `variables`, because v2's list must stay as it is. A placeholder list that doesn't match what the hook supplies makes the module send the text with the placeholders left in (`smsclass.php:370-371`);
   - `description`.

   Any v2 template that has no v1 counterpart stays **active**. Review it on the template tabs.
2. **`mod_moneosms_settings`** (one row): set `api`, `apiparams`, `wantsmsfield`, `gsmnumberfield` and `dateformat` from v1's settings row. Do not copy `id` or `version`.

Afterwards, check every copied template text against the v2 *Variables* list ([docs/templates.md](templates.md)).

**No tested migration script is provided.** Work on a copy of the database first. An automated import is tracked as a follow-up for a future release.

## What else changed between v1 and v2

See [CHANGELOG.md](../CHANGELOG.md). In short: the same 13 gateways and the same 29 templates, rewritten for PHP 8 and the WHMCS database layer, with an updated admin interface.
