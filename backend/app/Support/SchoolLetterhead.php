<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Support\Facades\Storage;

/**
 * Everything a printed document's letterhead needs, resolved from one school.
 *
 * ReceiptPdf, ReportPdf and StudentStatementPdf each carried their own copy of
 * this resolution logic, so a change to branding had to be made in three places
 * and the postal/contact details existed in none of them.
 *
 * Display names and the logo live in settings.branding; the postal block and the
 * extra phone numbers live in settings.letterhead. The primary phone and email
 * are real columns on the school, because SMS and other features read them.
 */
class SchoolLetterhead
{
    public static function for(?School $school): array
    {
        $settings = $school?->settings ?? [];
        $branding = $settings['branding'] ?? [];
        $letter = $settings['letterhead'] ?? [];

        return [
            'name' => $branding['app_name'] ?? ($school?->name ?? 'ShulePay'),
            'tagline' => $branding['app_tagline'] ?? 'nexoryaTECH',
            'logo' => self::logo($branding),

            // Postal block, printed as consecutive lines exactly as entered.
            'address_lines' => array_values(array_filter([
                $letter['address_line1'] ?? null,
                $letter['address_line2'] ?? null,
                $letter['po_box'] ?? null,
                $letter['city_country'] ?? null,
            ], fn ($v) => filled($v))),

            // Primary phone is a column; the extras are letterhead-only.
            'phones' => array_values(array_filter([
                $school?->phone,
                $letter['phone_2'] ?? null,
                $letter['phone_3'] ?? null,
            ], fn ($v) => filled($v))),

            'email' => $school?->email,
            'website' => $letter['website'] ?? $school?->website,
            'motto' => $letter['motto'] ?? $school?->motto,
        ];
    }

    /**
     * Logo as a data: URI, since DomPDF cannot fetch remote assets.
     *
     * Read from the 'public' disk explicitly. BrandingController uploads with
     * ->store(..., 'public'), but this used a bare Storage::exists(), which goes to
     * the default disk (FILESYSTEM_DISK, 'local' here). That looked in
     * storage/app/ while the file sits in storage/app/public/, so exists() was
     * always false and the logo was silently dropped from every PDF.
     */
    private static function logo(array $branding): ?string
    {
        $path = $branding['logo_path'] ?? null;
        if (! $path) {
            return null;
        }

        // Older uploads were recorded with a leading "public/".
        $path = preg_replace('#^public/#', '', $path);
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
    }
}
