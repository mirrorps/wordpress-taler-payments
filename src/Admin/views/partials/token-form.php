<?php
/**
 * @var string $token_value
 * @var bool $has_token
 * @var string $delete_confirm
 */
?>
<h3 class="taler-settings-subheading"><?php echo esc_html__('Access Token', 'taler-payments'); ?></h3>
<form id="taler-token-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
    <?php settings_fields('taler_token_group'); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="taler-token"><?php echo esc_html__('Access Token *', 'taler-payments'); ?></label></th>
            <td>
                <input
                    type="password"
                    id="taler-token"
                    name="taler_options[taler_token]"
                    value="<?php echo esc_attr($token_value); ?>"
                    <?php echo $has_token ? 'placeholder="' . esc_attr__('(stored)', 'taler-payments') . '"' : ''; ?>
                    class="regular-text ltr"
                    autocomplete="new-password"
                    required
                />
                <p class="description"><?php echo wp_kses_post(__('Access token for the Taler Merchant Backend.<br>Save the full value as used in the HTTP <code>Authorization</code> header (including the prefix). Example: <code>Bearer secret-token:sandbox</code>.<br>The access token will be first <strong>encrypted</strong> and then stored in the database.', 'taler-payments')); ?></p>
            </td>
        </tr>
    </table>
    <div class="taler-form-actions">
        <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'taler-payments'); ?></button>
        <?php if ($has_token) : ?>
            <button
                type="submit"
                name="taler_token_delete"
                value="1"
                class="button taler-delete-button"
                formnovalidate
                onclick="return confirm('<?php echo esc_attr($delete_confirm); ?>');"
            ><?php echo esc_html__('Delete', 'taler-payments'); ?></button>
        <?php endif; ?>
    </div>
</form>

