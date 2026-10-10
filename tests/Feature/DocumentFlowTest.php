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

    public function test_document_manager_can_archive_a_document_with_a_reason(): void
    {
        $manager = $this->createUserWithPermissions(['document.view', 'document.manage']);
        $documentId = \Illuminate\Support\Facades\DB::table('documents')->insertGetId([
            'title' => 'Test archive record',
            'owner_id' => $manager->id,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->actingAs($manager)->post("/documents/{$documentId}/archive", [
            'archive_reason' => 'Expired contract',
        ])->assertRedirect('/documents');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'status' => 'archived',
            'archive_reason' => 'Expired contract',
        ]);
    }
}
