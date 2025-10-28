<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Analytics\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Analytics\Api\AnalyticsController;
use Shopware\Core\Framework\Analytics\Token;
use Shopware\Core\Framework\Analytics\TokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AnalyticsController::class)]
class AnalyticsControllerTest extends TestCase
{
    private TokenService&MockObject $tokenService;

    private AnalyticsController $controller;

    protected function setUp(): void
    {
        $this->tokenService = $this->createMock(TokenService::class);
        $this->controller = new AnalyticsController($this->tokenService);
    }

    public function testTokenReturnsJsonResponseOnSuccess(): void
    {
        $referer = 'https://shop.example.com';
        $token = new Token('test-token', new \DateTimeImmutable('@' . (time() + 3600)));

        $this->tokenService
            ->expects($this->once())
            ->method('generate')
            ->with($referer)
            ->willReturn($token);

        $request = new Request();
        $request->headers->set('referer', $referer);

        $response = $this->controller->token($request);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(json_encode($token), $response->getContent());
    }

    #[DataProvider('missingRefererProvider')]
    public function testTokenReturnsServiceUnavailableWhenRefererMissing(?string $referer): void
    {
        $this->tokenService->expects($this->never())->method('generate');

        $request = new Request();
        if ($referer !== null) {
            $request->headers->set('referer', $referer);
        }

        $response = $this->controller->token($request);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function missingRefererProvider(): iterable
    {
        yield 'null referer' => [null];
        yield 'empty referer' => [''];
    }

    public function testTokenReturnsServiceUnavailableWhenTokenServiceReturnsNull(): void
    {
        $referer = 'https://shop.example.com';

        $this->tokenService
            ->expects($this->once())
            ->method('generate')
            ->with($referer)
            ->willReturn(null);

        $request = new Request();
        $request->headers->set('referer', $referer);

        $response = $this->controller->token($request);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }
}
