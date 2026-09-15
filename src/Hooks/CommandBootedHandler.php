<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Contracts\Foundation\Application;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class CommandBootedHandler
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->nightwatch->stage(ExecutionStage::Action);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
