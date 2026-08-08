<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class AdvancedFeaturesTest extends TestCase
{
    use CreatesTestUsers;

    public function test_recurring_reservation_creates_series(): void
    {
        $user = $this->createUserWithPermissions(['reservation.view', 'reservation.create']);
        $facilityId = DB::table('facilities')->insertGetId([
            'name' => 'Recurring Room',
            'type' => 'room',
            'capacity' => 8,
            'status' => 'available',
            'created_at' => now(),
        ]);

        $start = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');
        $end = now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s');
        $until = now()->addDays(15)->format('Y-m-d');

        $this->actingAs($user)->post('/reservations', [
            'facility_id' => $facilityId,
            'purpose' => 'Weekly standup',
            'start_datetime' => $start,
            'end_datetime' => $end,
            'recurrence_rule' => 'weekly',
            'recurrence_until' => $until,
        ])->assertRedirect('/reservations');

        $rows = DB::table('reservations')->where('purpose', 'Weekly standup')->get();
        $this->assertGreaterThanOrEqual(2, $rows->count());
        $this->assertNotNull($rows->first()->series_id);
    }

    public function test_document_content_search_finds_extracted_text(): void
    {
        $user = $this->createUserWithPermissions(['document.view', 'document.upload']);
        $docId = DB::table('documents')->insertGetId([
            'title' => 'Policy Memo',
            'description' => null,
            'document_number' => 'DOC-TEST-1',
            'owner_id' => $user->id,
            'confidentiality' => 'internal',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $versionId = DB::table('document_versions')->insertGetId([
            'document_id' => $docId,
            'version_no' => 1,
            'file_path' => '/uploads/dummy.txt',
            'checksum' => null,
            'content_text' => 'UniquePhraseAlpha facilities compliance checklist',
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'change_notes' => 'seed',
        ]);
        DB::table('documents')->where('id', $docId)->update(['current_version_id' => $versionId]);

        $this->actingAs($user)
            ->get('/documents?q=UniquePhraseAlpha')
            ->assertStatus(200)
            ->assertSee('UniquePhraseAlpha');
    }

    public function test_maintenance_block_can_be_scheduled(): void
    {
        $user = $this->createUserWithPermissions(['reservation.view', 'reservation.approve']);
        $facilityId = DB::table('facilities')->insertGetId([
            'name' => 'Maint Hall',
            'type' => 'venue',
            'capacity' => 20,
            'status' => 'available',
            'created_at' => now(),
        ]);

        $this->actingAs($user)->post('/reservations/maintenance', [
            'facility_id' => $facilityId,
            'purpose' => 'Floor waxing',
            'start_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(2)->addHours(4)->format('Y-m-d H:i:s'),
        ])->assertRedirect('/reservations');

        $this->assertDatabaseHas('reservations', [
            'facility_id' => $facilityId,
            'purpose' => 'Floor waxing',
            'status' => 'approved',
            'is_maintenance' => 1,
        ]);
    }
}
