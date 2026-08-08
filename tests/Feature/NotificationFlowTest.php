<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_authenticated_user_can_open_notifications(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/notifications')->assertStatus(200);
    }
}
