<?php

namespace App\Pipelines;

use App\Pipelines\Steps\BootVm;
use App\Pipelines\Steps\CheckClaudeAuthentication;
use App\Pipelines\Steps\CheckForUpdates;
use App\Pipelines\Steps\CloneRepo;
use App\Pipelines\Steps\CloneVm;
use App\Pipelines\Steps\CreateSshTunnels;
use App\Pipelines\Steps\DetectRecipe;
use App\Pipelines\Steps\EnsureTartInstalled;
use App\Pipelines\Steps\EnsureVmExists;
use App\Pipelines\Steps\GetGitBranch;
use App\Pipelines\Steps\LoadProjectConfig;
use App\Pipelines\Steps\SetupClaudeCode;

class SessionSetup extends SessionPipeline
{
	protected function label(): string
	{
		return 'Setting up virtual machine';
	}
	
	protected function steps(): array
	{
		return [
			CheckForUpdates::class,
			EnsureTartInstalled::class,
			DetectRecipe::class,
			LoadProjectConfig::class,
			GetGitBranch::class,
			EnsureVmExists::class,
			CheckClaudeAuthentication::class,
			CloneRepo::class,
			CloneVm::class,
			BootVm::class,
			SetupClaudeCode::class,
			CreateSshTunnels::class,
		];
	}
}
