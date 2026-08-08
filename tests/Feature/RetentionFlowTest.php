<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class RetentionFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_view_retention_with_permission(): void
    {
        $user = $this->createUserWithPermissions(['retention.view']);
        $this->actingAs($user)->get('/retention')->assertStatus(200);
    }

    public function test_user_cannot_view_retention_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/retention')->assertStatus(403);
    }
}
