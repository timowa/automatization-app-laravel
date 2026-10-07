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

    public function test_changelog_page_shows_history_and_marks_seen(): void
    {
        $this->withoutVite();

        $changelog = app(ChangelogService::class);
        $label = $changelog->currentLabel();
        $current = $changelog->current();

        $response = $this->withSession(['is_admin' => true])->get('/changelog');

        $response->assertOk();
        $response->assertSee($label, false);
        $response->assertSee('История версий', false);
        $response->assertSee('product-version-menu', false);
        $response->assertSessionHas(ChangelogService::SESSION_SEEN_VERSION_KEY, $current);
    }

    public function test_should_show_new_badge_until_marked_seen(): void
    {
        $changelog = app(ChangelogService::class);

        $this->assertTrue($changelog->shouldShowNewBadge());

        $changelog->markCurrentVersionSeen();

        $this->assertFalse($changelog->shouldShowNewBadge());
        $this->assertSame(
            $changelog->current(),
            session(ChangelogService::SESSION_SEEN_VERSION_KEY)
        );
    }

    public function test_mark_seen_endpoint_stores_version_in_session(): void
    {
        $this->withoutVite();

        $current = app(ChangelogService::class)->current();

        $response = $this->withSession(['is_admin' => true])->postJson('/changelog/seen');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'version' => $current,
        ]);
        $response->assertSessionHas(ChangelogService::SESSION_SEEN_VERSION_KEY, $current);
    }

    public function test_badge_hidden_after_version_already_seen_in_session(): void
    {
        $this->withoutVite();

        $current = app(ChangelogService::class)->current();

        $response = $this->withSession([
            'is_admin' => true,
            ChangelogService::SESSION_SEEN_VERSION_KEY => $current,
        ])->get('/changelog');

        $response->assertOk();
        $this->assertStringNotContainsString('id="version-new-badge"', $response->getContent());
    }

    public function test_version_menu_partial_renders_badge_flag(): void
    {
        $withBadge = view('partials.version-menu', [
            'productVersion' => 'v1.1',
            'recentReleases' => [],
            'showVersionNewBadge' => true,
        ])->render();

        $this->assertStringContainsString('id="version-new-badge"', $withBadge);
        $this->assertStringContainsString('changelog/seen', $withBadge);

        $withoutBadge = view('partials.version-menu', [
            'productVersion' => 'v1.1',
            'recentReleases' => [],
            'showVersionNewBadge' => false,
        ])->render();

        $this->assertStringNotContainsString('id="version-new-badge"', $withoutBadge);
    }
}
