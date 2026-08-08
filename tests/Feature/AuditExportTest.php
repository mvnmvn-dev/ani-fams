<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class AuditExportTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_export_audit_csv_with_permission(): void
    {
        $user = $this->createUserWithPermissions(['audit.view']);
        $this->actingAs($user)->get('/audit-logs/export')->assertStatus(200);
    }

    public function test_user_cannot_export_audit_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/audit-logs/export')->assertStatus(403);
    }
}
