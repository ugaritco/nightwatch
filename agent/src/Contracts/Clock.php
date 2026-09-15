<?php

namespace Ugarit\NightwatchAgent\Contracts;

interface Clock
{
    public function time(): int;
}
