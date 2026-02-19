<?php
namespace TalerPayments\Public;

use TalerPayments\Public\DTO\CreateOrderRequest;
use TalerPayments\Public\DTO\InvalidAmountException;
use TalerPayments\Public\Input\InputInterface;
use TalerPayments\Public\Response\ResponseMessages;
use TalerPayments\Public\Response\JsonResponder;
use TalerPayments\Public\Security\RequestSecurityInterface;
use TalerPayments\Public\Validation\AmountValidatorInterface;

/**
 * Handles public AJAX order creation endpoint.
 */
final class AjaxOrderController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly AmountValidatorInterface $amountValidator,
        private readonly InputInterface $input,
        private readonly RequestSecurityInterface $requestSecurity,
        private readonly JsonResponder $responder = new JsonResponder(),
    ) {
    }

    public function handle(): void
    {
        if (!$this->requestSecurity->isPostRequest()) {
            $this->responder->error(ResponseMessages::methodNotAllowed(), 405);
        }

        if (!$this->orderService->allowOrderCreateRequest()) {
            $this->responder->error(ResponseMessages::tooManyRequests(), 429);
        }

        $nonce = (string) wp_unslash($this->input->get('_ajax_nonce', ''));
        if (!$this->requestSecurity->isValidNonce('taler_wp_create_order', $nonce)) {
            $this->responder->error(ResponseMessages::invalidNonce(), 403);
        }

        try {
            $request = CreateOrderRequest::fromInput($this->input, $this->amountValidator);
        } catch (InvalidAmountException $e) {
            $this->responder->error(ResponseMessages::invalidAmountFormat(), 400);
        }

        try {
            $payUri = $this->orderService->createOrderPayUri($request->amount(), $request->summary());

            if ($payUri === null) {
                $this->responder->error(ResponseMessages::missingPayUri(), 502);
            }

            $this->responder->success(['taler_pay_uri' => $payUri]);
        } catch (\Taler\Exception\TalerException $e) {
            $this->responder->debugLog('taler-payments: taler exception', $e);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->responder->error($e->getMessage(), 502);
            }

            $this->responder->error(ResponseMessages::temporarilyUnavailable(), 502);
        } catch (\Throwable $e) {
            $this->responder->debugLog('taler-payments: runtime error', $e);

            $this->responder->error(ResponseMessages::runtimeError(), 500);
        }
    }
}
