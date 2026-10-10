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

    public function test_legal_manager_can_record_employee_departure_with_a_source_document(): void
    {
        $manager = $this->createUserWithPermissions(['legal.view', 'legal.manage']);
        $categoryId = \Illuminate\Support\Facades\DB::table('document_categories')->insertGetId([
            'name' => 'Legal Records',
        ]);
        $documentId = \Illuminate\Support\Facades\DB::table('documents')->insertGetId([
            'category_id' => $categoryId,
            'title' => 'Departure source fixture',
            'owner_id' => $manager->id,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->actingAs($manager)->post('/legal/employee-departures', [
            'employee_name' => 'Test Employee',
            'departure_type' => 'resigned',
            'effective_date' => '2026-10-01',
            'source_document_id' => $documentId,
            'notes' => 'Captured from source record fixture.',
        ])->assertRedirect('/legal');

        $this->assertDatabaseHas('legal_employee_departures', [
            'employee_name' => 'Test Employee',
            'departure_type' => 'resigned',
            'effective_date' => '2026-10-01',
            'source_document_id' => $documentId,
            'recorded_by' => $manager->id,
        ]);
    }
}
