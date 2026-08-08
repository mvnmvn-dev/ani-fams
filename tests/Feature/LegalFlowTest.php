<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class LegalFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_view_legal_with_permission(): void
    {
        $user = $this->createUserWithPermissions(['legal.view']);
        $this->actingAs($user)->get('/legal')->assertStatus(200);
    }

    public function test_user_cannot_view_legal_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/legal')->assertStatus(403);
    }
}
