<?php

namespace Tests\Unit;

use Ugarit\Nightwatch\Payload;
use Ugarit\NightwatchAgent\Payload as AgentPayload;
use Tests\TestCase;

require __DIR__.'/../../agent/src/Payload.php';

class PayloadVersionTest extends TestCase
{
    public function test_that_payload_versions_match(): void
    {
        $this->assertSame(Payload::PAYLOAD_VERSION, AgentPayload::EXPECTED_PAYLOAD_VERSION, 'Package payload version must match Agent payload version, changing this indicates that a new major version must be tagged');
    }
}
