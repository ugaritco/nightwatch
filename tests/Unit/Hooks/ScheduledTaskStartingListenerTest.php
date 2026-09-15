<?php

namespace Tests\Unit\Hooks;

use Heritage\Console\Events\ScheduledTaskStarting;
use Heritage\Console\Scheduling\Schedule;
use Ugarit\Nightwatch\Clock;
use Ugarit\Nightwatch\Hooks\ScheduledTaskStartingListener;
use RuntimeException;
use Tests\TestCase;

use function tap;

class ScheduledTaskStartingListenerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();
    }

    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInMicrotimeResolver = false;
        $this->core->clock = tap(new Clock, function ($clock) use (&$thrownInMicrotimeResolver): void {
            $clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver): void {
                $thrownInMicrotimeResolver = true;

                throw new RuntimeException('Whoops!');
            };
        });

        $event = new ScheduledTaskStarting($this->app[Schedule::class]->command('php scribe inspire'));

        $handler = new ScheduledTaskStartingListener($this->core);
        $handler($event);

        $this->assertTrue($thrownInMicrotimeResolver);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
