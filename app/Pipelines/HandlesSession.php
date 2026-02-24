<?php

namespace App\Pipelines;

use App\Dto\SessionContext;

interface HandlesSession
{
	public function handle(SessionContext $context): SessionContext;
}
