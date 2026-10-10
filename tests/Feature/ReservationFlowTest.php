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
        $user = $this->createUserWithPermissions(['reservation.view', 'reservation.create'], ['role_code' => 'employee']);

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
        $user = $this->createUserWithPermissions(['reservation.view', 'reservation.approve'], ['role_code' => 'staff']);

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

    public function test_administrator_can_supervise_but_cannot_approve_reservations(): void
    {
        $admin = $this->createUserWithPermissions(['reservation.view', 'reservation.create', 'reservation.approve'], ['role_code' => 'admin']);
        $facilityId = DB::table('facilities')->insertGetId([
            'name' => 'Admin Supervision Room', 'type' => 'room', 'capacity' => 10,
            'status' => 'available', 'created_at' => now(),
        ]);
        $reservationId = DB::table('reservations')->insertGetId([
            'facility_id' => $facilityId, 'requested_by' => $admin->id,
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'purpose' => 'Supervised request', 'status' => 'pending', 'created_at' => now(),
        ]);

        $this->actingAs($admin)->get('/reservations')->assertOk()->assertDontSee('New Reservation Request');
        $this->actingAs($admin)->post('/reservations', [
            'facility_id' => $facilityId,
            'start_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(2)->addHour()->format('Y-m-d H:i:s'),
            'purpose' => 'Admin must not submit',
        ])->assertRedirect('/reservations');
        $this->actingAs($admin)->post("/reservations/{$reservationId}/decide", ['decision' => 'approved'])->assertRedirect();

        $this->assertDatabaseMissing('reservations', ['purpose' => 'Admin must not submit']);
        $this->assertDatabaseHas('reservations', ['id' => $reservationId, 'status' => 'pending']);
    }

    public function test_staff_can_assign_an_active_staff_member_as_in_charge(): void
    {
        $staff = $this->createUserWithPermissions(['reservation.view', 'reservation.approve'], ['role_code' => 'staff']);
        $responsible = $this->createUserWithPermissions(['reservation.view'], ['role_code' => 'staff', 'full_name' => 'Responsible Staff']);
        $employee = $this->createUserWithPermissions(['reservation.view'], ['role_code' => 'employee']);
        $facilityId = DB::table('facilities')->insertGetId([
            'name' => 'Assignment Room', 'type' => 'room', 'capacity' => 10,
            'status' => 'available', 'created_at' => now(),
        ]);
        $reservationId = DB::table('reservations')->insertGetId([
            'facility_id' => $facilityId, 'requested_by' => $employee->id,
            'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'purpose' => 'Assigned reservation', 'status' => 'pending', 'created_at' => now(),
        ]);

        $this->actingAs($staff)->post("/reservations/{$reservationId}/in-charge", [
            'in_charge_user_id' => $responsible->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', ['id' => $reservationId, 'in_charge_user_id' => $responsible->id]);
    }
}
