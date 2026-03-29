<?php

namespace Rbr\LaravelApiDocs\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Rbr\LaravelApiDocs\Http\Requests\StoreEndpointRequest;
use Rbr\LaravelApiDocs\Http\Requests\UpdateEndpointRequest;
use Rbr\LaravelApiDocs\Models\ApiEndpoint;
use Rbr\LaravelApiDocs\Models\ApiEndpointGroup;
use Rbr\LaravelApiDocs\Models\ApiEndpointParameter;
use Rbr\LaravelApiDocs\Models\ApiEndpointResponse;
use Rbr\LaravelApiDocs\Models\ApiProject;

class ExternalEndpointController extends Controller
{
    public function create(ApiProject $project): Response
    {
        return Inertia::render('ApiDocs/Endpoints/Create', [
            'project' => $project,
            'title' => config('api-docs.title'),
        ]);
    }

    public function store(StoreEndpointRequest $request, ApiProject $project)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $project) {
            $group = ApiEndpointGroup::firstOrCreate(
                ['api_project_id' => $project->id, 'name' => $validated['group_name']],
                ['prefix' => strtolower(str_replace(' ', '-', $validated['group_name'])), 'sort_order' => ApiEndpointGroup::where('api_project_id', $project->id)->max('sort_order') + 1],
            );

            $endpoint = ApiEndpoint::create([
                'api_endpoint_group_id' => $group->id,
                'http_method' => $validated['http_method'],
                'uri' => $validated['uri'],
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_authenticated' => $validated['is_authenticated'] ?? false,
                'middleware' => [],
                'is_closure' => false,
            ]);

            foreach ($validated['parameters'] ?? [] as $param) {
                ApiEndpointParameter::create([
                    'api_endpoint_id' => $endpoint->id,
                    'name' => $param['name'],
                    'location' => $param['location'],
                    'type' => $param['type'],
                    'required' => $param['required'] ?? false,
                    'description' => $param['description'] ?? null,
                    'rules' => $param['rules'] ?? null,
                    'example' => $param['example'] ?? null,
                ]);
            }

            foreach ($validated['responses'] ?? [] as $resp) {
                $exampleBody = null;
                if (! empty($resp['example_body'])) {
                    $decoded = json_decode($resp['example_body'], true);
                    $exampleBody = $decoded !== null ? $decoded : $resp['example_body'];
                }

                ApiEndpointResponse::create([
                    'api_endpoint_id' => $endpoint->id,
                    'status_code' => $resp['status_code'],
                    'description' => $resp['description'] ?? null,
                    'content_type' => $resp['content_type'] ?? 'application/json',
                    'example_body' => $exampleBody,
                ]);
            }
        });

        return redirect()->route('api-docs.projects.show', $project)
            ->with('message', 'Endpoint created.');
    }

    public function edit(ApiProject $project, ApiEndpoint $endpoint): Response
    {
        $endpoint->load(['group', 'parameters', 'responses']);

        return Inertia::render('ApiDocs/Endpoints/Edit', [
            'project' => $project,
            'endpoint' => $endpoint,
            'title' => config('api-docs.title'),
        ]);
    }

    public function update(UpdateEndpointRequest $request, ApiProject $project, ApiEndpoint $endpoint)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $project, $endpoint) {
            $group = ApiEndpointGroup::firstOrCreate(
                ['api_project_id' => $project->id, 'name' => $validated['group_name']],
                ['prefix' => strtolower(str_replace(' ', '-', $validated['group_name'])), 'sort_order' => ApiEndpointGroup::where('api_project_id', $project->id)->max('sort_order') + 1],
            );

            $endpoint->update([
                'api_endpoint_group_id' => $group->id,
                'http_method' => $validated['http_method'],
                'uri' => $validated['uri'],
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_authenticated' => $validated['is_authenticated'] ?? false,
            ]);

            $endpoint->parameters()->delete();
            foreach ($validated['parameters'] ?? [] as $param) {
                ApiEndpointParameter::create([
                    'api_endpoint_id' => $endpoint->id,
                    'name' => $param['name'],
                    'location' => $param['location'],
                    'type' => $param['type'],
                    'required' => $param['required'] ?? false,
                    'description' => $param['description'] ?? null,
                    'rules' => $param['rules'] ?? null,
                    'example' => $param['example'] ?? null,
                ]);
            }

            $endpoint->responses()->delete();
            foreach ($validated['responses'] ?? [] as $resp) {
                $exampleBody = null;
                if (! empty($resp['example_body'])) {
                    $decoded = json_decode($resp['example_body'], true);
                    $exampleBody = $decoded !== null ? $decoded : $resp['example_body'];
                }

                ApiEndpointResponse::create([
                    'api_endpoint_id' => $endpoint->id,
                    'status_code' => $resp['status_code'],
                    'description' => $resp['description'] ?? null,
                    'content_type' => $resp['content_type'] ?? 'application/json',
                    'example_body' => $exampleBody,
                ]);
            }

            $this->cleanupEmptyGroups($project);
        });

        return redirect()->route('api-docs.projects.show', $project)
            ->with('message', 'Endpoint updated.');
    }

    public function destroy(ApiProject $project, ApiEndpoint $endpoint)
    {
        $endpoint->delete();
        $this->cleanupEmptyGroups($project);

        return redirect()->route('api-docs.projects.show', $project)
            ->with('message', 'Endpoint deleted.');
    }

    private function cleanupEmptyGroups(ApiProject $project): void
    {
        ApiEndpointGroup::where('api_project_id', $project->id)
            ->whereDoesntHave('endpoints')
            ->delete();
    }
}
