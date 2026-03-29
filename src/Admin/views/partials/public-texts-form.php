<?php

use TalerPayments\Public\Config\PublicUiTexts;

/**
 * @var string $thank_you_message
 * @var string $pay_button_cta
 * @var string $check_status_button_text
 * @var string $check_status_hint
 * @var bool $has_public_text_overrides
 * @var string $reset_public_texts_confirm
 */

if (!defined('ABSPATH')){
    exit;
}
?>
<h3 class="taler-settings-subheading"><?php echo esc_html__('Public Text Customization', 'mirrorps-payments-for-gnu-taler'); ?></h3>
<form id="taler-public-texts-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
    <?php settings_fields('taler_public_texts_group'); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="taler-thank-you-message"><?php echo esc_html__('Thank you message', 'mirrorps-payments-for-gnu-taler'); ?></label></th>
            <td>
                <input
                    type="text"
                    id="taler-thank-you-message"
                    name="taler_options[<?php echo esc_attr(PublicUiTexts::OPTION_THANK_YOU_MESSAGE); ?>]"
                    value="<?php echo esc_attr($thank_you_message); ?>"
                    class="regular-text"
                    placeholder="<?php echo esc_attr(PublicUiTexts::DEFAULT_THANK_YOU_MESSAGE); ?>"
                />
                <p class="description"><?php echo esc_html__('Shown after a successful payment. Leave empty to use the default text.', 'mirrorps-payments-for-gnu-taler'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="taler-pay-button-cta"><?php echo esc_html__('Payment button CTA', 'mirrorps-payments-for-gnu-taler'); ?></label></th>
            <td>
                <input
                    type="text"
                    id="taler-pay-button-cta"
                    name="taler_options[<?php echo esc_attr(PublicUiTexts::OPTION_PAY_BUTTON_CTA); ?>]"
                    value="<?php echo esc_attr($pay_button_cta); ?>"
                    class="regular-text"
                    placeholder="<?php echo esc_attr(PublicUiTexts::DEFAULT_PAY_BUTTON_CTA); ?>"
                />
                <p class="description"><?php echo esc_html__('Shown on the main payment button in the modal. Leave empty to use the default text.', 'mirrorps-payments-for-gnu-taler'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="taler-check-status-button-text"><?php echo esc_html__('Check payment status button text', 'mirrorps-payments-for-gnu-taler'); ?></label></th>
            <td>
                <input
                    type="text"
                    id="taler-check-status-button-text"
                    name="taler_options[<?php echo esc_attr(PublicUiTexts::OPTION_CHECK_STATUS_BUTTON); ?>]"
                    value="<?php echo esc_attr($check_status_button_text); ?>"
                    class="regular-text"
                    placeholder="<?php echo esc_attr(PublicUiTexts::DEFAULT_CHECK_STATUS_BUTTON); ?>"
                />
                <p class="description"><?php echo esc_html__('Shown on the button used to refresh payment status. Leave empty to use the default text.', 'mirrorps-payments-for-gnu-taler'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="taler-check-status-hint"><?php echo esc_html__('Check payment status hint', 'mirrorps-payments-for-gnu-taler'); ?></label></th>
            <td>
                <input
                    type="text"
                    id="taler-check-status-hint"
                    name="taler_options[<?php echo esc_attr(PublicUiTexts::OPTION_CHECK_STATUS_HINT); ?>]"
                    value="<?php echo esc_attr($check_status_hint); ?>"
                    class="regular-text"
                    placeholder="<?php echo esc_attr(PublicUiTexts::DEFAULT_CHECK_STATUS_HINT); ?>"
                />
                <p class="description"><?php echo esc_html__('Hint shown above the status button. Leave empty to use the default text.', 'mirrorps-payments-for-gnu-taler'); ?></p>
            </td>
        </tr>
    </table>
    <div class="taler-form-actions">
        <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'mirrorps-payments-for-gnu-taler'); ?></button>
        <?php if ($has_public_text_overrides) : ?>
            <button
                type="submit"
                name="taler_public_texts_reset"
                value="1"
                class="button"
                formnovalidate
                onclick="return confirm('<?php echo esc_attr($reset_public_texts_confirm); ?>');"
            ><?php echo esc_html__('Reset to defaults', 'mirrorps-payments-for-gnu-taler'); ?></button>
        <?php endif; ?>
    </div>
</form>
