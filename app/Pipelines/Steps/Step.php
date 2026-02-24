<?php

namespace App\Pipelines\Steps;

use App\Dto\SessionContext;
use Closure;

interface Step
{
	public function handle(SessionContext $context, Closure $next): mixed;
}
