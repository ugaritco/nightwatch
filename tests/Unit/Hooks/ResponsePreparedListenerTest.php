<?php

namespace Tests\Unit\Hooks;

use Heritage\Http\Request;
use Heritage\Routing\Events\ResponsePrepared;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\Hooks\ResponsePreparedListener;
use RuntimeException;
use Tests\TestCase;

use function response;

class ResponsePreparedListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Render;

        $event = new ResponsePrepared(Request::create('/tests'), response(''));

        $listener = new ResponsePreparedListener($this->core);
        $listener($event);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
