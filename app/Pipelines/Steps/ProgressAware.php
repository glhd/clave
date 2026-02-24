<?php

namespace App\Pipelines\Steps;

use Laravel\Prompts\Progress;

interface ProgressAware
{
	public function setProgress(Progress $progress): void;
}
