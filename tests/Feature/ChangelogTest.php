<?php

namespace Tests\Feature;

use App\Services\ChangelogService;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_current_version_matches_first_release(): void
    {
        $changelog = app(ChangelogService::class);
        $releases = $changelog->all();

        $this->assertNotEmpty($releases);
        $this->assertSame($releases[0]['version'], $changelog->current());
        $this->assertSame('v'.$changelog->current(), $changelog->currentLabel());
    }

    public function test_changelog_page_requires_admin(): void
    {
        $this->withoutVite();

        $this->get('/changelog')->assertRedirect();
    }

    public function test_changelog_page_shows_current_version_for_admin(): void
    {
        $this->withoutVite();

        $label = app(ChangelogService::class)->currentLabel();

        $response = $this->withSession(['is_admin' => true])->get('/changelog');

        $response->assertOk();
        $response->assertSee($label, false);
        $response->assertSee('История версий', false);
    }

    public function test_layout_version_menu_is_rendered_on_changelog_page(): void
    {
        $this->withoutVite();

        $response = $this->withSession(['is_admin' => true])->get('/changelog');

        $response->assertOk();
        $response->assertSee('product-version-menu', false);
        $response->assertSee('Все версии', false);
    }
}
