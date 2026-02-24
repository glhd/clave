<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use Closure;

interface Step
{
	public function handle(SessionContext $context, Closure $next): mixed;
}
