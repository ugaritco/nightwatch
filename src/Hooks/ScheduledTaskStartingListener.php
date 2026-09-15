<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Console\Events\ScheduledTaskStarting;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class ScheduledTaskStartingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ScheduledTaskStarting $event): void
    {
        try {
            $this->nightwatch->prepareForScheduledTask($event->task);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
