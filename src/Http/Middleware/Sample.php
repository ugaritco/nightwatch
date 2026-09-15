<?php

namespace Ugarit\Nightwatch\Http\Middleware;

use Closure;
use Heritage\Http\Request;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\Facades\Nightwatch;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

final class Sample
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(private Core $nightwatch)
    {
        //
    }

    public static function rate(float $rate): string
    {
        $rate = (string) $rate;

        if ($rate === '0') {
            $rate = '0.0';
        }

        return self::class.':'.$rate;
    }

    public static function always(): string
    {
        return self::class.':1.0';
    }

    public static function never(): string
    {
        return self::class.':0.0';
    }

    public function handle(Request $request, Closure $next, float $rate): mixed
    {
        try {
            $this->nightwatch->sample($rate);
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }

        return $next($request);
    }
}
