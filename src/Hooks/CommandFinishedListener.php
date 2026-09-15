<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Console\Events\CommandFinished;
use Ugarit\Nightwatch\Compatibility;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class CommandFinishedListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(CommandFinished $event): void
    {
        try {
            if ($this->nightwatch->capturingCommandNamed($event->command) && ! Compatibility::$terminatingEventExists) {
                $this->nightwatch->stage(ExecutionStage::Terminating);
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
