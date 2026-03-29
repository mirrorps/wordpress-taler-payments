=== mirrorps Payments for GNU Taler ===
Contributors: mirrorps
Tags: gnu taler, payments, donations, ecommerce
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments and donations with GNU Taler via a simple shortcode and an in-page payment modal with QR code. This plugin is not affiliated with the GNU Taler project.

== Description ==

MirrorPS Payments for GNU Taler adds GNU Taler checkout to WordPress with a shortcode.

When a visitor clicks your payment button, the plugin:

- Creates a GNU Taler order only after the visitor clicks "Pay"
- Opens the GNU Taler wallet using a `taler://` payment URI
- Shows a QR code for mobile wallets

This makes it suitable for donations and simple payment flows on pages and posts.

== Installation ==

1. Upload the `mirrorps-payments-for-gnu-taler` folder to the `/wp-content/plugins/` directory (or upload a zip in WordPress admin).
2. Activate the plugin from the "Plugins" screen in WordPress.
3. Go to **Settings -> MirrorPS Payments for GNU Taler**.
4. Save your Merchant Backend settings:
   - Base URL (must start with `https://` and include `/instances/<instance-id>`)
   - Either Access Token, or Username + Password + Instance ID
5. Add `[taler_pay_button]` to a page or post.

== Usage ==

= Basic shortcode =

Add the shortcode to any post/page:

`[taler_pay_button amount="KUDOS:1.00" summary="Donation"]`

Parameters:

- `amount` - Amount in the form `CURRENCY:VALUE` (example: `EUR:5.00`)
- `summary` - Payment summary shown to the payer
- `text` - Text shown on the page button (default: `Pay with Taler`)

== Frequently Asked Questions ==

= Do visitors need the GNU Taler Wallet? =

Yes. For in-browser payment, the visitor needs the GNU Taler Wallet browser extension.  
The payment modal includes a wallet information link.

= Which credentials should I use: token or username/password? =

You can use either:

- Access Token, or
- Username + Password + Instance ID

If both are saved, the plugin prefers Access Token.

= What happens if payment is not confirmed immediately? =

The payer can click "Check payment status" in the modal after completing wallet steps.

= What data does this plugin send? =

When a visitor clicks the pay button, your site sends the configured order amount and summary to your configured GNU Taler Merchant Backend to create the order and retrieve payment status.

== Third-party licenses ==

This plugin bundles:

- QRCode.js (`js/davidshimjs-qrcodejs-04f46c6/qrcode.min.js`) under the MIT License (c) 2012 davidshimjs.

See:

- `js/davidshimjs-qrcodejs-04f46c6/LICENSE`
- `THIRD-PARTY-LICENSES.txt`

== Privacy Policy ==

This plugin itself does not collect user profile information.

When a visitor initiates a payment, your site contacts your configured GNU Taler Merchant Backend to create an order and check order status. Depending on your hosting and backend configuration, server logs may contain IP addresses and request metadata.

== Screenshots ==

1. Plugin settings page in WordPress admin.
2. Payment modal with wallet link and QR code.
3. Payment status check after wallet payment.

== Changelog ==

= 1.2.1 =
* Renamed plugin for WordPress.org trademark guidelines; not affiliated with GNU Taler.
* Register all settings with explicit sanitization callbacks.
* Remove WordPress.org directory icon assets from the distribution zip (upload via SVN after approval).

= 1.2.0 =
* Initial release.
