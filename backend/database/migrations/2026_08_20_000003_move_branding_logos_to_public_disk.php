<?php

use App\Models\School;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Move existing branding logos from the private local disk (old code stored
 * them as "public/branding/..." on the local disk) to the public disk
 * ("branding/..." under storage/app/public) so they are web-accessible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── System branding ──────────────────────────────────────────────────
        try {
            $system = SystemSetting::get('branding', []);
            if (! empty($system['logo_path'])) {
                $system['logo_path'] = $this->migrate($system['logo_path']);
                SystemSetting::set('branding', $system);
            }
        } catch (\Throwable) {
            // system_settings table may not exist yet — skip
        }

        // ── Per-school branding ───────────────────────────────────────────────
        School::all()->each(function (School $school) {
            $settings = $school->settings ?? [];
            $branding = $settings['branding'] ?? [];

            if (empty($branding['logo_path'])) {
                return;
            }

            $newPath = $this->migrate($branding['logo_path']);
            if ($newPath !== $branding['logo_path']) {
                $branding['logo_path'] = $newPath;
                $settings['branding'] = $branding;
                $school->update(['settings' => $settings]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible — old files were on the wrong disk
    }

    /**
     * Move a file from the old path (local disk, "public/branding/...") to
     * the public disk ("branding/..."). Returns the new path.
     */
    private function migrate(string $oldPath): string
    {
        // Already on the correct path (no "public/" prefix)
        if (! str_starts_with($oldPath, 'public/')) {
            return $oldPath;
        }

        $newPath = preg_replace('#^public/#', '', $oldPath);

        try {
            // Try reading from the local private disk first
            if (Storage::disk('local')->exists($oldPath)) {
                $contents = Storage::disk('local')->get($oldPath);
                Storage::disk('public')->put($newPath, $contents);
                Storage::disk('local')->delete($oldPath);

                return $newPath;
            }

            // Also check the public disk in case it was put there with the old path
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->move($oldPath, $newPath);

                return $newPath;
            }
        } catch (\Throwable) {
            // File may not exist on disk at all; just fix the path reference
        }

        return $newPath; // Fix the DB path even if the file is gone
    }
};
