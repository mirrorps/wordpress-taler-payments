<?php
namespace TalerPayments\Public;

use TalerPayments\Public\DTO\CheckOrderStatusRequest;
use TalerPayments\Public\DTO\InvalidOrderIdException;
use TalerPayments\Public\Input\InputInterface;
use TalerPayments\Public\Response\JsonResponder;
use TalerPayments\Public\Response\ResponseMessages;
use TalerPayments\Public\Security\RequestSecurityInterface;
use TalerPayments\Services\OrderServiceInterface;

/**
 * Handles public AJAX order payment status checks.
 */
final class AjaxOrderStatusController
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
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

        if (!$this->orderService->allowOrderStatusRequest()) {
            $this->responder->error(ResponseMessages::tooManyRequests(), 429);
        }

        $nonce = (string) wp_unslash($this->input->get('_ajax_nonce', ''));
        if (!$this->requestSecurity->isValidNonce('taler_wp_check_order_status', $nonce)) {
            $this->responder->error(ResponseMessages::invalidNonce(), 403);
        }

        try {
            $request = CheckOrderStatusRequest::fromInput($this->input);
        } catch (InvalidOrderIdException $e) {
            $this->responder->error(ResponseMessages::invalidOrderId(), 400);
        }

        try {
            $isPaid = $this->orderService->isOrderPaid($request->orderId());
            $this->responder->success(['is_paid' => $isPaid]);
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
