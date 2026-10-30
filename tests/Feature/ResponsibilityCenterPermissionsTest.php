<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class ResponsibilityCenterPermissionsTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_budget_officer_can_manage_responsibility_centers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'budget_officer']));

        $this->get('/departments')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Departments/Index')->where('canManageResponsibilityCenters', true));

        $this->post('/departments', ['name' => 'Budget Office', 'code' => 'BUD'])
            ->assertRedirect('/departments')->assertSessionHasNoErrors();
        $department = Department::where('code', 'BUD')->firstOrFail();

        $this->put("/departments/{$department->id}", ['name' => 'Budget Planning', 'code' => 'PLAN'])
            ->assertRedirect('/departments')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Budget Planning', 'code' => 'PLAN']);

        $this->get('/departments/export-csv')->assertOk();
        $csv = "responsibility_center,code\nImported Center,IMP\n";
        $this->post('/imports/departments/preview', [
            'csv_file' => UploadedFile::fake()->createWithContent('centers.csv', $csv),
        ])->assertOk()->assertJsonPath('valid_count', 1);
        $this->post('/departments/import-csv', [
            'csv_file' => UploadedFile::fake()->createWithContent('centers.csv', $csv),
        ])->assertRedirect('/departments')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', ['name' => 'Imported Center', 'code' => 'IMP']);

        $this->deleteJson("/departments/{$department->id}")->assertForbidden();
        $this->assertDatabaseHas('departments', ['id' => $department->id]);
        $this->getJson('/system/backup/export')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->delete("/departments/{$department->id}")->assertRedirect('/departments');
        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_other_staff_remain_read_only(): void
    {
        $department = Department::create(['name' => 'Protected Center', 'code' => 'PROT']);
        foreach (['cashier', 'auditor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/departments')->assertOk()->assertInertia(fn ($page) => $page
                ->where('canManageResponsibilityCenters', false));
            $this->postJson('/departments', ['name' => 'Unauthorized', 'code' => 'NO'])->assertForbidden();
            $this->putJson("/departments/{$department->id}", ['name' => 'Unauthorized', 'code' => 'NO'])->assertForbidden();
            $this->deleteJson("/departments/{$department->id}")->assertForbidden();
            $this->getJson('/departments/export-csv')->assertForbidden();
            $this->postJson('/departments/import-csv')->assertForbidden();
            $this->postJson('/imports/departments/preview')->assertForbidden();
        }
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'code' => 'PROT']);
    }
}
