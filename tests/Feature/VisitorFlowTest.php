<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class VisitorFlowTest extends TestCase
{
    use CreatesTestUsers;

    public function test_user_can_view_visitors_with_permission(): void
    {
        $user = $this->createUserWithPermissions(['visitor.view']);
        $this->actingAs($user)->get('/visitors')->assertStatus(200);
    }

    public function test_user_can_pre_register_visitor(): void
    {
        $user = $this->createUserWithPermissions(['visitor.view', 'visitor.register']);

        $employeeId = DB::table('employees')->insertGetId([
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'position' => 'Host',
            'email' => $user->email,
        ]);

        $response = $this->actingAs($user)->post('/visitors', [
            'full_name' => 'John Doe',
            'company' => 'Acme Corp',
            'email' => 'john@acme.com',
            'purpose' => 'Business Meeting',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'host_employee_id' => $employeeId,
        ]);

        $response->assertRedirect('/visitors');
        $this->assertDatabaseHas('visitors', [
            'full_name' => 'John Doe',
            'company' => 'Acme Corp',
        ]);
        $this->assertDatabaseHas('visits', [
            'purpose' => 'Business Meeting',
            'status' => 'pending_approval',
            'host_employee_id' => $employeeId,
        ]);
    }

    public function test_user_can_check_in_visitor(): void
    {
        $user = $this->createUserWithPermissions(['visitor.view', 'visitor.checkin']);

        $employeeId = DB::table('employees')->insertGetId([
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'position' => 'Host',
            'email' => $user->email,
        ]);

        $visitorId = DB::table('visitors')->insertGetId([
            'full_name' => 'Jane Smith',
            'company' => 'Globex',
            'email' => 'jane@globex.com',
            'is_watchlisted' => 0,
            'created_at' => now(),
        ]);

        $visitId = DB::table('visits')->insertGetId([
            'visitor_id' => $visitorId,
            'host_employee_id' => $employeeId,
            'scheduled_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'purpose' => 'Interview',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($user)->post("/visitors/{$visitId}/check-in");

        $response->assertRedirect();
        $this->assertDatabaseHas('visits', [
            'id' => $visitId,
            'status' => 'checked_in',
        ]);
    }
}
