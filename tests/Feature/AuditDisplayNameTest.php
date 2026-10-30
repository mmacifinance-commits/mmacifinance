<?php

namespace Tests\Feature;

use App\Models\{AuditTrail, Department, User};
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class AuditDisplayNameTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_current_name_is_displayed_without_rewriting_historical_attribution(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'role' => 'super_admin']);
        $center = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $log = AuditTrail::log($center, 'created', $user);
        $user->update(['name' => 'Updated Name']);
        $serialized = $log->fresh()->toArray();
        $this->assertSame('Updated Name', $serialized['display_user_name']);
        $this->assertSame('Original Name', $serialized['user_name']);
        $this->assertSame('Head of Finance', $serialized['user_role']);
        $this->assertArrayNotHasKey('user', $serialized);
        $log->update(['user_id' => null]);
        $this->assertSame('Original Name', $log->fresh()->display_user_name);
        $this->assertDatabaseHas('audit_trails', ['id' => $log->id, 'user_name' => 'Original Name']);
    }
}
