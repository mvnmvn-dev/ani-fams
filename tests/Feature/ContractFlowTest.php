<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class ContractFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_view_contracts_with_permission(): void
    {
        $user = $this->createUserWithPermissions(['contract.view']);
        $this->actingAs($user)->get('/contracts')->assertStatus(200);
    }

    public function test_user_cannot_view_contracts_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/contracts')->assertStatus(403);
    }
}
