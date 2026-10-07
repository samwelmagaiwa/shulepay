<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Printed documents must follow the language chosen in the app. They used to be
 * written in Swahili inside the Blade templates, so a receipt stayed Swahili for
 * a user working in English.
 */
class LocaleTest extends TestCase
{
    private function pass(array $headers): string
    {
        $request = Request::create('/api/anything', 'GET', server: array_combine(
            array_map(fn ($k) => 'HTTP_'.strtoupper(str_replace('-', '_', $k)), array_keys($headers)),
            array_values($headers),
        ));

        (new SetLocale)->handle($request, fn () => response('ok'));

        return app()->getLocale();
    }

    public function test_the_apps_language_header_sets_the_locale(): void
    {
        $this->assertSame('sw', $this->pass(['X-Lang' => 'sw']));
        $this->assertSame('en', $this->pass(['X-Lang' => 'en']));
    }

    public function test_accept_language_is_used_when_no_app_header_is_sent(): void
    {
        $this->assertSame('sw', $this->pass(['Accept-Language' => 'sw-TZ,sw;q=0.9']));
    }

    public function test_an_unsupported_language_leaves_the_default_in_place(): void
    {
        app()->setLocale('en');

        $this->assertSame('en', $this->pass(['X-Lang' => 'fr']));
        $this->assertSame('en', $this->pass([]));
    }

    public function test_receipt_labels_exist_in_both_languages(): void
    {
        $keys = array_keys(require base_path('lang/sw/pdf.php'));

        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            app()->setLocale('sw');
            $sw = __('pdf.'.$key);
            app()->setLocale('en');
            $en = __('pdf.'.$key);

            // A missing key returns the key itself — that is the failure to catch.
            $this->assertNotSame('pdf.'.$key, $sw, "Swahili label missing: {$key}");
            $this->assertNotSame('pdf.'.$key, $en, "English label missing: {$key}");
        }
    }
}
