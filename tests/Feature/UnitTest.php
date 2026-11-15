<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_units(): void
    {
        $this->getJson('/api/units')->assertUnauthorized();
    }

    public function test_index_returns_paginated_units_and_filters_by_search(): void
    {
        $this->signIn();
        Unit::factory()->create([
            'code' => 'pcs',
            'name' => 'Pieces',
        ]);
        Unit::factory()->create([
            'code' => 'box',
            'name' => 'Box',
        ]);

        $this->getJson('/api/units?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'created_at', 'updated_at', 'created_by_id', 'updated_by_id', 'code', 'name']], 'links', 'meta']);

        $this->getJson('/api/units?search=pie')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'pcs');
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn();

        $this->getJson('/api/units?per_page=1000&sort_by=id')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_store_creates_unit_with_audit_columns(): void
    {
        $user = $this->signIn();

        $this->postJson('/api/units', [
            'code' => 'pcs',
            'name' => 'Pieces',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'pcs')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.updated_by_id', $user->id);
    }

    public function test_store_rejects_missing_and_duplicate_code(): void
    {
        $this->signIn();
        Unit::factory()->create(['code' => 'pcs']);

        $this->postJson('/api/units', [])->assertJsonValidationErrors(['code', 'name']);
        $this->postJson('/api/units', [
            'code' => 'pcs',
            'name' => 'Lain',
        ])->assertJsonValidationErrors(['code']);
    }

    public function test_show_returns_unit(): void
    {
        $this->signIn();
        $unit = Unit::factory()->create();

        $this->getJson("/api/units/{$unit->id}")->assertOk()->assertJsonPath('data.id', $unit->id);
    }

    public function test_update_allows_own_code_and_rejects_code_of_another_unit(): void
    {
        $user = $this->signIn();
        Unit::factory()->create(['code' => 'box']);
        $unit = Unit::factory()->create(['code' => 'pcs']);

        $this->putJson("/api/units/{$unit->id}", [
            'code' => 'pcs',
            'name' => 'Baru',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Baru')
            ->assertJsonPath('data.updated_by_id', $user->id);

        $this->putJson("/api/units/{$unit->id}", [
            'code' => 'box',
            'name' => 'X',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_destroy_deletes_unused_unit(): void
    {
        $this->signIn();
        $unit = Unit::factory()->create();

        $this->deleteJson("/api/units/{$unit->id}")->assertNoContent();

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_destroy_returns_conflict_when_unit_is_a_base_unit(): void
    {
        $this->signIn();
        $product = Product::factory()->create();

        $this->deleteJson("/api/units/{$product->base_unit_id}")->assertConflict();

        $this->assertDatabaseHas('units', ['id' => $product->base_unit_id]);
    }

    public function test_destroy_returns_conflict_when_unit_is_used_by_a_product_unit(): void
    {
        $this->signIn();
        $productUnit = ProductUnit::factory()->create();

        $this->deleteJson("/api/units/{$productUnit->unit_id}")->assertConflict();

        $this->assertDatabaseHas('units', ['id' => $productUnit->unit_id]);
    }
}
