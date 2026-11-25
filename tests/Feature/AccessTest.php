<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private const OPEN_TO_AUTHENTICATED = ['api/auth/me', 'api/users'];

    /**
     * @return array<int, LaravelRoute>
     */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/') && $route->uri() !== 'api/up',
        ));
    }

    public function test_every_api_route_requires_a_token(): void
    {
        foreach ($this->apiRoutes() as $route) {
            $this->assertContains('auth:api', $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_every_route_except_the_open_ones_asks_for_a_permission_named_resource_dot_action(): void
    {
        foreach ($this->apiRoutes() as $route) {
            if (in_array($route->uri(), self::OPEN_TO_AUTHENTICATED, true)) {
                continue;
            }

            $abilities = array_filter(
                $route->gatherMiddleware(),
                fn ($middleware) => is_string($middleware) && str_contains($middleware, 'Authorize:'),
            );

            $this->assertCount(1, $abilities, $route->uri());
            $this->assertMatchesRegularExpression('/Authorize:[a-z]+\.[a-z_]+$/', array_values($abilities)[0], $route->uri());
        }
    }

    public function test_guest_without_json_accept_header_gets_401_instead_of_a_server_error(): void
    {
        $this->get('/api/branches')->assertUnauthorized();
    }

    public function test_user_without_permissions_is_forbidden_on_collection_endpoints(): void
    {
        $this->actingAsUser(User::factory()->create(), []);

        foreach (['adjustments', 'branches', 'categories', 'dashboard', 'handovers', 'inventories', 'movements', 'products', 'racks', 'units', 'warehouses'] as $path) {
            $this->getJson("/api/{$path}")->assertForbidden();
        }

        $this->postJson('/api/branches', [])->assertForbidden();
        $this->postJson('/api/movements', [])->assertForbidden();
    }
}
