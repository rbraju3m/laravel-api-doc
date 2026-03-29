<?php

namespace Rbr\LaravelApiDocs\Console\Commands;

use Rbr\LaravelApiDocs\Models\ApiProject;
use Rbr\LaravelApiDocs\Services\ApiDoc\DocGenerator;
use Illuminate\Console\Command;

class GenerateApiDocs extends Command
{
    protected $signature = 'api-docs:generate {--project= : Generate docs for a specific external project by ID}';

    protected $description = 'Scan routes and generate API documentation';

    public function handle(DocGenerator $generator): int
    {
        $projectId = $this->option('project');

        if ($projectId) {
            $project = ApiProject::find($projectId);

            if (! $project) {
                $this->error("Project with ID {$projectId} not found.");

                return self::FAILURE;
            }

            if (! $project->project_path) {
                $this->error('This project has no project_path configured.');

                return self::FAILURE;
            }

            $this->info("Scanning external project: {$project->name} ({$project->project_path})...");

            try {
                $stats = $generator->generateForProject($project);
                $this->info("Done! Generated {$stats['endpoints']} endpoints in {$stats['groups']} groups.");
            } catch (\Throwable $e) {
                $this->error('Failed: '.$e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->info('Scanning routes and generating documentation...');

        $stats = $generator->generate();

        $this->info("Done! Generated {$stats['endpoints']} endpoints in {$stats['groups']} groups.");

        return self::SUCCESS;
    }
}
