<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Analytics\Token;
use Shopware\Core\Framework\Analytics\TokenService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[CoversClass(TokenService::class)]
class TokenServiceTest extends TestCase
{
    private const GATEWAY_URL = 'https://analytics.example.com';

    private SystemConfigService&MockObject $systemConfigService;

    private HttpClientInterface&MockObject $httpClient;

    private LoggerInterface&MockObject $logger;

    private TokenService $tokenService;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->tokenService = new TokenService(
            $this->systemConfigService,
            $this->httpClient,
            $this->logger,
            self::GATEWAY_URL
        );
    }

    public function testGenerateReturnsTokenOnSuccess(): void
    {
        $expiresAt = time() + 3600;
        $referer = 'https://shop.example.com';

        $this->systemConfigService
            ->method('get')
            ->with('core.analytics.secret')
            ->willReturn('existing-secret');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $expectedToken = 'test-token';
        $response->method('toArray')->willReturn([
            'token' => $expectedToken,
            'expires_at' => $expiresAt,
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', self::GATEWAY_URL . '/token', [
                'headers' => [
                    'X-Client-Secret' => 'existing-secret',
                    'Referer' => $referer,
                ],
            ])
            ->willReturn($response);

        $token = $this->tokenService->generate($referer);

        static::assertInstanceOf(Token::class, $token);
        static::assertSame($expectedToken, $token->token);
        static::assertSame($expiresAt, $token->expiresAt->getTimestamp());
    }

    public function testGenerateCreatesSecretWhenNoneExists(): void
    {
        $expiresAt = time() + 3600;
        $referer = 'https://shop.example.com';

        $this->systemConfigService
            ->method('get')
            ->with('core.analytics.secret')
            ->willReturn(null);

        $this->systemConfigService
            ->expects($this->once())
            ->method('set')
            ->with('core.analytics.secret', static::callback(fn ($value) => \is_string($value)));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('toArray')->willReturn([
            'token' => 'test-token',
            'expires_at' => $expiresAt,
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $token = $this->tokenService->generate($referer);

        static::assertInstanceOf(Token::class, $token);
    }

    public function testGenerateRotatesSecretOnForbidden(): void
    {
        $expiresAt = time() + 3600;
        $referer = 'https://shop.example.com';

        $this->systemConfigService
            ->method('get')
            ->with('core.analytics.secret')
            ->willReturn('old-secret');

        $this->systemConfigService
            ->expects($this->once())
            ->method('set')
            ->with('core.analytics.secret', static::callback(fn ($value) => \is_string($value)));

        $forbiddenResponse = $this->createMock(ResponseInterface::class);
        $forbiddenResponse->method('getStatusCode')->willReturn(Response::HTTP_FORBIDDEN);

        $successResponse = $this->createMock(ResponseInterface::class);
        $successResponse->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $newToken = 'new-token';
        $successResponse->method('toArray')->willReturn([
            'token' => $newToken,
            'expires_at' => $expiresAt,
        ]);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($forbiddenResponse, $successResponse);

        $token = $this->tokenService->generate($referer);

        static::assertInstanceOf(Token::class, $token);
        static::assertSame($newToken, $token->token);
    }

    public function testGenerateReturnsNullAndLogsOnNonOkResponse(): void
    {
        $this->systemConfigService
            ->method('get')
            ->with('core.analytics.secret')
            ->willReturn('existing-secret');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->httpClient->method('request')->willReturn($response);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Analytics token request failed', [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'gatewayUrl' => self::GATEWAY_URL,
            ]);

        static::assertNull($this->tokenService->generate('https://shop.example.com'));
    }

    public function testGenerateReturnsNullAndLogsOnTransportException(): void
    {
        $this->systemConfigService
            ->method('get')
            ->with('core.analytics.secret')
            ->willReturn('existing-secret');

        $exception = new class extends \Exception implements TransportExceptionInterface {};

        $this->httpClient
            ->method('request')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Analytics gateway is unreachable', [
                'exception' => $exception,
                'gatewayUrl' => self::GATEWAY_URL,
            ]);

        static::assertNull($this->tokenService->generate('https://shop.example.com'));
    }

    public function testGenerateReturnsNullWhenSecretIsInvalidType(): void
    {
        $this->systemConfigService
            ->method('get')
            ->with('core.analytics.secret')
            ->willReturn(['invalid' => 'type']);

        $this->httpClient->expects($this->never())->method('request');

        static::assertNull($this->tokenService->generate('https://shop.example.com'));
    }

    /**
     * @param array<string, mixed> $responseData
     */
    #[DataProvider('invalidResponseDataProvider')]
    public function testGenerateReturnsNullOnInvalidResponseData(array $responseData): void
    {
        $this->systemConfigService
            ->method('get')
            ->with('core.analytics.secret')
            ->willReturn('existing-secret');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('toArray')->willReturn($responseData);

        $this->httpClient->method('request')->willReturn($response);

        static::assertNull($this->tokenService->generate('https://shop.example.com'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidResponseDataProvider(): iterable
    {
        yield 'missing token' => [['expires_at' => time() + 3600]];
        yield 'missing expires_at' => [['token' => 'test-token']];
        yield 'token is not string' => [['token' => 12345, 'expires_at' => time() + 3600]];
        yield 'expires_at is not int' => [['token' => 'test-token', 'expires_at' => 'not-an-int']];
    }
}
