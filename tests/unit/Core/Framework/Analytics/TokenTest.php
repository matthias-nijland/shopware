<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Analytics\Token;

/**
 * @internal
 */
#[CoversClass(Token::class)]
class TokenTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $tokenString = 'test-token-string';
        $expiresAt = new \DateTimeImmutable('2025-12-31 23:59:59');

        $token = new Token($tokenString, $expiresAt);

        static::assertSame($tokenString, $token->token);
        static::assertSame($expiresAt, $token->expiresAt);
    }

    public function testJsonSerialize(): void
    {
        $tokenString = 'test-token-string';
        $expiresAt = new \DateTimeImmutable('@1735689599'); // 2025-12-31 23:59:59 UTC

        $token = new Token($tokenString, $expiresAt);

        static::assertSame([
            'token' => $tokenString,
            'expiresAt' => 1735689599,
        ], $token->jsonSerialize());
    }
}
