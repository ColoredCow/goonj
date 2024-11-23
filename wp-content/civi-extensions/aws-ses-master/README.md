# AWS SES integration

## Features

Uses SES bounce and complaint types.

## Installation

Download and install as you would a normal CiviCRM extension. No special steps are required.

## Configuration

1. Navigate to `civicrm/admin/settings/aws-ses` to display (and update if required) the secret token.
2. Configure AWS to send SES bounce and complaint notifications.
   - Notification url: `https://<site-name>/civicrm/aws-ses/listen?secret=<YOUR_SECRET>`

## Roadmap

- Process forwarded emails to better understand reasons for bounces.
- Process email client unsubscribes
