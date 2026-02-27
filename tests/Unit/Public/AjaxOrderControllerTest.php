<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Public;

use PHPUnit\Framework\TestCase;
use TalerPayments\Public\AjaxOrderController;
use TalerPayments\Public\Input\ArrayInput;
use TalerPayments\Public\Input\InputInterface;
use TalerPayments\Public\Response\JsonResponder;
use TalerPayments\Public\Response\ResponseMessages;
use TalerPayments\Public\Response\WordPressResponseSpyState;
use TalerPayments\Public\Security\RequestSecurityInterface;
use TalerPayments\Public\Validation\AmountValidator;
use TalerPayments\Public\Validation\AmountValidatorInterface;
use TalerPayments\Services\OrderServiceInterface;

final class AjaxOrderControllerTest extends TestCase
{
    protected function setUp(): void
    {
        WordPressResponseSpyState::reset();
    }

    public function testHandleEmitsMethodNotAllowedErrorForNonPostRequests(): void
    {
        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->expects(self::never())->method('allowOrderCreateRequest');

        $requestSecurity = $this->createMock(RequestSecurityInterface::class);
        $requestSecurity->method('isPostRequest')->willReturn(false);

        $controller = $this->controller(
            $orderService,
            $this->createStub(AmountValidatorInterface::class),
            new ArrayInput([]),
            $requestSecurity
        );

        $controller->handle();

        self::assertSame(ResponseMessages::methodNotAllowed(), WordPressResponseSpyState::$errors[0]['message']);
        self::assertSame(405, WordPressResponseSpyState::$errors[0]['status']);
    }

    public function testHandleEmitsInvalidNonceErrorForInvalidSecurityToken(): void
    {
        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->method('allowOrderCreateRequest')->willReturn(true);

        $requestSecurity = $this->createMock(RequestSecurityInterface::class);
        $requestSecurity->method('isPostRequest')->willReturn(true);
        $requestSecurity->method('isValidNonce')->with('taler_wp_create_order', 'bad')->willReturn(false);

        $controller = $this->controller(
            $orderService,
            new AmountValidator(),
            new ArrayInput(['_ajax_nonce' => 'bad']),
            $requestSecurity
        );

        $controller->handle();

        self::assertSame(ResponseMessages::invalidNonce(), WordPressResponseSpyState::$errors[0]['message']);
        self::assertSame(403, WordPressResponseSpyState::$errors[0]['status']);
    }

    public function testHandleEmitsInvalidAmountError(): void
    {
        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->method('allowOrderCreateRequest')->willReturn(true);
        $orderService->expects(self::never())->method('createOrder');

        $requestSecurity = $this->createMock(RequestSecurityInterface::class);
        $requestSecurity->method('isPostRequest')->willReturn(true);
        $requestSecurity->method('isValidNonce')->willReturn(true);

        $controller = $this->controller(
            $orderService,
            new AmountValidator(),
            new ArrayInput([
                '_ajax_nonce' => 'nonce',
                'amount' => 'invalid',
                'summary' => 'Support',
            ]),
            $requestSecurity
        );

        $controller->handle();

        self::assertSame(ResponseMessages::invalidAmountFormat(), WordPressResponseSpyState::$errors[0]['message']);
        self::assertSame(400, WordPressResponseSpyState::$errors[0]['status']);
    }

    private function controller(
        OrderServiceInterface $orderService,
        AmountValidatorInterface $amountValidator,
        InputInterface $input,
        RequestSecurityInterface $requestSecurity
    ): AjaxOrderController {
        return new AjaxOrderController(
            $orderService,
            $amountValidator,
            $input,
            $requestSecurity,
            new JsonResponder(),
        );
    }
}
