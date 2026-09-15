<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Foundation\Events\Terminating;
use Ugarit\Nightwatch\Compatibility;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class TerminatingListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Terminating $event): void
    {
        if (! Compatibility::$terminatingEventExists) {
            return;
        }

        try {
            $this->nightwatch->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
