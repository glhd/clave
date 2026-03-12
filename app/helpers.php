<?php

namespace App;

use App\Prompts\ChecklistItem;
use App\Prompts\Heading;

function heading(string $header): void
{
	(new Heading($header))->display();
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

function home_path(?string $path = null): string
{
	$home = $_SERVER['HOME'] ?? getenv('HOME');

	return $path !== null
		? rtrim($home, '/').'/'.ltrim($path, '/')
		: $home;
}
