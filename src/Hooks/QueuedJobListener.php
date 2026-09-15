<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Queue\Events\JobQueued;
use Heritage\Queue\Events\JobQueueing;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class QueuedJobListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobQueueing|JobQueued $event): void
    {
        try {
            $this->nightwatch->queuedJob($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
