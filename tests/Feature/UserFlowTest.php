<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class UserFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_list_users_with_manage_permission(): void
    {
        $user = $this->createUserWithPermissions(['users.manage']);
        $this->actingAs($user)->get('/users')->assertStatus(200);
    }

    public function test_user_cannot_list_users_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/users')->assertStatus(403);
    }
}
