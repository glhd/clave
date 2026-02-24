<?php

namespace App\Pipelines\Steps;

use Laravel\Prompts\Progress;

trait AcceptsProgress
{
	protected ?Progress $progress = null;
	
	public function setProgress(Progress $progress): void
	{
		$this->progress = $progress;
	}
	
	protected function hint(string $message): void
	{
		$this->progress?->hint($message)->render();
	}
}
