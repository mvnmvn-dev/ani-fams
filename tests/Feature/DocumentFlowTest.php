<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_view_documents_with_permission(): void
    {
        $user = $this->createUserWithPermissions(['document.view']);
        $this->actingAs($user)->get('/documents')->assertStatus(200);
    }

    public function test_user_cannot_view_documents_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/documents')->assertStatus(403);
    }
}
