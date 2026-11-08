<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrationScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('integration_clients')) {
            Schema::create('integration_clients', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('system_code', 20)->unique();
                $table->string('name', 150);
                $table->string('key_prefix', 12)->unique();
                $table->string('key_hash');
                $table->text('signing_secret')->nullable();
                $table->json('scopes');
                $table->string('allowed_ips', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
        \Illuminate\Support\Facades\DB::table('integration_clients')->insert([
            'system_code' => 'hr1', 'name' => 'HR1 test', 'key_prefix' => 'fams_test01',
            'key_hash' => Hash::make('test-secret'), 'scopes' => json_encode(['reference:read']),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_generic_reference_read_scope_cannot_read_users_feed(): void
    {
        $this->withToken('fams_test01.test-secret')
            ->getJson('/api/integration/v1/data/users')
            ->assertForbidden();
    }
}
