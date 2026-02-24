<?php

namespace App\Pipelines;

use App\Data\SessionContext;

interface HandlesSession
{
	public function handle(SessionContext $context): SessionContext;
}
