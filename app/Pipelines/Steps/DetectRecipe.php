<?php

namespace App\Pipelines\Steps;

use App\Data\Recipe;
use App\Data\SessionContext;
use Closure;
use Illuminate\Filesystem\Filesystem;

class DetectRecipe extends Step
{
	public function __construct(
		protected Filesystem $fs,
	) {
	}
	
	public function handle(SessionContext $context, Closure $next): mixed
	{
		$this->hint('Detecting project type...');
		
		$context->recipe = $this->detect($context->project_dir);
		
		$this->hint(match ($context->recipe) {
			Recipe::Unknown => 'Unknown project type',
			default => "Found {$context->recipe->label()} project",
		});
		
		return $next($context);
	}
	
	protected function detect(string $project_dir): Recipe
	{
		if ($this->fs->exists($project_dir.'/artisan') && $this->fs->exists($project_dir.'/composer.json')) {
			return Recipe::Laravel;
		}
		
		return Recipe::Unknown;
	}
}
