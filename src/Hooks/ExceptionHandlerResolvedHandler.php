<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Contracts\Debug\ExceptionHandler;
use Heritage\Foundation\Exceptions\Handler;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class ExceptionHandlerResolvedHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ExceptionHandler $handler): void
    {
        try {
            if ($handler instanceof Handler) {
                /**
                 * @see \Ugarit\Nightwatch\Records\Exception
                 */
                $handler->reportable(new ReportableHandler($this->nightwatch));
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
