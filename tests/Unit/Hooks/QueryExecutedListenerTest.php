<?php

namespace Tests\Unit\Hooks;

use Heritage\Database\Events\QueryExecuted;
use Heritage\Support\Facades\DB;
use Ugarit\Nightwatch\Hooks\QueryExecutedListener;
use RuntimeException;
use Tests\TestCase;

class QueryExecutedListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInQuerySensor = false;
        $this->core->sensor->querySensor = function () use (&$thrownInQuerySensor): void {
            $thrownInQuerySensor = true;

            throw new RuntimeException('Whoops!');
        };

        $event = new QueryExecuted('select * from "users"', [], 5, DB::connection());

        $listener = new QueryExecutedListener($this->core);
        $listener($event);

        $this->assertTrue($thrownInQuerySensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
