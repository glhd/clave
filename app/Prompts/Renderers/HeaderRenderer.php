<?php

namespace App\Prompts\Renderers;

use App\Prompts\Heading;
use Laravel\Prompts\Themes\Default\Renderer;

class HeaderRenderer extends Renderer
{
	public function __invoke(Heading $header): string
	{
		$width = $header->terminal()->cols() - 6;
		$lines = explode(PHP_EOL, $this->mbWordwrap($header->message, $width - 2));
		
		$draw = ['h' => '─', 'v' => '│', 'tl' => '╭', 'tr' => '╮', 'bl' => '╰', 'br' => '╯'];
		
		$this->line($this->cyan($draw['tl'].str_repeat($draw['h'], $width).$draw['tr']));
		
		foreach ($lines as $line) {
			$line = mb_str_pad($line, $width - 2, ' ');
			$this->line($this->cyan("{$draw['v']} ").$this->black($line).$this->cyan(" {$draw['v']}"));
		}
		
		$this->line($this->cyan($draw['bl'].str_repeat($draw['h'], $width).$draw['br']));
		
		return (string) $this;
	}
}
