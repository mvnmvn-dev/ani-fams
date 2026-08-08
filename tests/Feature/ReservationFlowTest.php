<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class ReservationFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_view_reservations_with_permission(): void
    {
        $user = $this->createUserWithPermissions(['reservation.view']);
        $this->actingAs($user)->get('/reservations')->assertStatus(200);
    }

    public function test_user_cannot_view_reservations_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $this->actingAs($user)->get('/reservations')->assertStatus(403);
    }

    public function test_user_can_create_reservation(): void
    {
        $user = $this->createUserWithPermissions(['reservation.view', 'reservation.create']);

        $facilityId = DB::table('facilities')->insertGetId([
            'name' => 'Conference Room A',
            'type' => 'room',
            'capacity' => 10,
            'status' => 'available',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/reservations', [
            'facility_id' => $facilityId,
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'purpose' => 'Team Meeting',
        ]);

        $response->assertRedirect('/reservations');
        $this->assertDatabaseHas('reservations', [
            'facility_id' => $facilityId,
            'purpose' => 'Team Meeting',
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);
    }

    public function test_user_can_approve_reservation(): void
    {
        $user = $this->createUserWithPermissions(['reservation.view', 'reservation.approve']);

        $facilityId = DB::table('facilities')->insertGetId([
            'name' => 'Conference Room B',
            'type' => 'room',
            'capacity' => 10,
            'status' => 'available',
            'created_at' => now(),
        ]);

        $reservationId = DB::table('reservations')->insertGetId([
            'facility_id' => $facilityId,
            'requested_by' => $user->id,
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'purpose' => 'Team Meeting',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->post("/reservations/{$reservationId}/decide", [
            'decision' => 'approved',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);
    }
}
