<?php

namespace App\Prompts;

use Laravel\Prompts\Progress;

class ClaveStatus extends Progress
{
	protected int $refs = 0;

	public function __construct()
	{
		parent::__construct('', 1);
	}

	public function start(string $label = '', int $total = 0): void
	{
		if ($this->refs === 0) {
			$this->label = $label;
			$this->total = $total;
			$this->progress = 0;
			parent::start();
		} else {
			$this->total += $total;
			$this->render();
		}

		$this->refs++;
	}

	public function finish(string $hint = ''): void
	{
		if ($this->refs === 0) {
			return;
		}

		$this->refs--;

		if ($this->refs === 0) {
			if ($hint !== '') {
				$this->hint = $hint;
			}
			parent::finish();
		}
	}
}
