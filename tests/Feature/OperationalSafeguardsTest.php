<?php

namespace Tests\Feature;

use App\Models\Income;
use App\Models\User;
use App\Services\SystemBackupService;
use Illuminate\Support\Facades\Storage;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class OperationalSafeguardsTest extends TestCase
{
    use SafeRefreshDatabase;

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
