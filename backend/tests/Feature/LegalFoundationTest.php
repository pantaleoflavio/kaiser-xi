<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalFoundationTest extends TestCase
{
    public function test_public_legal_routes_and_global_links_are_defined(): void
    {
        $routes = file_get_contents(base_path('../frontend/src/routes/AppRoutes.tsx'));
        $footer = file_get_contents(base_path('../frontend/src/components/LegalFooter.tsx'));

        $this->assertStringContainsString('path="privacy"', $routes);
        $this->assertStringContainsString('path="impressum"', $routes);
        $this->assertStringContainsString('path="privacy-acknowledgement"', $routes);
        $this->assertStringContainsString('to="/privacy"', $footer);
        $this->assertStringContainsString('to="/impressum"', $footer);
    }

    public function test_legal_translations_exist_in_every_supported_language(): void
    {
        foreach (['en', 'de', 'it'] as $language) {
            $messages = json_decode(file_get_contents(base_path("../frontend/src/i18n/{$language}.json")), true, flags: JSON_THROW_ON_ERROR);
            $this->assertNotEmpty($messages['legal']['privacyTitle']);
            $this->assertNotEmpty($messages['legal']['imprintTitle']);
            $this->assertNotEmpty($messages['legal']['privacy']['deletion']['body']);
            $this->assertNotEmpty($messages['auth']['register']['privacyPrefix']);
        }
    }
}
