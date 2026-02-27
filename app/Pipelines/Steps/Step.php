<?php

namespace App\Pipelines\Steps;

use App\Data\SessionContext;
use App\Prompts\ChecklistItem;
use Closure;
use function App\checklist;

abstract class Step
{
	protected string $pipeline;
	
	abstract public function handle(SessionContext $context, Closure $next): mixed;
	
	public function setPipelineName(string $pipeline)
	{
		$this->pipeline = $pipeline;
	}
	
	protected function hint(string $message)
	{
		// FIXME
	}
	
	protected function checklist(string $item): ChecklistItem
	{
		return checklist($this->pipeline, $item);
	}
}
