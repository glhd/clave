<?php

namespace App\Pipelines\Steps;

use function App\checklist;
use App\Data\SessionContext;
use App\Prompts\ChecklistItem;
use Closure;

abstract class Step
{
	protected string $pipeline = '';
	
	abstract public function handle(SessionContext $context, Closure $next): mixed;
	
	public function setPipelineName(string $pipeline)
	{
		$this->pipeline = $pipeline;
	}
	
	protected function checklist(string $item): ChecklistItem
	{
		return checklist($this->pipeline, $item);
	}
}
