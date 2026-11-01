<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('founders', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); $t->text('background')->nullable(); $t->text('skills')->nullable(); $t->text('experience')->nullable(); $t->text('interests')->nullable(); $t->text('personal_goals')->nullable(); $t->text('resources')->nullable(); $t->timestamps(); });
        Schema::create('startups', function (Blueprint $t) { $t->id(); $t->foreignId('founder_id')->constrained()->cascadeOnDelete(); $t->string('name'); $t->text('description')->nullable(); $t->text('problem')->nullable(); $t->text('solution')->nullable(); $t->text('target_customers')->nullable(); $t->string('industry')->nullable(); $t->string('stage')->default('idea')->index(); $t->text('current_challenges')->nullable(); $t->timestamps(); });
        Schema::create('goals', function (Blueprint $t) { $t->id(); $t->foreignId('startup_id')->constrained()->cascadeOnDelete(); $t->string('title'); $t->text('description')->nullable(); $t->string('status')->default('active')->index(); $t->unsignedTinyInteger('progress')->default(0); $t->date('deadline')->nullable()->index(); $t->timestamps(); });
        Schema::create('tasks', function (Blueprint $t) { $t->id(); $t->foreignId('startup_id')->constrained()->cascadeOnDelete(); $t->foreignId('goal_id')->nullable()->constrained()->nullOnDelete(); $t->string('title'); $t->text('description')->nullable(); $t->string('status')->default('pending')->index(); $t->string('priority')->default('medium')->index(); $t->date('due_date')->nullable()->index(); $t->timestamps(); });
        Schema::create('activities', function (Blueprint $t) { $t->id(); $t->foreignId('startup_id')->constrained()->cascadeOnDelete(); $t->string('title'); $t->text('description')->nullable(); $t->string('type')->index(); $t->timestamp('occurred_at')->index(); $t->timestamps(); });
        Schema::create('conversations', function (Blueprint $t) { $t->id(); $t->foreignId('startup_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('title')->nullable(); $t->timestamps(); });
        Schema::create('messages', function (Blueprint $t) { $t->id(); $t->foreignId('conversation_id')->constrained()->cascadeOnDelete(); $t->string('role'); $t->text('content'); $t->timestamps(); });
        Schema::create('memories', function (Blueprint $t) { $t->id(); $t->foreignId('startup_id')->constrained()->cascadeOnDelete(); $t->text('content'); $t->string('category')->index(); $t->unsignedTinyInteger('importance'); $t->string('source')->default('ai'); $t->timestamps(); });
        Schema::create('ai_insights', function (Blueprint $t) { $t->id(); $t->foreignId('startup_id')->constrained()->cascadeOnDelete(); $t->string('title'); $t->text('content'); $t->string('type'); $t->string('priority')->default('medium'); $t->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('ai_insights'); Schema::dropIfExists('memories'); Schema::dropIfExists('messages'); Schema::dropIfExists('conversations'); Schema::dropIfExists('activities'); Schema::dropIfExists('tasks'); Schema::dropIfExists('goals'); Schema::dropIfExists('startups'); Schema::dropIfExists('founders'); }
};
