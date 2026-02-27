<?php

namespace App;

use App\Prompts\ChecklistItem;
use App\Prompts\Header;

function header(string $header)
{
	(new Header($header))->display();
}

function checklist(string $title, ?string $item = null, bool $complete = false): ChecklistItem
{
	$checklist = new ChecklistItem($title, $item, $complete);
	
	$checklist->display();
	
	return $checklist;
}

function clear_screen()
{
	echo "\033[H\033[2J";
}
