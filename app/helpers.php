<?php

namespace App;

use App\Prompts\Header;

function header(string $header)
{
	(new Header($header))->display();
}

function clear_screen()
{
	echo "\033[H\033[2J";
}
