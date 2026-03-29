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
            $table->string('base_url')->nullable();
            $table->string('project_path', 500)->nullable();
            $table->json('exclude_prefixes')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_external')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('api_endpoint_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_project_id')->nullable()->constrained('api_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('api_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_endpoint_group_id')->constrained()->cascadeOnDelete();
            $table->string('http_method');
            $table->string('uri');
            $table->string('name')->nullable();
            $table->string('controller_class')->nullable();
            $table->string('controller_method')->nullable();
            $table->text('description')->nullable();
            $table->json('middleware')->nullable();
            $table->boolean('is_authenticated')->default(false);
            $table->boolean('is_closure')->default(false);
            $table->timestamps();
        });

        Schema::create('api_endpoint_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('location', ['query', 'body', 'uri']);
            $table->string('type')->default('string');
            $table->boolean('required')->default(false);
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->text('example')->nullable();
            $table->timestamps();
        });

        Schema::create('api_endpoint_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_endpoint_id')->constrained()->cascadeOnDelete();
            $table->integer('status_code');
            $table->string('description')->nullable();
            $table->string('content_type')->default('application/json');
            $table->json('example_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_endpoint_responses');
        Schema::dropIfExists('api_endpoint_parameters');
        Schema::dropIfExists('api_endpoints');
        Schema::dropIfExists('api_endpoint_groups');
        Schema::dropIfExists('api_projects');
    }
};
