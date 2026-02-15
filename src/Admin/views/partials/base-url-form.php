<?php
/**
 * @var string $saved_base_url
 * @var bool $has_base_url
 * @var string $delete_confirm
 */
?>
<h3 class="taler-settings-subheading"><?php echo esc_html__('Base URL', 'taler-payments'); ?></h3>
<form id="taler-baseurl-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
    <?php settings_fields('taler_baseurl_group'); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="taler-base-url"><?php echo esc_html__('Taler Base URL *', 'taler-payments'); ?></label></th>
            <td>
                <input
                    type="text"
                    id="taler-base-url"
                    name="taler_options[taler_base_url]"
                    value="<?php echo esc_attr($saved_base_url); ?>"
                    class="regular-text ltr"
                    placeholder="https://backend.demo.taler.net/instances/sandbox"
                    required
                />
                <p class="description">
                    <?php echo wp_kses_post(__(
                        'Important: the Base URL must include the instance path (e.g. /instances/<instance-id>) and must start with https://.<br>Example: https://backend.demo.taler.net/instances/sandbox',
                        'taler-payments'
                    )); ?>
                </p>
            </td>
        </tr>
    </table>
    <div class="taler-form-actions">
        <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'taler-payments'); ?></button>
        <?php if ($has_base_url) : ?>
            <button
                type="submit"
                name="taler_baseurl_delete"
                value="1"
                class="button taler-delete-button"
                formnovalidate
                onclick="return confirm('<?php echo esc_attr($delete_confirm); ?>');"
            ><?php echo esc_html__('Delete', 'taler-payments'); ?></button>
        <?php endif; ?>
    </div>
</form>

