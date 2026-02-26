<?php

namespace App\Prompts;

use App\Prompts\Renderers\HeaderRenderer;
use Laravel\Prompts\Prompt;

class Header extends Prompt
{
	public function __construct(
		public string $message
	) {
	}
	
	public function display(): void
	{
		$this->prompt();
	}
	
	public function prompt(): bool
	{
		$this->capturePreviousNewLines();
		
		if (static::shouldFallback()) {
			return $this->fallback();
		}
		
		$this->state = 'submit';
		
		static::output()->write($this->renderTheme());
		
		return true;
	}
	
	public function value(): bool
	{
		return true;
	}
	
	protected function getRenderer(): callable
	{
		return new HeaderRenderer($this);
	}
}
