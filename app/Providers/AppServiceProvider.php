<?php

namespace App\Providers;

use App\Services\GitManager;
use App\Services\HerdManager;
use App\Services\SessionTeardown;
use App\Services\SshExecutor;
use App\Services\TartManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
	public function boot(): void
	{
	}

	public function register(): void
	{
		$this->app->singleton(TartManager::class);
		$this->app->singleton(GitManager::class);
		$this->app->singleton(SshExecutor::class);
		$this->app->singleton(HerdManager::class);
		$this->app->singleton(SessionTeardown::class);
	}
}
