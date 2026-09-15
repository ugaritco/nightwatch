<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Console\Events\CommandFinished;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;

/**
 * @internal
 */
final class VaporWorkCommandFinishedListener
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
        $this->nightwatch->finishExecution();
    }
}
