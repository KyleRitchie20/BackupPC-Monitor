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
            if (!Schema::hasColumn('sites', 'agent_token')) {
                $table->string('agent_token', 100)->nullable()->after('api_key');
            }
            if (!Schema::hasColumn('sites', 'agent_version')) {
                $table->string('agent_version', 20)->nullable()->after('agent_token');
            }
            if (!Schema::hasColumn('sites', 'last_agent_contact')) {
                $table->timestamp('last_agent_contact')->nullable()->after('agent_version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $columns = ['agent_token', 'agent_version', 'last_agent_contact'];
            $existingColumns = [];

            foreach ($columns as $column) {
                if (Schema::hasColumn('sites', $column)) {
                    $existingColumns[] = $column;
                }
            }

            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
