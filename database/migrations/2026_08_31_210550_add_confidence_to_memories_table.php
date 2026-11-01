<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->string('confidence', 16)->default('explicit')->after('source');
            $table->string('key')->nullable()->index()->after('confidence');
            $table->boolean('superseded')->default(false)->index()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropColumn(['confidence', 'key', 'superseded']);
        });
    }
};
