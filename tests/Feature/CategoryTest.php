<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_categories(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_index_returns_paginated_categories(): void
    {
        $this->signIn();
        Category::factory()->count(3)->create();

        $this->getJson('/api/categories?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'created_at', 'updated_at', 'created_by_id', 'updated_by_id', 'name']], 'links', 'meta']);
    }

    public function test_index_filters_by_search(): void
    {
        $this->signIn();
        Category::factory()->create(['name' => 'Elektronik']);
        Category::factory()->create(['name' => 'Makanan']);

        $this->getJson('/api/categories?search=elek')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Elektronik');
    }

    public function test_index_sorts_descending(): void
    {
        $this->signIn();
        Category::factory()->create(['name' => 'A']);
        Category::factory()->create(['name' => 'B']);

        $this->getJson('/api/categories?sort_by=name&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'B');
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/categories?per_page=1000&sort_by=id')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_store_creates_category_with_audit_columns(): void
    {
        $user = $this->signIn();

        $this->postJson('/api/categories', ['name' => 'Elektronik'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Elektronik')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.updated_by_id', $user->id);

        $this->assertDatabaseHas('categories', ['name' => 'Elektronik']);
    }

    public function test_store_rejects_missing_and_duplicate_name(): void
    {
        $this->signIn();
        Category::factory()->create(['name' => 'Elektronik']);

        $this->postJson('/api/categories', [])->assertJsonValidationErrors(['name']);
        $this->postJson('/api/categories', ['name' => 'Elektronik'])->assertJsonValidationErrors(['name']);
    }

    public function test_show_returns_category(): void
    {
        $this->signIn();
        $category = Category::factory()->create();

        $this->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_show_returns_not_found(): void
    {
        $this->signIn();

        $this->getJson('/api/categories/999')->assertNotFound();
    }

    public function test_update_changes_name_and_updater(): void
    {
        $category = Category::factory()->create(['name' => 'Lama']);
        $user = $this->signIn();

        $this->putJson("/api/categories/{$category->id}", ['name' => 'Baru'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Baru')
            ->assertJsonPath('data.updated_by_id', $user->id);
    }

    public function test_update_allows_keeping_the_same_name(): void
    {
        $this->signIn();
        $category = Category::factory()->create(['name' => 'Sama']);

        $this->putJson("/api/categories/{$category->id}", ['name' => 'Sama'])->assertOk();
    }

    public function test_update_rejects_name_used_by_another_category(): void
    {
        $this->signIn();
        Category::factory()->create(['name' => 'Milik Lain']);
        $category = Category::factory()->create(['name' => 'Awal']);

        $this->putJson("/api/categories/{$category->id}", ['name' => 'Milik Lain'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Awal',
        ]);
    }

    public function test_destroy_deletes_category(): void
    {
        $this->signIn();
        $category = Category::factory()->create();

        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_conflict_when_category_is_used(): void
    {
        $this->signIn();
        $category = Category::factory()->create();
        $unitId = DB::table('units')->insertGetId([
            'code' => 'pcs',
            'name' => 'Pieces',
        ]);
        DB::table('products')->insert([
            'base_unit_id' => $unitId,
            'category_id' => $category->id,
            'name' => 'Kabel',
            'sku' => 'KBL-001',
        ]);

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Data masih digunakan oleh data lain.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
