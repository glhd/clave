<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
	public function up(): void
	{
		Schema::create('sessions', function(Blueprint $table) {
			$table->id();
			$table->string('session_id')->unique();
			$table->string('project_dir');
			$table->string('project_name');
			$table->string('branch')->nullable();
			$table->string('vm_name')->nullable();
			$table->integer('port')->nullable();
			$table->string('proxy_name')->nullable();
			$table->integer('pid')->nullable();
			$table->timestamp('started_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('sessions');
	}
};
