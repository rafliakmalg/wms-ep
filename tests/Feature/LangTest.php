<?php

namespace Tests\Feature;

use App\Http\Requests\Adjustment\AdjustmentRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Branch\BranchRequest;
use App\Http\Requests\Category\CategoryRequest;
use App\Http\Requests\Handover\HandoverActionRequest;
use App\Http\Requests\Handover\HandoverRequest;
use App\Http\Requests\Handover\TransferHandoverRequest;
use App\Http\Requests\IndexRequest;
use App\Http\Requests\Movement\MovementRequest;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Requests\Rack\RackRequest;
use App\Http\Requests\Role\RoleRequest;
use App\Http\Requests\Unit\UnitRequest;
use App\Http\Requests\User\UserRequest;
use App\Http\Requests\Warehouse\WarehouseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LangTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_errors_are_translated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $errors = $this->getJson('/api/branches?filters[0][field]=zzz&filters[0][operator]=eq&filters[0][value]=1')
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Filter tidak dapat difilter.', $errors['filters.0'][0]);
    }

    public function test_index_lang_is_flat_with_string_values(): void
    {
        foreach (trans('index') as $key => $value) {
            $this->assertIsString($value, "index.{$key} bukan string");
        }

        $this->assertSame('Cabang', __('index.branch'));
        $this->assertSame('Nama Cabang', __('index.branch.name'));
    }

    public function test_login_failure_message_is_translated(): void
    {
        $this->postJson('/api/auth/login', [
            'username' => 'x',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'Username atau password salah.');
    }

    public function test_login_returns_lang(): void
    {
        User::factory()->create([
            'username' => 'a',
            'password' => 'password',
        ]);

        $lang = $this->postJson('/api/auth/login', [
            'username' => 'a',
            'password' => 'password',
        ])
            ->assertOk()
            ->json('data.lang');

        $this->assertSame('Cabang', $lang['branch']);
        $this->assertSame('Nama Cabang', $lang['branch.name']);
        $this->assertSame(':attribute wajib diisi.', $lang['validation.required']);
    }

    public function test_me_returns_lang(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $lang = $this->getJson('/api/auth/me')->assertOk()->json('lang');

        $this->assertSame('Cabang', $lang['branch']);
    }

    public function test_request_attributes_are_all_translated(): void
    {
        $requests = [
            AdjustmentRequest::class,
            BranchRequest::class,
            CategoryRequest::class,
            HandoverActionRequest::class,
            HandoverRequest::class,
            IndexProductRequest::class,
            LoginRequest::class,
            MovementRequest::class,
            ProductRequest::class,
            RackRequest::class,
            RoleRequest::class,
            TransferHandoverRequest::class,
            UnitRequest::class,
            UserRequest::class,
            WarehouseRequest::class,
        ];

        foreach ($requests as $class) {
            foreach ((new $class)->attributes() as $field => $label) {
                $this->assertStringNotContainsString('index.', $label, "{$class}: {$field}");
            }
        }

        $this->assertTrue(is_subclass_of(IndexProductRequest::class, IndexRequest::class));
    }

    public function test_validation_messages_use_attribute_labels(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/branches', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Kode Cabang wajib diisi.')
            ->assertJsonPath('errors.name.0', 'Nama Cabang wajib diisi.');

        $errors = $this->postJson('/api/products', [
            'units' => [
                [
                    'factor' => 0,
                    'unit_id' => 1,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('SKU wajib diisi.', $errors['sku'][0]);
        $this->assertSame('Jumlah harus lebih besar dari 0.', $errors['units.0.factor'][0]);
    }
}
