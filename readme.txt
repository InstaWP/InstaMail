=== InstaWP Email Logs ===
Contributors: instawp
Tags: email, logs, debugging, staging
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intercept and log emails sent from your InstaWP Staging WordPress site.

== Description ==

InstaWP Email Logs is a powerful tool for developers and site administrators working with InstaWP staging environments. This plugin intercepts all outgoing emails from your WordPress site and logs them for easy viewing and debugging.

Key features:

* Intercept and log all outgoing emails
* User-friendly admin interface to view and manage logged emails
* Secure public access with password protection
* One-time password (OTP) generation for temporary access
* Pagination for easy navigation through email logs
* Option to clear all logs
* Responsive design using Tailwind CSS

Perfect for staging environments where you want to prevent emails from being sent to real users while still being able to debug and test email functionality.

== Installation ==

1. Upload the `instawp-email-logs` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the email logs from the 'Tools' menu in the WordPress admin area

== Frequently Asked Questions ==

= Can I use this plugin on a live site? =

While you can use this plugin on a live site, it's primarily designed for use in staging environments. Be cautious about storing sensitive email content on production sites.

= How do I access the email logs? =

Navigate to 'Tools' > 'InstaWP Email Logs' in the WordPress admin area to view and manage your email logs.

= Is there a way to provide temporary access to the logs? =

Yes, you can generate a one-time password (OTP) for temporary access to the email logs.

== Screenshots ==

1. Email logs admin interface
2. Public access settings

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of InstaWP Email Logs.