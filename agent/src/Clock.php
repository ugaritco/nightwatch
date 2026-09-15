<?php

namespace Ugarit\NightwatchAgent;

use Ugarit\NightwatchAgent\Contracts\Clock as ClockContract;

use function time;

class Clock implements ClockContract
{
    public function time(): int
    {
        return time();
    }
}
