<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Http\Client\Factory;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class HttpClientFactoryResolvedHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Factory $factory): void
    {
        try {
            /**
             * @see \Ugarit\Nightwatch\Records\OutgoingRequest
             */
            $factory->globalMiddleware($this->nightwatch->guzzleMiddleware());
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
