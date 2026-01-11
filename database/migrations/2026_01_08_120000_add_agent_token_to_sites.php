<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('agent_token', 100)->nullable()->after('api_key');
            $table->string('agent_version', 20)->nullable()->after('agent_token');
            $table->timestamp('last_agent_contact')->nullable()->after('agent_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['agent_token', 'agent_version', 'last_agent_contact']);
        });
    }
};
