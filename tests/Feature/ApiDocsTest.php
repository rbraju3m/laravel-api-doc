<?php

namespace Tests\Feature;

use App\Http\Controllers\ExternalProjectController;
use App\Http\Requests\StoreProjectRequest;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointGroup;
use App\Services\ApiDoc\ControllerParser;
use App\Services\ApiDoc\DocGenerator;
use App\Services\ApiDoc\RequestValidationParser;
use App\Services\ApiDoc\ResponseGenerator;
use App\Services\ApiDoc\RouteScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_scanner_returns_routes(): void
    {
        $scanner = new RouteScanner;
        $routes = $scanner->scan();

        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);

        // Should find docs/api project routes
        $uris = array_column($routes, 'uri');
        $this->assertContains('/', $uris);
    }

    public function test_route_scanner_excludes_docs_routes(): void
    {
        $scanner = new RouteScanner;
        $routes = $scanner->scan();

        $uris = array_column($routes, 'uri');
        foreach ($uris as $uri) {
            $this->assertStringStartsNotWith('docs/api', $uri);
        }
    }

    public function test_controller_parser_extracts_docblock(): void
    {
        $parser = new ControllerParser;
        $result = $parser->parse(ExternalProjectController::class, 'index');

        $this->assertEquals('List all external projects.', $result['description']);
    }

    public function test_controller_parser_detects_form_request(): void
    {
        $parser = new ControllerParser;
        $result = $parser->parse(ExternalProjectController::class, 'store');

        $this->assertEquals(StoreProjectRequest::class, $result['form_request_class']);
    }

    public function test_validation_parser_extracts_rules(): void
    {
        $parser = new RequestValidationParser;
        $params = $parser->parse(StoreProjectRequest::class);

        $this->assertNotEmpty($params);

        $names = array_column($params, 'name');
        $this->assertContains('name', $names);
        $this->assertContains('base_url', $names);

        // Check url type detection
        $urlParam = collect($params)->firstWhere('name', 'base_url');
        $this->assertEquals('string (url)', $urlParam['type']);

        // Check required detection
        $nameParam = collect($params)->firstWhere('name', 'name');
        $this->assertTrue($nameParam['required']);
    }

    public function test_response_generator_creates_responses(): void
    {
        $generator = new ResponseGenerator;

        $postResponses = $generator->generate('POST', true, true);
        $statusCodes = array_column($postResponses, 'status_code');

        $this->assertContains(201, $statusCodes);
        $this->assertContains(422, $statusCodes);
        $this->assertContains(401, $statusCodes);
    }

    public function test_doc_generator_creates_database_records(): void
    {
        $generator = app(DocGenerator::class);
        $stats = $generator->generate();

        $this->assertGreaterThan(0, $stats['groups']);
        $this->assertGreaterThan(0, $stats['endpoints']);
        $this->assertGreaterThan(0, ApiEndpointGroup::count());
        $this->assertGreaterThan(0, ApiEndpoint::count());
    }

    public function test_artisan_command_runs_successfully(): void
    {
        $this->artisan('api-docs:generate')
            ->expectsOutputToContain('Done!')
            ->assertExitCode(0);
    }

    public function test_index_page_loads(): void
    {
        $response = $this->get('/docs/api');
        $response->assertStatus(200);
    }

    public function test_show_page_loads(): void
    {
        app(DocGenerator::class)->generate();

        $endpoint = ApiEndpoint::first();
        $this->assertNotNull($endpoint);

        $response = $this->get("/docs/api/endpoints/{$endpoint->id}");
        $response->assertStatus(200);
    }

    public function test_generate_endpoint_works(): void
    {
        $response = $this->post('/docs/api/generate');
        $response->assertRedirect('/docs/api');

        $this->assertGreaterThan(0, ApiEndpoint::count());
    }
}
