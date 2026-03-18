# Amazon SES integration for CiviCRM

Relays outgoing CiviCRM emails using the Amazon SES API (over https).

Also exposes a webhook page to process bounces and complaints from Amazon SES (Simple Email Service)
through Amazon SNS (Simple Notification Service) notifications.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Installation

See: https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension

## Setup

See [detailed configuration steps here](docs/configuration.md). 

### Events
#### SubscriptionConfirmation

If the _Notification_ is a _SubscriptionConfirmation_ it automatically _subscribes_ to it.

#### Bounce

If it's a Bounce, it maps SES' bounce type to Civi's bounce type as follows:

```php
    $sesBounceTypes['Undetermined']['Undetermined'] = 'Invalid';
    $sesBounceTypes['Permanent']['General'] = 'Invalid';
    $sesBounceTypes['Permanent']['NoEmail'] = 'Invalid';
    $sesBounceTypes['Permanent']['Suppressed'] = 'Invalid';
    $sesBounceTypes['Permanent']['OnAccountSuppressionList'] = 'Invalid';
    $sesBounceTypes['Transient']['General'] = 'Relay';
    $sesBounceTypes['Transient']['MailboxFull'] = 'Quota';
    $sesBounceTypes['Transient']['MessageTooLarge'] = 'Relay';
    $sesBounceTypes['Transient']['ContentRejected'] = 'Spam';
    $sesBounceTypes['Transient']['AttachmentRejected'] = 'Spam';
```

#### Complaint

If the _Notification_ is a _Complaint_ it creates a bounce in CiviCRM of type **Spam** and sets the **opt-out** flag for the contact identified by the email address in the notification.

This is implemented in the same way as used by the coopsymbiotic fork of sparkpost: https://github.com/coopsymbiotic/sparkpost/blob/coopsymbiotic/CRM/Sparkpost/Page/callback.php#L151

#### See also:

* AWS extension: https://github.com/mecachisenros/aws which could replace this extension in the future.
* AirMail extension: https://github.com/aghstrategies/com.aghstrategies.airmail which is similar but handles bounces/complaints in CiviCRM slightly differently and doesn't verify webhook signatures.

## Support and Maintenance

This extension is supported and maintained with the help and support of the CiviCRM community by [MJW](https://www.mjwconsult.co.uk).

We offer paid [support and development](https://mjw.pt/support) as well as a [troubleshooting/investigation service](https://mjw.pt/investigation).
