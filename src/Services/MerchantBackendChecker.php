<?php
namespace TalerPayments\Services;

/**
 * Performs a lightweight merchant backend login/config check and surfaces settings notices.
 */
final class MerchantBackendChecker
{
    /** @var array<string,bool> */
    private array $ran = [];

    public function __construct(
        private readonly SettingsNotices $notices,
        ?MerchantAuthConfigurator $authConfigurator = null,
        ?Taler $taler = null,
    ) {
        $this->authConfigurator = $authConfigurator ?? new MerchantAuthConfigurator();
        $this->taler = $taler ?? new Taler();
    }

    private readonly MerchantAuthConfigurator $authConfigurator;
    private readonly Taler $taler;

    /**
     * Checks are only run when:
     * - base_url is set, AND
     * - (token is set) OR (username+password+instance are set)
     *
     * @param array<string, mixed> $options Merged taler_options (already saved shape)
     */
    public function testLogin(array $options, string $mode = MerchantAuthConfigurator::MODE_AUTO): bool
    {
        $context = $this->authConfigurator->buildLoginCheckContext($options, $mode);
        if ($context === null) {
            return true;
        }

        $factoryOptions = $context->factoryOptions;
        [$authLabel, $credentialHint] = $this->authUiText($context->authMethod);

        // Duplicate guard: prevent duplicate checks/notices per request.
        $runKey = $mode . '|' . md5(json_encode($factoryOptions->toArray()));
        if (isset($this->ran[$runKey])) {
            return true;
        }

        $this->ran[$runKey] = true;

        try {
            $report = $this->taler->createClient($factoryOptions)->configCheck();

            if (!is_array($report) || empty($report['ok'])) {
                $this->addFailureNotice($authLabel, $report);
                return false;
            }

            $this->notices->addOnce(
                'taler_options',
                'taler_backend_login_ok',
                sprintf(
                    __('Merchant backend login test successful (%s).', 'taler-payments'),
                    $authLabel
                ),
                'updated'
            );
            return true;
        } catch (\InvalidArgumentException $e) {
            $this->notices->addOnce(
                'taler_options',
                'taler_backend_login_invalid',
                __('Merchant backend login test failed: invalid configuration (is this a Taler Merchant Backend base URL?).', 'taler-payments'),
                'error'
            );
            return false;
        } catch (\Throwable $e) {
            // Avoid leaking sensitive info; keep message generic.
            $this->notices->addOnce(
                'taler_options',
                'taler_backend_login_exception',
                sprintf(
                    /* translators: 1: auth method label, 2: credentials hint */
                    __('Merchant backend login test failed (error: %1$s). Please verify Base URL and %2$s.', 'taler-payments'),
                    (string) $authLabel,
                    $credentialHint
                ),
                'error'
            );
            return false;
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function authUiText(string $authMethod): array
    {
        if ($authMethod === MerchantAuthConfigurator::AUTH_METHOD_TOKEN) {
            return [
                __('Access Token', 'taler-payments'),
                __('access token', 'taler-payments'),
            ];
        }

        if ($authMethod === MerchantAuthConfigurator::AUTH_METHOD_USERPASS) {
            return [
                __('Username & Password', 'taler-payments'),
                __('username, password, and instance ID', 'taler-payments'),
            ];
        }

        return [
            __('Credentials', 'taler-payments'),
            __('credentials', 'taler-payments'),
        ];
    }

    /**
     * @param array<string,mixed>|mixed $report
     */
    private function addFailureNotice(string $authLabel, $report): void
    {
        [$step, $status, $error] = $this->extractFailureHint($report);
        $statusText = is_int($status) ? (' (HTTP ' . $status . ')') : '';
        $errorText = is_string($error) && $error !== '' ? (' ' . $error) : '';

        $this->notices->addOnce(
            'taler_options',
            'taler_backend_login_failed',
            sprintf(
                /* 1: auth method label, 2: failing step, 3: optional status text, 4: optional error slug */
                __('Merchant backend login test failed (error: %1$s): %2$s%3$s.%4$s', 'taler-payments'),
                $authLabel,
                $step,
                $statusText,
                $errorText
            ),
            'error'
        );
    }

    /**
     * @param array<string,mixed>|mixed $report
     * @return array{0:string,1:int|null,2:mixed}
     */
    private function extractFailureHint($report): array
    {
        $step = 'auth';
        $status = null;
        $error = null;

        if (!is_array($report)) {
            return [$step, $status, $error];
        }

        if (isset($report['config']) && is_array($report['config']) && empty($report['config']['ok'])) {
            return [
                'config',
                isset($report['config']['status']) && is_int($report['config']['status']) ? $report['config']['status'] : null,
                $report['config']['error'] ?? null,
            ];
        }

        if (isset($report['instance']) && is_array($report['instance']) && empty($report['instance']['ok'])) {
            return [
                'instance',
                isset($report['instance']['status']) && is_int($report['instance']['status']) ? $report['instance']['status'] : null,
                $report['instance']['error'] ?? null,
            ];
        }

        if (isset($report['auth']) && is_array($report['auth']) && empty($report['auth']['ok'])) {
            return [
                'auth',
                isset($report['auth']['status']) && is_int($report['auth']['status']) ? $report['auth']['status'] : null,
                $report['auth']['error'] ?? null,
            ];
        }

        return [$step, $status, $error];
    }
}

