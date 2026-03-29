<?php
/**
 * @var array<string, mixed> $options
 * @var string $username
 * @var string $instance
 * @var string $password
 * @var string $token_value
 * @var bool $has_userpass
 * @var bool $has_token
 * @var string $saved_base_url
 * @var bool $has_base_url
 * @var string $thank_you_message
 * @var string $pay_button_cta
 * @var string $check_status_button_text
 * @var string $check_status_hint
 * @var bool $has_public_text_overrides
 * @var string $delete_confirm
 * @var string $reset_public_texts_confirm
 */

if (!defined('ABSPATH')){
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="taler-settings-group">
        <h2><?php echo esc_html__('Taler Merchant Backend', 'mirrorps-payments-for-gnu-taler'); ?></h2>

        <div class="notice notice-info inline">
            <p><?php
                echo wp_kses_post(
                    __('<strong>Security:</strong> Passwords and tokens are first <strong>encrypted</strong> and then stored in the database.', 'mirrorps-payments-for-gnu-taler')
                );
            ?></p>
        </div>

        <div class="notice notice-info inline">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: 1: "Taler Merchant Backend", 2: "Username & Password", 3: "Access Token" */
                        __('To access the %1$s provide either %2$s or an %3$s. If both are supplied, %3$s takes priority.', 'mirrorps-payments-for-gnu-taler'),
                        '<strong>' . esc_html__('Taler Merchant Backend', 'mirrorps-payments-for-gnu-taler') . '</strong>',
                        '<strong>' . esc_html__('Username & Password', 'mirrorps-payments-for-gnu-taler') . '</strong>',
                        '<strong>' . esc_html__('Access Token', 'mirrorps-payments-for-gnu-taler') . '</strong>'
                    )
                );
                ?>
            </p>
        </div>

        <hr class="taler-divider" />

        <?php include __DIR__ . '/partials/base-url-form.php'; ?>

        <hr class="taler-divider" />

        <?php include __DIR__ . '/partials/userpass-form.php'; ?>

        <hr class="taler-divider" />

        <?php include __DIR__ . '/partials/token-form.php'; ?>

        <hr class="taler-divider" />

        <?php include __DIR__ . '/partials/public-texts-form.php'; ?>

        <hr class="taler-divider" />
    </div>
</div>

