<?php
namespace TalerPayments\Settings;

use TalerPayments\Settings\DTO\SanitizeContext;
use TalerPayments\Services\MerchantBackendChecker;

/**
 * Orchestrates settings save flow (request context, sanitization, connectivity validation).
 */
final class SettingsSaveService
{
    public function __construct(
        private readonly Sanitizer $sanitizer,
        private readonly MerchantBackendChecker $checker,
    ) {
    }

    /**
     * @param mixed $input
     * @param array<string,mixed> $request
     * @param array<string,mixed> $currentOptions
     * @return array<string,mixed>
     */
    public function sanitizeForSave($input, array $request, array $currentOptions): array
    {
        $context = $this->buildContext($request);
        $result = $this->sanitizer->sanitize($input, $context, $currentOptions);
        $mode = $result->loginCheckMode();
        if (is_string($mode) && $mode !== '') {
            $candidate = $result->options();
            $isValid = $this->checker->testLogin($candidate, $mode);
            if (!$isValid) {
                return $currentOptions;
            }
        }

        return $result->options();
    }

    /**
     * @param array<string,mixed> $request
     */
    private function buildContext(array $request): SanitizeContext
    {
        $optionPage = isset($request['option_page']) ? sanitize_text_field(wp_unslash($request['option_page'])) : '';
        $deleteFlag = SettingsFormMap::deleteFlagForOptionPage($optionPage);
        $isDelete = $deleteFlag !== null && !empty($request[$deleteFlag]);

        return new SanitizeContext($optionPage, $isDelete);
    }
}
