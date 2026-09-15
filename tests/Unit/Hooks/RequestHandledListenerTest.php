<?php

namespace Tests\Unit\Hooks;

use Heritage\Foundation\Http\Events\RequestHandled;
use Heritage\Http\Request;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\Hooks\RequestHandledListener;
use RuntimeException;
use Tests\TestCase;

use function response;

class RequestHandledListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $event = new RequestHandled(Request::create('/tests'), response(''));

        $listener = new RequestHandledListener($this->core);
        $listener($event);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
