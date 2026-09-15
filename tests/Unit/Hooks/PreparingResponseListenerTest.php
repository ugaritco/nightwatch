<?php

namespace Tests\Unit\Hooks;

use Heritage\Http\Request;
use Heritage\Routing\Events\PreparingResponse;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\Hooks\PreparingResponseListener;
use RuntimeException;
use Tests\TestCase;

use function response;

class PreparingResponseListenerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Action;

        $event = new PreparingResponse(Request::create('/tests'), response(''));

        $listener = new PreparingResponseListener($this->core);
        $listener($event);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
