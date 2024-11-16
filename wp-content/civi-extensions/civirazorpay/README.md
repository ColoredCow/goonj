# CiviCRM Razorpay Payment Processor

Integrates the Razorpay payment processor into CiviCRM so you can use it to accept UPI, Cards, Netbanking payments on your site.

* https://razorpay.com/

Latest releases can be found in the [CiviCRM extensions directory](https://civicrm.org/extensions/razorpay-payment-processor)
## Documentation

### Installation

1. Download the extension code
2. Put it in your CiviCRM extension directory
3. Go to `CiviCRM > Administer > System Settings > Extensions`
4. Find `com.coloredcow.civirazorpay` in the extension list and enable it
5. Optionally, you can also use `cv` command-line tool to perform step 3 and 4

### Setup

The setup has two parts: one needs to be done at Razorpay's end the other in CiviCRM. These are explained below.
#### Obtain Razorpay API Key

1. Login to [Razorpay dashboard](https://dashboard.razorpay.com/)
2. Optionally after logging in you can switch to `Test Mode` if you want to test the integration first
3. Go to `Account & Settings`
4. Under `Website and app settings` click on `API Keys`
5. Generate API Key. Razorpay will also provide a secret for the generated API key
6. Save both API Key and Secret securely
#### CiviCRM Payment Processor

1. Go to `CiviCRM > CiviContribute > Payment Processors`
2. Click on `Add Payment Processor`
3. Select `Razorpay` in the `Payment Processor Type`
4. Fill in the rest of the details along with correct API key and secrets

### Usage

Once the Payment Processor of type Razorpay has been added, the next step is to use this payment processor to accept payments on contribution pages.

1. Go to `CiviCRM > Contributions > Manage Contribution Pages`
2. Add a new contribution page or configure and existing one
3. In the `Amounts` tab when configuring a contribution page, you will see a field `Payment Processor`
4. Check the `Razorpay` option for the `Payment Processor`
5. Fill in the required fields and save
6. That's it!

## Maintainers

Crafted by [ColoredCow](https://github.com/coloredcow/)
