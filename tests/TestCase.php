<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    private static ?string $keyDirectory = null;

    private static function generateKeys(): string
    {
        $directory = sys_get_temp_dir().'/jwt-test-keys-'.getmypid();

        if (! is_dir($directory)) {
            mkdir($directory);
        }

        $options = ['config' => __DIR__.'/openssl.cnf'];

        foreach (['foreign', 'main'] as $name) {
            $key = openssl_pkey_new([
                ...$options,
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($key, $private, null, $options);

            file_put_contents("{$directory}/{$name}-private.pem", $private);
            file_put_contents("{$directory}/{$name}-public.pem", openssl_pkey_get_details($key)['key']);
        }

        return $directory;
    }

    protected function keyPath(string $name): string
    {
        self::$keyDirectory ??= self::generateKeys();

        return self::$keyDirectory."/{$name}.pem";
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.private_key_path' => $this->keyPath('main-private'),
            'jwt.public_key_path' => $this->keyPath('main-public'),
        ]);
    }

    /**
     * Every permission a route asks for, so a test user can use any endpoint unless told otherwise.
     *
     * @return array<int, string>
     */
    protected function allPermissions(): array
    {
        $abilities = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_contains($middleware, 'Authorize:'))
            ->map(fn (string $middleware) => substr($middleware, strpos($middleware, 'Authorize:') + 10));

        return $abilities->unique()->sort()->values()->all();
    }

    /**
     * @param  array<int, string>|null  $permissions  null grants every route permission
     */
    protected function actingAsUser(User $user, ?array $permissions = null): User
    {
        $user->permissions = $permissions ?? $this->allPermissions();

        $this->actingAs($user);

        return $user;
    }
}
