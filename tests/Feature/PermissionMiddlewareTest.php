<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use CreatesTestUsers;

    public function test_middleware_allows_access_when_user_has_permission(): void
    {
        $user = $this->createUserWithPermissions(['document.view']);

        $this->actingAs($user)->get('/documents')->assertStatus(200);
    }

    public function test_middleware_denies_access_when_user_lacks_permission(): void
    {
        $user = $this->createUserWithPermissions(['document.view']);

        $this->actingAs($user)->get('/retention')->assertStatus(403);
    }

    public function test_middleware_redirects_unauthenticated_users(): void
    {
        $this->get('/reservations')->assertRedirect('/login');
    }
}
