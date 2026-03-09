# Taler Payments for WordPress

Developer-focused documentation for the GNU Taler WordPress plugin.

This plugin adds a shortcode-driven payment UI that creates Merchant Backend orders on demand, opens the wallet with a `taler://` URI, and supports QR-based mobile payment.

## Admin quick start (self-hosted)

If you only need to use the plugin on your own site:

1. Copy plugin to `wp-content/plugins/taler-payments`.
2. Run `composer install --no-dev` inside the plugin directory.
3. Activate **Taler Payments** in WordPress.
4. Open **Settings -> Taler Payments**.
5. Save:
   - Base URL (must include `/instances/<instance-id>` and use `https://`)
   - Either Access Token or Username/Password/Instance ID
6. Add shortcode to a page:
   - `[taler_pay_button amount="KUDOS:1.00" summary="Donation"]`

For user-facing documentation, use `readme.txt` (WordPress.org-oriented).

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Composer
- Reachable GNU Taler Merchant Backend over HTTPS

## Core behavior

- Shortcode: `[taler_pay_button]`
- Order is created only after user interaction (button click)
- Modal flow includes:
  - amount + summary preview
  - wallet deep-link (`taler://...`)
  - QR code generation for mobile wallets
  - manual "Check payment status" action
- Public page support adds `<meta name="taler-support" content="uri,api,hijack">` when needed

## Configuration details

Settings are stored in WordPress option `taler_options`.

### Base URL

- Key: `taler_base_url`
- Must be `https://`
- Must include instance path (example: `https://backend.demo.taler.net/instances/sandbox`)

### Authentication

Two auth methods are supported:

- Access token
  - Key: `taler_token` (stored encrypted)
  - Token auth has priority when both methods are present
- Username/password/instance
  - Keys: `ext_username`, `ext_password` (encrypted), `taler_instance`

Runtime fallback behavior:

- If token-auth order operations receive `401`, the plugin retries once using configured username/password credentials.

### Public text overrides

Optional text keys:

- `public_thank_you_message`
- `public_pay_button_cta`
- `public_check_status_button_text`
- `public_check_status_hint`

## Public endpoints and protections

AJAX actions:

- `taler_wp_create_order`
- `taler_wp_check_order_status`

Protections:

- WordPress nonce checks on both actions
- Per-request throttling with transients
- Generic public errors (detailed backend messages only with `WP_DEBUG`)

Rate-limit filters:

- `taler_wp_rate_limit_window_seconds`
- `taler_wp_rate_limit_max_requests`
- `taler_wp_status_rate_limit_window_seconds`
- `taler_wp_status_rate_limit_max_requests`

## Developer setup

Install all dependencies (including dev):

- `composer install`

Run tests:

- `composer test`

## Project structure

- `taler-payments.php` - plugin bootstrap and protocol allowance
- `src/Admin` - settings page and form rendering
- `src/Public` - shortcode presentation, modal wiring, AJAX controllers
- `src/Services` - merchant auth/config and order logic
- `src/Settings` - sanitization and options mapping
- `css` - frontend/admin CSS
- `js` - frontend JS and bundled third-party library
- `tests/Unit` - PHPUnit unit tests

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Privacy

The plugin does not create user profiles.

When users trigger payment actions, WordPress sends order-related requests to the configured Merchant Backend. Hosting and backend logs may include metadata such as IP addresses and request details.

## License and third-party components

- Plugin license: GPLv2 or later
- Bundled component: `js/davidshimjs-qrcodejs-04f46c6/qrcode.min.js` (MIT License, davidshimjs)

See:

- `js/davidshimjs-qrcodejs-04f46c6/LICENSE`
- `THIRD-PARTY-LICENSES.txt`
## Funding

This project is funded through [NGI TALER Fund](https://nlnet.nl/taler), a fund established by [NLnet](https://nlnet.nl) with financial support from the European Commission's [Next Generation Internet](https://ngi.eu) program. Learn more at the [NLnet project page](https://nlnet.nl/project/TalerPHP).

[<img src="https://nlnet.nl/logo/banner.png" alt="NLnet foundation logo" width="20%" />](https://nlnet.nl)
