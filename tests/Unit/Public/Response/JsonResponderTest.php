<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Public\Response;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TalerPayments\Public\Response\JsonResponder;
use TalerPayments\Public\Response\WordPressResponseSpyState;

final class JsonResponderTest extends TestCase
{
    protected function setUp(): void
    {
        WordPressResponseSpyState::reset();
    }

    public function testSuccessSendsWordPressSuccessJson(): void
    {
        $responder = new JsonResponder();
        $responder->success(['id' => 'order-1']);

        self::assertSame(['id' => 'order-1'], WordPressResponseSpyState::$successPayload);
    }

    public function testErrorSendsWordPressErrorJsonWithStatus(): void
    {
        $responder = new JsonResponder();
        $responder->error('bad_request', 400);

        self::assertSame(['message' => 'bad_request'], WordPressResponseSpyState::$errorPayload);
        self::assertSame(400, WordPressResponseSpyState::$errorStatus);
    }

    public function testDebugLogWritesExpectedMessageWhenDebugLoggingEnabled(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG !== true) {
            self::markTestSkipped('WP_DEBUG is already defined as false.');
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG !== true) {
            self::markTestSkipped('WP_DEBUG_LOG is already defined as false.');
        }

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }

        $responder = new JsonResponder();
        $responder->debugLog('Create order failed', new RuntimeException('Boom', 42));

        self::assertSame(
            ['Create order failed: Boom (code: 42)'],
            WordPressResponseSpyState::$logs
        );
    }
}
