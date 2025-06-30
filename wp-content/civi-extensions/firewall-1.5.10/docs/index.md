# firewall

This implements a simple firewall for CiviCRM that blocks by IP address in various scenarios.

## Installation

See: https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension

Configure via **Administer->System Settings->Firewall Settings**

## Setup

### Scheduled Jobs

#### Job.Firewall_cleanup

This job automatically removes old entries from the `civicrm_firewall_ipaddress` table after 1 month.

* Run: Daily
* Domain-specific: No. This job only needs to be run on one of the domains for multisite/multidomain setup.

If this job is *not* running then the `civicrm_firewall_ipaddress` table will gradually increase in size.

### Settings

#### CSRF validity

* There is a setting `firewall_csrf_timeout` (default 43200 (12 hours)) that controls how long generated CSRF tokens
are valid for. This accepts an integer number of seconds.

## Scenarios

### Blocking time

The extension currently blocks an IP address when there are a number of events equal to the 'threshold' for the event
type in the past two hours. Once the number of events in the last 2 hours drop below the threshold, the IP address will
be automatically unblocked.

### Event types

#### Fraud Events

Threshold: 3

You can trigger a Fraud Event by calling:
```php
\Civi\Firewall\Event\FraudEvent::trigger([ip address], "my helpful description");
```

#### Declined Card Events

Threshold: 10

You can trigger a Declined Card Event by calling:
```php
\Civi\Firewall\Event\DeclinedCardEvent::trigger([ip address], "my helpful description");
```

Multiple declined card attempts could be an indicator of card testing via your site.

#### Invalid CSRF Events

Threshold: 10

If you implement APIs or AJAX endpoints which require anonymous access (eg. a javascript based payment processor
such as [Stripe](https://lab.civicrm.org/extensions/stripe)) then you will probably need to protect them with a CSRF token.

First get a token and pass it to your form/endpoint, eg:

```php
$myVars = [
  'token' => class_exists('\Civi\Firewall\Firewall') ? \Civi\Firewall\Firewall::getCSRFToken() : NULL,
];
```

OR

```php
$token = \Civi\Firewall\Firewall::getCSRFToken();
```

Then in your API/AJAX endpoint check if the token is valid:

```php
if (class_exists('\Civi\Firewall\Firewall')) {
  $firewall = new \Civi\Firewall\Firewall();
  if (!$firewall->checkIsCSRFTokenValid(CRM_Utils_Request::retrieveValue('token', 'String'))) {
    self::returnInvalid($firewall->getReasonDescription());
  }
}
```

!!! Note: By checking if the class exists the firewall extension can be an optional dependency.

#### Integration with Formprotection extension

Threshold: 10

The [Formprotection extension](https://civicrm.org/extensions/form-protection) (1.4.0+) triggers `FormProtectionEvent`s
whenever a user fails to submit a form due to anti-spam measures (such as reCAPTCHA, flood control or honeypot).

You can also trigger this event in your own custom form protection measures.

## Hooks

The following hooks are provided by the extension.

### hook_civicrm_firewallRequestBlocked

`hook_civicrm_firewallRequestBlocked($clientIP)`

This hook is called when the firewall blocks a request from a certain IP address. The `$clientIP` parameter contains the IP
address which has been blocked.

## Future Development / Ideas

Thanks to @artfulrobot for testing and writing down some ideas for future development.

* Some forensic logging of bad things happening would be good. Who made the request, why was it bad, what was the content of the request, what was the user agent and the http method, were they logged in (!) - and as which user, is there any other relevant context? This way sites can use that data to be clevererer with setting limits/identifying traits of spammers.
* All rates/limits should be configurable (limit and period per event; how long logs are kept for).
* csrf tokens could include time limits and getter / checker could also take a param for the purpose - so one token doesn't work across different forms/purposes. Is there an advantage to tying in the IP to the token, too? I know you said this could come later.
* I like the idea that we could use it for more stuff, like thwarting other form submission spam.
* Also, should we log when we've denied someone something? I know there's the server logs with 403s. Just thinking that when I've been in this sort of situation, you can never get enough information. e.g. if it's baddies: need to study their behaviour; if it's goodies getting frustrated, good to understand what happened there, too, as it may help solve a supporter relations issue.

  *Currently the records are kept in the database table for one month. So you can work out when an IP was blocked - but it does require a bit of calculation.*

## Support and Maintenance
This extension is supported and maintained with the help and support of the CiviCRM community by:

[![MJW Consulting](images/mjwconsulting.jpg)](https://www.mjwconsult.co.uk)

We offer paid [support and development](https://mjw.pt/support) as well as a [troubleshooting/investigation service](https://mjw.pt/investigation).
