<?php

namespace Tests\Unit\Hooks;

use Heritage\Console\Events\ScribeStarting;
use Heritage\Foundation\Application;
use Ugarit\Nightwatch\Hooks\ScribeStartingListener;
use Tests\TestCase;

use function version_compare;

class ScribeStartingHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $this->markTestSkippedWhen(version_compare(Application::VERSION, '12.0.0', '<'), <<<'MESSAGE'
            This test only fails when there are type declations which where introduced in 12.x
            MESSAGE);
        $event = new class extends ScribeStarting
        {
            public function __construct()
            {
                //
            }
        };

        $listener = new ScribeStartingListener($this->core);
        $listener($event);

        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
