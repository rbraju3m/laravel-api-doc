<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_projects', function (Blueprint $table) {
            $table->string('project_path', 500)->nullable()->after('base_url');
        });
    }

    public function down(): void
    {
        Schema::table('api_projects', function (Blueprint $table) {
            $table->dropColumn('project_path');
        });
    }
};
