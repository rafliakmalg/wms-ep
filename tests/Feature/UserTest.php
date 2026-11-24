<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_index_filters_by_search_and_active(): void
    {
        $this->actingAsUser(User::factory()->create(['name' => 'Aksara']), []);
        User::factory()->create(['name' => 'Budi Santoso']);
        User::factory()->create([
            'name' => 'Budi Nonaktif',
            'active' => false,
        ]);

        $this->getJson('/api/users?search=budi')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/users?search=budi&filters[0][field]=active&filters[0][operator]=eq&filters[0][value]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi Santoso')
            ->assertJsonStructure(['data' => [['id', 'created_at', 'updated_at', 'name', 'active']], 'links', 'meta']);
    }

    public function test_index_is_open_to_any_authenticated_user_without_permissions(): void
    {
        $this->actingAsUser(User::factory()->create(), []);

        $this->getJson('/api/users')->assertOk();
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->actingAsUser(User::factory()->create(), []);

        $this->getJson('/api/users?per_page=1000&sort_by=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }
}
