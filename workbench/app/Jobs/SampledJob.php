<?php

namespace App\Jobs;

use Heritage\Bus\Queueable;
use Heritage\Contracts\Queue\ShouldQueue;
use Heritage\Foundation\Bus\Dispatchable;
use Heritage\Queue\InteractsWithQueue;
use Heritage\Queue\SerializesModels;
use Ugarit\Nightwatch\Facades\Nightwatch;

class SampledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private float $rate)
    {
        //
    }

    public function handle(): void
    {
        Nightwatch::sample($this->rate);
    }
}
