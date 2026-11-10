# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/). The repository has no release tags; versions refer to `version.txt`.

## [Unreleased]

### Documentation

- **README rewritten for accuracy.**
  - Requirements now match the officially supported WHMCS/PHP combinations, with sources.
  - Corrected counts: 13 gateways (not 14) and 29 templates (not "27+").
  - Replaced the "bulk SMS to all opted-in clients" claim; the *Send SMS* tab sends to one client.
  - Removed the unverified claims "CSRF protection on all forms" and "InnoDB engine with utf8mb4".
  - Updated the admin menu paths to WHMCS 8+/9.
  - Stated that *Show on Order Form* is required for the custom fields.
  - Added a warning that deactivating the module deletes all module data.
  - Added a warning that all templates are active right after activation, and reordered the setup steps so templates are reviewed before the gateway is configured.
  - Added a compatibility & testing status table and a list of known limitations.
- **Corrected the upgrade instructions.** v1 → v2 is not an in-place upgrade, and no data is migrated.
- **Added new documentation:**
  - [`docs/templates.md`](docs/templates.md): all 29 templates, with variables, default texts and trigger conditions.
  - [`docs/providers.md`](docs/providers.md): the gateway capability matrix, phone number handling and security notes.
  - [`docs/upgrading-from-v1.md`](docs/upgrading-from-v1.md).
  - [`docs/adding-a-provider.md`](docs/adding-a-provider.md): the gateway contract, with an example.
  - [`docs/testing.md`](docs/testing.md): a manual smoke-test checklist.
  - This changelog.

## [2.0.0] - 2026-02-16

A rewrite of the 1.x module (`aktuel_sms`, last version 1.1.8) under a new name. **v2.0.0 is not an in-place upgrade of 1.x.** See [docs/upgrading-from-v1.md](docs/upgrading-from-v1.md).

### Changed

- **Module renamed** from `aktuel_sms` to `moneo_sms`:
  - the folder, the function prefix and the core class (`AktuelSms` → `MoneoSms`);
  - the database tables (`mod_aktuelsms_*` → `mod_moneosms_*`).

  No data is migrated from the 1.x tables.
- **Database access rewritten.** The `mysql_*` calls are replaced by the WHMCS Capsule query builder and schema builder.
- **PHP 8 syntax.** The code uses typed properties, return types and `string|false` union types, so it needs PHP 8.0 or newer. Because WHMCS does not support PHP 8.0, that means PHP 8.1 or newer in practice. PHP 7.x is no longer supported.
- **`SmsSenderInterface` has typed method signatures.**
- **Admin interface reworked** with Bootstrap panels and tabs.
- **English is the default module language**, and the default template texts are now English. They were Turkish in 1.x. Turkish and Russian translations are kept.
- **Database columns enlarged:** template text 240 → 1000 characters, `extra` 3 → 10, recipient number 15 → 20, status 10 → 20, and gateway settings `varchar(500)` → `text`.

### Security

- **Database queries use parameter binding.** In 1.x, values were concatenated into SQL strings.
- **The gateway name is validated** against `^[a-z0-9_]+$` before its file is included, and the gateway class must implement `SmsSenderInterface`.
- **Most admin page output is escaped** with `htmlspecialchars`. Some language strings that contain HTML are printed as-is. The `tab` parameter is checked against a whitelist.
- **SSL certificate verification is enabled** in the cURL helpers (`curlGet()` and `curlPost()`).

### Unchanged

- **The same 13 gateways** as 1.1.8: BirSMS, ByteHand, ClickAtell, Dakik SMS, HemenPosta, msg91, MutluCell, NetGsm, OneTouchSMS, Route SMS, SendSms.ro, SMS Gateway and Ucuz Sms Al.
- **The same 29 templates**, with the same template names, as 1.1.8.

### Known issues

See [Known limitations](README.md#known-limitations) in the README. Among them:

- *Send SMS* sends to only one client.
- Gateway credentials are written to the message log.
- Some gateways use plain HTTP.
- `ClientLogin_admin` may never fire on current WHMCS versions.
