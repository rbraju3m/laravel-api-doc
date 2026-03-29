<?php

namespace Rbr\LaravelApiDocs\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Rbr\LaravelApiDocs\Http\Requests\StoreProjectRequest;
use Rbr\LaravelApiDocs\Http\Requests\UpdateProjectRequest;
use Rbr\LaravelApiDocs\Models\ApiProject;
use Rbr\LaravelApiDocs\Services\ApiDoc\DocGenerator;

class ExternalProjectController extends BaseController
{
    public function index(): Response
    {
        $projects = ApiProject::withCount('endpoints')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('ApiDocs/Projects/Index', [
            'projects' => $projects,
            'title' => config('api-docs.title'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ApiDocs/Projects/Create', [
            'title' => config('api-docs.title'),
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $project = ApiProject::create([
            ...$request->validated(),
            'sort_order' => ApiProject::max('sort_order') + 1,
        ]);

        return redirect()->route('api-docs.projects.show', $project)
            ->with('message', "Project \"{$project->name}\" created.");
    }

    public function show(ApiProject $project): Response
    {
        $project->load(['groups' => function ($query) {
            $query->orderBy('sort_order');
        }, 'groups.endpoints' => function ($query) {
            $query->orderBy('uri')->orderBy('http_method');
        }]);

        return Inertia::render('ApiDocs/Projects/Show', [
            'project' => $project,
            'title' => config('api-docs.title'),
        ]);
    }

    public function edit(ApiProject $project): Response
    {
        return Inertia::render('ApiDocs/Projects/Edit', [
            'project' => $project,
            'title' => config('api-docs.title'),
        ]);
    }

    public function update(UpdateProjectRequest $request, ApiProject $project)
    {
        $project->update($request->validated());

        return redirect()->route('api-docs.projects.show', $project)
            ->with('message', "Project \"{$project->name}\" updated.");
    }

    public function generate(ApiProject $project, DocGenerator $generator)
    {
        if (! $project->project_path) {
            return redirect()->route('api-docs.projects.show', $project)
                ->with('message', 'No project path configured. Set a Laravel project path first.');
        }

        if (! is_dir($project->project_path)) {
            return redirect()->route('api-docs.projects.show', $project)
                ->with('message', "Project path does not exist: {$project->project_path}");
        }

        if (! file_exists($project->project_path.'/artisan')) {
            return redirect()->route('api-docs.projects.show', $project)
                ->with('message', 'The specified path does not appear to be a Laravel project (no artisan file found).');
        }

        try {
            $stats = $generator->generateForProject($project);

            return redirect()->route('api-docs.projects.show', $project)
                ->with('message', "Generated {$stats['endpoints']} endpoints in {$stats['groups']} groups from project.");
        } catch (\Throwable $e) {
            return redirect()->route('api-docs.projects.show', $project)
                ->with('message', 'Failed to generate docs: '.$e->getMessage());
        }
    }

    public function destroy(ApiProject $project)
    {
        $name = $project->name;
        $project->delete();

        return redirect()->route('api-docs.projects.index')
            ->with('message', "Project \"{$name}\" deleted.");
    }
}
