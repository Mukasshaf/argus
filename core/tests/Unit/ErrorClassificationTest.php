<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Channels\Exceptions\PermanentChannelException;
use App\Channels\Exceptions\TransientChannelException;
use App\Channels\Exceptions\UnmappedCodeException;
use PHPUnit\Framework\TestCase;

class ErrorClassificationTest extends TestCase
{
    public function test_transient_error_stores_retry_after(): void
    {
        $transient = new TransientChannelException('Too Many Requests', 429, 60);

        $this->assertSame(429, $transient->getCode());
        $this->assertSame(60, $transient->retryAfter);
        $this->assertInstanceOf(\Exception::class, $transient);
    }

    public function test_permanent_exceptions_hierarchy(): void
    {
        $permanent = new PermanentChannelException('Bad Request Schema', 400);
        $unmapped = new UnmappedCodeException(null, 'Room code XYZ unmapped');

        $this->assertInstanceOf(PermanentChannelException::class, $unmapped);
        $this->assertNotInstanceOf(TransientChannelException::class, $permanent);
        $this->assertNotInstanceOf(TransientChannelException::class, $unmapped);
    }
}
