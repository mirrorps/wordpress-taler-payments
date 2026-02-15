=== Taler Payments ===
Contributors: mirrorps
Tags: taler, payments, donations, qr-code, ecommerce
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments and donations with GNU Taler via a simple shortcode and an in-page payment modal with QR code.

== Description ==

Taler Payments integrates the GNU Taler payment system into WordPress, allowing you to offer a payment button that:

- Creates a GNU Taler order only after the visitor clicks "Pay"
- Opens the Taler wallet (browser extension) using a `taler://` payment URI
- Shows a QR code for mobile wallets

### Usage (shortcode)

Add the shortcode to any post/page:

`[taler_pay_button amount="KUDOS:1.00" summary="Donation"]`

Parameters:

- `amount`: an amount in the form `CURRENCY:VALUE` (must be supported by your exchange)
- `summary`: order summary shown to the payer
- `text`: button call-to-action text (defaults to `Pay with Taler`)

### Configuration

Configure your Taler backend endpoint and API token using either environment variables or constants:

- `TALER_BASE_URL`
- `TALER_TOKEN`

== Installation ==

1. Upload the `taler-payments` folder to the `/wp-content/plugins/` directory (or upload a zip via the admin UI).
2. Install PHP dependencies (required):
   - Run `composer install --no-dev` inside the plugin directory so that `vendor/` exists.
3. Set `TALER_BASE_URL` and `TALER_TOKEN` (environment variables or `wp-config.php` constants).
4. Activate the plugin through the "Plugins" menu in WordPress.
5. Add `[taler_pay_button]` to a page/post.

== Frequently Asked Questions ==

= Does this plugin require the GNU Taler Wallet? =

Yes. To pay in the browser, the visitor needs the GNU Taler Wallet browser extension. The payment modal includes a link to wallet information.

= What data does this plugin send? =

When a visitor clicks the button, your WordPress site sends the configured `amount` and `summary` to your configured Taler backend to create an order and obtain the `taler://` payment URI.

== Third-party licenses ==

This plugin bundles:

- QRCode.js (`assets/davidshimjs-qrcodejs-04f46c6/qrcode.min.js`) under the MIT License (c) 2012 davidshimjs.

See:

- `assets/davidshimjs-qrcodejs-04f46c6/LICENSE`
- `THIRD-PARTY-LICENSES.txt`

== Privacy Policy ==

This plugin itself does not collect user profile information.

When a visitor initiates a payment, your site contacts your configured Taler backend endpoint to create an order (using the amount and summary shown in the payment modal). Depending on your hosting/logging setup and backend configuration, server logs may contain IP addresses and request metadata.

== Screenshots ==

1. Payment modal with pay link and QR code.

== Changelog ==

= 0.1.0 =
* Initial release.

