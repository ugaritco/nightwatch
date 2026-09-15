<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Console\Events\CommandFinished;
use Heritage\Console\Events\CommandStarting;
use Heritage\Console\Events\ScheduledTaskFailed;
use Heritage\Console\Events\ScheduledTaskFinished;
use Heritage\Console\Events\ScheduledTaskSkipped;
use Heritage\Console\Events\ScheduledTaskStarting;
use Heritage\Contracts\Console\Kernel as ConsoleKernelContract;
use Heritage\Contracts\Events\Dispatcher;
use Heritage\Foundation\Console\Kernel as ConsoleKernel;
use Heritage\Queue\Events\JobFailed;
use Heritage\Queue\Events\JobPopping;
use Heritage\Queue\Events\JobProcessed;
use Heritage\Queue\Events\JobProcessing;
use Heritage\Queue\Events\JobReleasedAfterException;
use Heritage\Queue\Events\Looping;
use Heritage\Queue\Events\WorkerStopping;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\Facades\Nightwatch;
use Ugarit\Nightwatch\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class CommandStartingListener
{
    private bool $hasRun = false;

    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Dispatcher $events,
        private Core $nightwatch,
        private ConsoleKernelContract $kernel,
    ) {
        //
    }

    public function __invoke(CommandStarting $event): void
    {
        if ($this->hasRun) {
            return;
        }

        $this->hasRun = true;

        try {
            match ($event->command) {
                'queue:work', 'queue:listen', 'horizon:work', 'vapor:work' => $this->registerJobHooks($event),
                'schedule:run', 'schedule:work', 'vapor:schedule' => $this->registerScheduledTaskHooks(),
                'help', 'inspire', 'schedule:finish' => null,
                default => $this->registerCommandHooks($event),
            };
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }

    private function registerJobHooks(CommandStarting $event): void
    {
        $this->nightwatch->configureForJobs();

        /**
         * @see \Ugarit\Nightwatch\Core::finishExecution()
         * @see \Ugarit\Nightwatch\State\CommandState::flush()
         * @see \Ugarit\Nightwatch\State\CommandState::$timestamp
         * @see \Ugarit\Nightwatch\State\CommandState::$id
         */
        $this->events->listen([
            Looping::class,
            JobPopping::class,
            JobProcessing::class,
            WorkerStopping::class,
            CommandFinished::class,
        ], (new WorkerLifecycleListener($this->nightwatch))(...));

        /**
         * @see \Ugarit\Nightwatch\Records\JobAttempt
         * @see \Ugarit\Nightwatch\Core::finishExecution()
         */
        $this->events->listen([
            JobProcessed::class,
            JobReleasedAfterException::class,
            JobFailed::class,
        ], (new JobAttemptListener($this->nightwatch))(...));

        if ($event->command === 'vapor:work') {
            $this->events->listen(CommandFinished::class, (new VaporWorkCommandFinishedListener($this->nightwatch))(...));
        }
    }

    private function registerScheduledTaskHooks(): void
    {
        $this->nightwatch->configureForScheduledTasks();

        $this->events->listen(ScheduledTaskStarting::class, (new ScheduledTaskStartingListener($this->nightwatch))(...));

        /**
         * @see \Ugarit\Nightwatch\Core::finishExecution()
         */
        $this->events->listen([
            ScheduledTaskFinished::class,
            ScheduledTaskSkipped::class,
            ScheduledTaskFailed::class,
        ], (new ScheduledTaskListener($this->nightwatch))(...));
    }

    private function registerCommandHooks(CommandStarting $event): void
    {
        if (! $this->kernel instanceof ConsoleKernel) {
            return;
        }

        $this->nightwatch->configureCommandSampling($event->command);

        $this->nightwatch->prepareForCommand($event->command);

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::Terminating
         */
        $this->events->listen(CommandFinished::class, (new CommandFinishedListener($this->nightwatch))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::End
         * @see \Ugarit\Nightwatch\Records\Command
         * @see \Ugarit\Nightwatch\Core::finishExecution()
         */
        $this->kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->nightwatch));
    }
}
