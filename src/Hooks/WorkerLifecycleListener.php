<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Console\Events\CommandFinished;
use Heritage\Queue\Events\JobPopping;
use Heritage\Queue\Events\JobProcessing;
use Heritage\Queue\Events\Looping;
use Heritage\Queue\Events\WorkerStopping;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class WorkerLifecycleListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Looping|JobPopping|JobProcessing|WorkerStopping|CommandFinished $event): void
    {
        try {
            match ($event::class) {
                Looping::class, WorkerStopping::class => $this->nightwatch->finishExecution()->waitForExecution(),
                CommandFinished::class => $event->command === 'queue:work' && $this->nightwatch->finishExecution()->waitForExecution(),
                JobPopping::class => $this->nightwatch->prepareForNextJob(),
                JobProcessing::class => $this->nightwatch->prepareForJob($event->job),
            };
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
