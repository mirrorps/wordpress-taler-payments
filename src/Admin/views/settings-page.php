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
 * @var string $delete_confirm
 */
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="taler-settings-group">
        <h2><?php echo esc_html__('Taler Merchant Backend', 'taler-payments'); ?></h2>

        <div class="notice notice-info inline">
            <p><?php
                echo wp_kses_post(sprintf(
                    __('<strong>Security:</strong> Passwords and tokens are first <strong>encrypted</strong> and then stored in the database.', 'taler-payments')
                ));
            ?></p>
        </div>

        <div class="notice notice-info inline">
            <p><?php echo wp_kses_post(sprintf(
                __('To access the <strong>Taler Merchant Backend</strong> provide either <strong>%1$s</strong> or an <strong>%2$s</strong>. If both are supplied, the <strong>%2$s</strong> takes priority.', 'taler-payments'),
                esc_html__('Username & Password', 'taler-payments'),
                esc_html__('Access Token', 'taler-payments')
            )); ?>
            </p>
        </div>

        <hr class="taler-divider" />

        <?php include __DIR__ . '/partials/base-url-form.php'; ?>

        <hr class="taler-divider" />

        <?php include __DIR__ . '/partials/userpass-form.php'; ?>

        <hr class="taler-divider" />

        <?php include __DIR__ . '/partials/token-form.php'; ?>

        <hr class="taler-divider" />
    </div>
</div>

