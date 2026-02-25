<?php

namespace App\Providers;

use App\Pipelines\ClaudeCodePipeline;
use App\Pipelines\PreflightPipeline;
use App\Pipelines\Steps\CheckClaudeAuthentication;
use App\Pipelines\Steps\DetectRecipe;
use App\Pipelines\Steps\EnsureTartInstalled;
use App\Pipelines\Steps\EnsureVmExists;
use App\Pipelines\Steps\SaveSession;
use App\Support\AuthManager;
use App\Support\GitManager;
use App\Support\HerdManager;
use App\Support\InstallationManager;
use App\Support\SessionTeardown;
use App\Support\SshExecutor;
use App\Support\TartManager;
use Illuminate\Console\Signals;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
	public function boot(): void
	{
		$fs = app(Filesystem::class);
		
		$config_dir = ($_SERVER['HOME'] ?? getenv('HOME')).'/.config/clave';
		
		$fs->ensureDirectoryExists($config_dir);
	}

	public function register(): void
	{
		Signals::resolveAvailabilityUsing(fn() => $this->app->runningInConsole()
			&& ! $this->app->runningUnitTests()
			&& extension_loaded('pcntl'));
		
		$this->app->singleton(PreflightPipeline::class);
		$this->app->singleton(ClaudeCodePipeline::class);

		$this->app->singleton(AuthManager::class);
		$this->app->singleton(InstallationManager::class);
		$this->app->singleton(TartManager::class);
		$this->app->singleton(GitManager::class);
		$this->app->singleton(SshExecutor::class);
		$this->app->singleton(HerdManager::class);
		$this->app->singleton(SessionTeardown::class);
		$this->app->singleton(SaveSession::class);
		
		$this->app->singleton(DetectRecipe::class);
		$this->app->singleton(EnsureTartInstalled::class);
		$this->app->singleton(EnsureVmExists::class);
		$this->app->singleton(CheckClaudeAuthentication::class);
	}
}
