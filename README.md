# Moneo SMS - WHMCS SMS Notification Module

An open-source, feature-rich SMS notification addon for WHMCS. Automatically send SMS messages to clients and administrators based on WHMCS events such as order acceptance, invoice creation, ticket updates, and more.

## Requirements

- **WHMCS** 8.0 or later
- **PHP** 8.1 or later (8.2 recommended)
- **PHP Extensions**: cURL, JSON, SimpleXML
- An active account with one of the supported SMS gateways

## Features

- **27+ automated hooks** covering all major WHMCS events
- **14 SMS gateway providers** supported out of the box
- **Client and Admin notifications** with separate template management
- **Template variables** for personalized messages
- **Bulk SMS sending** to all opted-in clients
- **Message history** with delivery status tracking
- **Multi-language** admin interface (English, Turkish, Russian)
- **Credit balance** display (where supported by provider)

## Installation

1. Upload the `modules/` folder to your WHMCS root directory.
2. Go to **Admin Area** > **Setup** > **Addon Modules**.
3. Find **Moneo SMS** and click **Activate**.
4. Grant access to the admin groups that should manage the module.

### Configure Custom Client Fields

5. Go to **Setup** > **Custom Client Fields**.
6. Add a **Tick Box** field:
   - **Name**: `Subscribe to SMS`
   - **Show on Order Form**: Checked
   - This allows clients to opt-in to SMS notifications during registration.
7. Add a **Text Box** field:
   - **Name**: `GSM Number`
   - **Show on Order Form**: Checked
   - Clients will enter their mobile number here.

### Configure the Module

8. Go to **Addons** > **Moneo SMS**.
9. In the **Settings** tab:
   - Select your SMS Gateway provider.
   - Enter your API credentials.
   - Set the Sender ID and Signature.
   - Map the custom fields you created above.
10. In the **Client Templates** and **Admin Templates** tabs, customize your message templates.

## Supported SMS Gateways

| Provider | Region | Website |
|----------|--------|---------|
| ClickAtell | Global | [clickatell.com](https://www.clickatell.com) |
| NetGsm | Turkey | [netgsm.com.tr](https://www.netgsm.com.tr) |
| Ucuz Sms Al | Turkey | [ucuzsmsal.com](https://www.ucuzsmsal.com) |
| MutluCell | Turkey | [mutlucell.com](https://www.mutlucell.com) |
| Dakik SMS | Turkey | [dakiksms.com](https://www.dakiksms.com) |
| BirSMS | Turkey | [1sms.com.tr](https://www.1sms.com.tr) |
| HemenPosta | Turkey | - |
| msg91.com | India | [msg91.com](https://msg91.com) |
| ByteHand | Global / Russia | [bytehand.com](https://bytehand.com) |
| SMS Gateway | Global (Android) | [smsgateway.me](https://smsgateway.me) |
| SendSms.ro | Romania / Global | [sendsms.ro](https://www.sendsms.ro) |
| Route SMS | Global | - |
| OneTouchSMS | Global | - |

## Supported Hooks

### Client Notifications

| Hook | Description |
|------|-------------|
| `ClientAdd` | Welcome SMS after client registration |
| `ClientChangePassword` | Notification when client changes password |
| `AcceptOrder` | Notification when order is accepted |
| `AfterRegistrarRegistration` | Domain registered successfully |
| `AfterRegistrarRegistrationFailed` | Domain registration failed |
| `AfterRegistrarRenewal` | Domain renewed successfully |
| `AfterModuleCreate` | Hosting account created (with credentials) |
| `AfterModuleSuspend` | Service suspended notification |
| `AfterModuleUnsuspend` | Service reactivated notification |
| `AfterModuleChangePassword` | Hosting password changed |
| `AfterModuleChangePackage` | Service package changed |
| `InvoiceCreated` | New invoice created |
| `InvoicePaid` | Invoice payment received |
| `InvoicePaymentReminder` | Payment reminder |
| `InvoicePaymentReminder` (1st overdue) | First overdue notice |
| `InvoicePaymentReminder` (2nd overdue) | Second overdue notice |
| `InvoicePaymentReminder` (3rd overdue) | Third overdue notice |
| `DomainRenewalNotice` | Domain expiry reminder ({x} days before) |
| `TicketAdminReply` | Admin replied to support ticket |
| `TicketClose` | Support ticket closed |

### Admin Notifications

| Hook | Description |
|------|-------------|
| `AdminLogin` | Admin panel login alert |
| `ClientLogin` | Client login notification |
| `ClientAdd` | New client registration alert |
| `TicketOpen` | New ticket opened |
| `TicketUserReply` | Client replied to ticket |
| `AfterRegistrarRegistration` | Domain registered |
| `AfterRegistrarRenewal` | Domain renewed |
| `AfterRegistrarRegistrationFailed` | Domain registration failed |
| `AfterRegistrarRenewalFailed` | Domain renewal failed |

## Upgrading from v1.x

Version 2.0.0 is a complete rewrite for WHMCS 8.x compatibility:

- All deprecated `mysql_*` functions replaced with WHMCS Capsule DBAL
- PHP 8.1+ type hints and modern syntax
- SQL injection vulnerabilities fixed with parameterized queries
- CSRF protection on all forms
- Modernized Bootstrap admin UI
- InnoDB engine with utf8mb4 charset support

**Upgrade steps:**

1. Back up your existing module files and database.
2. Upload the new files to your WHMCS installation, overwriting the old ones.
3. Go to **Setup** > **Addon Modules** and the upgrade will run automatically.
4. Verify your settings and templates are intact.

## Adding a New SMS Provider

1. Create a new file in `modules/addons/moneo_sms/senders/` (e.g., `myprovider.php`).
2. Create a class that extends `MoneoSms` and implements `SmsSenderInterface`.
3. Implement `send()`, `balance()`, `report()`, `utilgsmnumber()`, and `utilmessage()`.
4. Return the provider configuration array at the end of the file:

```php
return [
    'value'  => 'myprovider',
    'label'  => 'My Provider',
    'fields' => ['user', 'pass'],
];
```

## Contributing

Contributions are welcome! You are free to add new hooks, gateways, features, or bug fixes. Fork the repository, make your changes, and submit a pull request.

## Contributors

- [Güven Atbakan](https://github.com/shibby)
- [Emir Karşıyakalı](https://github.com/emir)
- [Turgay Çoşkun](https://github.com/adalim61)
- [Hakan Ersu](https://github.com/hakanersu)

## License

This project is licensed under the [MIT License](LICENSE.md).