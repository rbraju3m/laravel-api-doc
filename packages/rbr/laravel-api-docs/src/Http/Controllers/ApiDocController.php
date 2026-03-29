<?php

namespace Rbr\LaravelApiDocs\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Rbr\LaravelApiDocs\Models\ApiEndpoint;
use Rbr\LaravelApiDocs\Models\ApiEndpointGroup;
use Rbr\LaravelApiDocs\Models\ApiProject;
use Rbr\LaravelApiDocs\Services\ApiDoc\DocGenerator;

class ApiDocController extends BaseController
{
    public function index(): Response
    {
        $groups = ApiEndpointGroup::whereNull('api_project_id')
            ->with(['endpoints' => function ($query) {
                $query->orderBy('uri')->orderBy('http_method');
            }])
            ->orderBy('sort_order')
            ->get();

        $projects = ApiProject::withCount('endpoints')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('ApiDocs/Index', [
            'groups' => $groups,
            'projects' => $projects,
            'title' => config('api-docs.title'),
            'description' => config('api-docs.description'),
        ]);
    }

    public function show(ApiEndpoint $endpoint): Response
    {
        $endpoint->load(['group', 'parameters', 'responses']);

        return Inertia::render('ApiDocs/Show', [
            'endpoint' => $endpoint,
            'title' => config('api-docs.title'),
        ]);
    }

    public function destroy(ApiEndpoint $endpoint)
    {
        $group = $endpoint->group;
        $projectId = $group?->api_project_id;

        $endpoint->parameters()->delete();
        $endpoint->responses()->delete();
        $endpoint->delete();

        if ($group && ! $group->endpoints()->exists()) {
            $group->delete();
        }

        if ($projectId) {
            return redirect()->route('api-docs.projects.show', $projectId)
                ->with('message', 'Endpoint deleted.');
        }

        return redirect()->route('api-docs.index')
            ->with('message', 'Endpoint deleted.');
    }

    public function generate(DocGenerator $generator)
    {
        $stats = $generator->generate();

        return redirect()->route('api-docs.index')
            ->with('message', "Generated {$stats['endpoints']} endpoints in {$stats['groups']} groups.");
    }
}
