<?php

namespace App\Pipelines;

use App\Pipelines\Steps\CheckClaudeAuthentication;
use App\Pipelines\Steps\CheckForTart;
use App\Pipelines\Steps\DetectRecipe;
use App\Pipelines\Steps\EnsureVmExists;
use App\Pipelines\Steps\GetGitBranch;
use App\Pipelines\Steps\SaveSession;

class PreflightPipeline extends SessionPipeline
{
	protected function label(): string
	{
		return 'Setting up project...';
	}
	
	protected function steps(): array
	{
		return [
			CheckForTart::class,
			DetectRecipe::class,
			GetGitBranch::class,
			EnsureVmExists::class,
			CheckClaudeAuthentication::class,
			SaveSession::class,
		];
	}
}
