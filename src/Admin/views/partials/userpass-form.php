<?php
/**
 * @var string $instance
 * @var string $username
 * @var string $password
 * @var bool $has_userpass
 * @var string $delete_confirm
 */
?>
<h3 class="taler-settings-subheading"><?php echo esc_html__('Username & Password', 'taler-payments'); ?></h3>
<form id="taler-userpass-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
    <?php settings_fields('taler_userpass_group'); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="taler-instance"><?php echo esc_html__('Instance ID *', 'taler-payments'); ?></label></th>
            <td>
                <input
                    type="text"
                    id="taler-instance"
                    name="taler_options[taler_instance]"
                    value="<?php echo esc_attr($instance); ?>"
                    class="regular-text"
                    required
                />
                <p class="description">
                    <?php echo wp_kses_post(__(
                        'Required when authenticating with Username & Password<br>The instance ID specifies which Taler Merchant Backend instance to authenticate against.',
                        'taler-payments'
                    )); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="taler-ext-username"><?php echo esc_html__('Username *', 'taler-payments'); ?></label></th>
            <td>
                <input type="text" id="taler-ext-username" name="taler_options[ext_username]" value="<?php echo esc_attr($username); ?>" class="regular-text" required />
                <p class="description"><?php echo esc_html__('Username for the Taler Merchant Backend instance.', 'taler-payments'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="taler-ext-password"><?php echo esc_html__('Password *', 'taler-payments'); ?></label></th>
            <td>
                <input
                    type="password"
                    id="taler-ext-password"
                    name="taler_options[ext_password]"
                    value="<?php echo esc_attr($password); ?>"
                    <?php echo $has_userpass ? 'placeholder="' . esc_attr__('(stored)', 'taler-payments') . '"' : ''; ?>
                    class="regular-text ltr"
                    autocomplete="new-password"
                    <?php echo $has_userpass ? '' : 'required'; ?>
                />
                <p class="description">
                    <?php echo wp_kses_post(__(
                        'Password for the Taler Merchant Backend instance.<br>The password will be first <strong>encrypted</strong> and then stored in the database.',
                        'taler-payments'
                    )); ?>
                    <?php if ($has_userpass) : ?>
                        <?php echo ' ' . esc_html__('Leave blank to keep the stored password.', 'taler-payments'); ?>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
    </table>
    <div class="taler-form-actions">
        <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'taler-payments'); ?></button>
        <?php if ($has_userpass) : ?>
            <button
                type="submit"
                name="taler_userpass_delete"
                value="1"
                class="button taler-delete-button"
                formnovalidate
                onclick="return confirm('<?php echo esc_attr($delete_confirm); ?>');"
            ><?php echo esc_html__('Delete', 'taler-payments'); ?></button>
        <?php endif; ?>
    </div>
</form>

