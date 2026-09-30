<?php

namespace Tests\Feature;

use App\Models\Income;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\SystemBackupService;
use Illuminate\Support\Facades\Storage;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class OperationalSafeguardsTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_reconciliation_uses_actual_receipts_and_preserves_actor_and_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);
        Income::create(['income_no' => 'ACTUAL', 'receipt_no' => 'R1', 'source' => 'Fees', 'description' => 'Receipt', 'amount' => 100, 'date_encoded' => '2026-01-01']);
        Income::create(['income_no' => 'FORECAST', 'source' => 'Fees', 'description' => 'Forecast', 'amount' => 500, 'date_encoded' => '2026-01-01']);
        $data = ['as_of_date' => '2026-01-02', 'opening_balance' => 20, 'actual_cash' => 30, 'bank_balance' => 100, 'deposits_in_transit' => 10, 'outstanding_payments' => 20];
        $this->actingAs($user)->post('/reconciliations', $data + ['created_by_name' => 'Spoofed'])->assertSessionHasNoErrors();
        $record = Reconciliation::firstOrFail();
        $this->assertEquals(120, $record->book_balance);
        $this->assertEquals(0, $record->difference);
        $this->assertSame($user->name, $record->created_by_name);
        Income::where('income_no', 'ACTUAL')->update(['amount' => 200]);
        $this->assertEquals(120, $record->fresh()->book_balance);
        $this->post('/reconciliations', $data)->assertSessionHasErrors('notes');
        $this->post('/reconciliations', $data + ['notes' => 'Investigating unrecorded deposit'])->assertSessionHasNoErrors();
        $this->assertEquals(-100, Reconciliation::latest('id')->first()->difference);
        $this->actingAs(User::factory()->create(['role' => 'auditor']))->postJson('/reconciliations', $data)->assertForbidden();
        $this->get('/reconciliations')->assertOk();
    }

    public function test_scheduled_backup_is_stored_privately_and_corruption_is_detected(): void
    {
        Storage::fake('backup_test');
        config(['backup.disk' => 'backup_test']);
        Storage::disk('backup_test')->put('scheduled-backups/old.zip', 'old');
        touch(Storage::disk('backup_test')->path('scheduled-backups/old.zip'), now()->subDays(31)->timestamp);
        $this->artisan('system:backup')->assertSuccessful();
        Storage::disk('backup_test')->assertMissing('scheduled-backups/old.zip');
        $files = Storage::disk('backup_test')->files('scheduled-backups');
        $this->assertCount(1, $files);
        $path = Storage::disk('backup_test')->path($files[0]);
        app(SystemBackupService::class)->verifyArchive($path);
        $zip = new \ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $entry = reset($manifest['tables']);
        $zip->addFromString($entry['file'], "{}\n");
        $zip->close();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('checksum mismatch');
        app(SystemBackupService::class)->verifyArchive($path);
    }

    public function test_production_never_exposes_demo_accounts_or_otp_even_with_debug_enabled(): void
    {
        $user = User::factory()->create(['otp_code' => '123456', 'otp_expires_at' => now()->addMinutes(10)]);
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);
        $this->get('/login')->assertInertia(fn ($page) => $page->has('demoUsers', 0));
        $this->withSession(['2fa_user_id' => $user->id])->get('/2fa/verify')
            ->assertInertia(fn ($page) => $page->where('devOtp', null));
    }

    public function test_otp_guessing_is_rate_limited(): void
    {
        $user = User::factory()->create(['otp_code' => '123456', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->withSession(['2fa_user_id' => $user->id]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/2fa/verify', ['otp' => '000000'])->assertSessionHasErrors('otp');
        }
        $this->postJson('/2fa/verify', ['otp' => '123456'])->assertStatus(429);
        $this->assertGuest();
    }
}
