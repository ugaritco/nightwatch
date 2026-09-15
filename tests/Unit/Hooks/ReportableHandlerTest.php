<?php

namespace Tests\Unit\Hooks;

use Exception;
use Heritage\Foundation\Bootstrap\HandleExceptions;
use Ugarit\Nightwatch\Facades\Nightwatch;
use Ugarit\Nightwatch\Hooks\ReportableHandler;
use RuntimeException;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Tests\TestCase;

use function strlen;

class ReportableHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions): void {
            $unrecoverableExceptions[] = $e;
        });
        $thrownInExceptionSensor = false;
        $this->core->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor): void {
            $thrownInExceptionSensor = true;

            throw new RuntimeException('Whoops sensor!');
        };

        $exception = new RuntimeException('Whoops app!');

        $handler = new ReportableHandler($this->core);
        $handler($exception);

        $this->assertTrue($thrownInExceptionSensor);
        $this->assertCount(1, $unrecoverableExceptions);
        $this->assertSame('Whoops sensor!', $unrecoverableExceptions[0]->getMessage());
    }

    public function test_it_reserves_and_releases_memory(): void
    {
        $this->fakeIngest();

        // Test it reserves memory
        $handler = new ReportableHandler($this->core);
        $this->assertSame(32768, strlen($handler->reservedMemory));

        // Test it doesn't release it if Ugarit hasn't released its own
        $handler(new Exception('Test'));
        $this->assertSame(32768, strlen($handler->reservedMemory));

        // Test it does release it if Ugarit has released its own
        HandleExceptions::$reservedMemory = null;
        $handler(new FatalError('Test', 0, ['file' => __FILE__, 'line' => __LINE__], 0));
        $this->assertNull($handler->reservedMemory);
    }
}
