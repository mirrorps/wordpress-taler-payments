<?php
namespace TalerPayments\Public;

use TalerPayments\Public\DTO\CreateOrderRequest;
use TalerPayments\Public\DTO\InvalidAmountException;
use TalerPayments\Public\Input\InputInterface;
use TalerPayments\Public\Response\ResponseMessages;
use TalerPayments\Public\Response\JsonResponder;
use TalerPayments\Public\Security\RequestSecurityInterface;
use TalerPayments\Public\Validation\AmountValidatorInterface;
use TalerPayments\Services\OrderServiceInterface;

/**
 * Handles public AJAX order creation endpoint.
 */
final class AjaxOrderController
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
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
            return;
        }

        if (!$this->orderService->allowOrderCreateRequest()) {
            $this->responder->error(ResponseMessages::tooManyRequests(), 429);
            return;
        }

        $nonce = (string) wp_unslash($this->input->get('_ajax_nonce', ''));
        if (!$this->requestSecurity->isValidNonce('taler_wp_create_order', $nonce)) {
            $this->responder->error(ResponseMessages::invalidNonce(), 403);
            return;
        }

        try {
            $request = CreateOrderRequest::fromInput($this->input, $this->amountValidator);
        } catch (InvalidAmountException $e) {
            $this->responder->error(ResponseMessages::invalidAmountFormat(), 400);
            return;
        }

        try {
            $created = $this->orderService->createOrder($request->amount(), $request->summary());

            if ($created === null) {
                $this->responder->error(ResponseMessages::missingPayUri(), 502);
                return;
            }

            $this->responder->success([
                'order_id' => $created->orderId(),
                'taler_pay_uri' => $created->talerPayUri(),
            ]);
            return;
        } catch (\Taler\Exception\TalerException $e) {
            $this->responder->debugLog('mirrorps-gnu-taler-payments: taler exception', $e);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->responder->error($e->getMessage(), 502);
                return;
            }

            $this->responder->error(ResponseMessages::temporarilyUnavailable(), 502);
            return;
        } catch (\Throwable $e) {
            $this->responder->debugLog('mirrorps-gnu-taler-payments: runtime error', $e);

            $this->responder->error(ResponseMessages::runtimeError(), 500);
            return;
        }
    }
}
