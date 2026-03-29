<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->text('description')->nullable();
            $table->boolean('is_external')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('api_endpoint_groups', function (Blueprint $table) {
            $table->foreignId('api_project_id')->nullable()->after('id')->constrained('api_projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_endpoint_groups', function (Blueprint $table) {
            $table->dropForeign(['api_project_id']);
            $table->dropColumn('api_project_id');
        });

        Schema::dropIfExists('api_projects');
    }
};
