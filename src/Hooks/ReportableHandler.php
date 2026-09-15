<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Foundation\Bootstrap\HandleExceptions;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

use function str_repeat;

/**
 * @internal
 */
final class ReportableHandler
{
    public ?string $reservedMemory;

    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        $this->reservedMemory = str_repeat('n', 32768);
    }

    public function __invoke(Throwable $e): void
    {
        if (HandleExceptions::$reservedMemory === null) {
            $this->reservedMemory = null;
        }

        if ($this->nightwatch->executionState->source === 'schedule') {
            return;
        }

        $this->nightwatch->report($e);
    }
}
