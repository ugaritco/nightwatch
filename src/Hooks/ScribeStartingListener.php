<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Console\Events\ScribeStarting;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class ScribeStartingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ScribeStarting $event): void
    {
        try {
            $this->nightwatch->captureScribe($event->scribe);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
