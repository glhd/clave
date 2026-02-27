<?php

namespace App\Prompts;

use Laravel\Prompts\Prompt;

class ChecklistItem extends Prompt
{
	protected static ?string $active_title = null;
	
	public function __construct(
		protected string $title,
		protected ?string $item = null,
		protected bool $complete = false,
	) {
	}
	
	public function item(string $item, bool $complete = false): static
	{
		return new static($this->title, $item, $complete);
	}
	
	public function run(callable $callback): mixed
	{
		$this->render();
		
		$result = $callback();
		
		$this->complete();
		
		return $result;
	}
	
	public function complete(): static
	{
		$this->complete = true;
		
		$this->render();
		
		return $this;
	}
	
	public function display(): void
	{
		$this->prompt();
	}
	
	public function prompt(): bool
	{
		$this->capturePreviousNewLines();
		
		$this->state = 'submit';
		
		static::output()->write($this->renderTheme());
		
		return true;
	}
	
	public function value(): bool
	{
		return $this->complete;
	}
	
	protected function getRenderer(): callable
	{
		return function(ChecklistItem $item) {
			$output = '';
			
			if ($item->title !== static::$active_title) {
				$output .= str_repeat(PHP_EOL, max(2 - $item->newLinesWritten(), 0));
				$output .= $this->yellow(" {$item->title}");
				$output .= PHP_EOL;
				static::$active_title = $item->title;
			}
			
			if ($this->item) {
				$bullet = $item->complete ? '■' : '□';
				$output .= $this->yellow("   {$bullet} {$item->item}");
				$output .= PHP_EOL;
			}
			
			return $output;
		};
	}
}
