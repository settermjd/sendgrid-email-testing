# Testing the Sendgrid PHP Helper Library

This is a small project showing how to test emails sent with SendGrid in PHP using SendGrid's PHP Helper Library.

## Prerequisites

To use the project, you'll need the following:

- [Composer][composer] installed globally
- [PHP][php] 8.3
- Some command-line/terminal experience

## What is the project about?

The project isn't designed to be used, rather as an examination of how you could add tests to your PHP applications when you integrate against SendGrid (specifically, sending emails) using [SendGrid's PHP Helper Library][sendgrid-php-helper-library].

Tag 1.0.0 of the application is a small web-based API using [Mini Mezzio][mini-mezzio] to simplify its creation.
It uses a slightly refactored version of [the PHP sample code from the SendGrid docs][send-email-sendgrid-docs] that shows how to send an email.

## Getting Started

If you do want to use the project, clone the project to your development machine (wherever you store PHP projects), change into the new project directory, and install the PHP dependencies, by running the commands below:

```bash
git clone git@github.com:settermjd/sendgrid-email-testing.git
cd sendgrid-email-testing
composer install
```

Then, copy _.env.example_ as _.env_, [create a SendGrid API key][sendgrid-create-api-key], and replace `<SENDGRID_API_KEY>` with the key.

[composer]: https://getcomposer.org
[mini-mezzio]: https://github.com/asgrim/mini-mezzio
[php]: https://php.net
[sendgrid-send-email-response-documentation]: https://www.twilio.com/docs/sendgrid/api-reference/how-to-use-the-sendgrid-v3-api/responses
[send-email-sendgrid-docs]: https://www.twilio.com/docs/sendgrid/for-developers/sending-email/quickstart-php#complete-code-block
[sendgrid-create-api-key]: https://www.twilio.com/docs/sendgrid/ui/account-and-settings/api-keys#creating-an-api-key
[sendgrid-php-helper-library]: https://github.com/sendgrid/sendgrid-php
