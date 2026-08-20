<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        try {
            $system = SystemSetting::get('branding', []);
            if (! empty($system['logo_path'])) {
                $system['logo_path'] = $this->moveLogo($system['logo_path']);
                SystemSetting::set('branding', $system);
            }
        } catch (\Throwable) {
            // system_settings table may not exist yet
        }

        School::all()->each(function (School $school) {
            $settings = $school->settings ?? [];
            $branding = $settings['branding'] ?? [];

            if (empty($branding['logo_path'])) {
                return;
            }

            $newPath = $this->moveLogo($branding['logo_path']);

            if ($newPath !== $branding['logo_path']) {
                $branding['logo_path'] = $newPath;
                $settings['branding'] = $branding;
                $school->update(['settings' => $settings]);
            }
        });
    }

    public function down(): void {}

    private function moveLogo(string $oldPath): string
    {
        if (! str_starts_with($oldPath, 'public/')) {
            return $oldPath;
        }

        $newPath = substr($oldPath, strlen('public/'));

        try {
            if (Storage::disk('local')->exists($oldPath)) {
                Storage::disk('public')->put($newPath, Storage::disk('local')->get($oldPath));
                Storage::disk('local')->delete($oldPath);

                return $newPath;
            }

            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->move($oldPath, $newPath);

                return $newPath;
            }
        } catch (\Throwable) {
            // File missing on disk; fix the DB reference only
        }

        return $newPath;
    }
};
