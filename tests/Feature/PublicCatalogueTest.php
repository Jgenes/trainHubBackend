<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Match ApiSmokeTest: migrate once per test without RefreshDatabase's
        // connection swapping, which some PHP/SQLite runtimes cannot handle.
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    private function providerUser(): User
    {
        return User::create([
            'name'              => 'Catalogue Provider',
            'email'             => 'catalogue-provider@example.test',
            'phone'             => '0712345678',
            'password'          => 'password123',
            'role'              => 'tenant',
            'is_verified'       => true,
            'email_verified_at' => now(),
        ]);
    }

    private function course(User $provider, ?string $status, string $title = 'Catalogue Course'): Course
    {
        return Course::create([
            'provider_id'       => $provider->id,
            'title'             => $title,
            'category'          => 'IT',
            'mode'              => 'Online',
            'short_description' => 'Short',
            'long_description'  => 'Long',
            'contents'          => [],
            'status'            => $status,
        ]);
    }

    public function test_public_scope_keeps_draft_and_legacy_statuses_visible(): void
    {
        $provider = $this->providerUser();

        $visibleStatuses = [
            'Published', 'Draft', 'published', 'PUBLISHED',
            'Approved', 'Pending', 'Active', null,
        ];
        $hiddenStatuses = [
            'Closed', 'suspended', 'Rejected', 'Archived',
            'Unpublished', 'Deleted', 'Inactive', 'Disabled',
        ];

        $visibleIds = [];
        foreach ($visibleStatuses as $index => $status) {
            $visibleIds[] = $this->course($provider, $status, "Visible {$index}")->id;
        }
        foreach ($hiddenStatuses as $index => $status) {
            $this->course($provider, $status, "Hidden {$index}");
        }

        $publicIds = Course::published()->pluck('id')->all();

        $this->assertEqualsCanonicalizing($visibleIds, $publicIds);
    }

    public function test_both_catalogue_endpoints_return_the_visible_courses(): void
    {
        $provider = $this->providerUser();

        $draft = $this->course($provider, 'Draft', 'Draft course');
        $legacy = $this->course($provider, 'APPROVED', 'Legacy course');
        $published = $this->course($provider, 'Published', 'Published course');
        $closed = $this->course($provider, 'Closed', 'Closed course');

        foreach (['/api/public-courses', '/api/training'] as $endpoint) {
            $response = $this->getJson($endpoint)->assertOk();

            $response->assertJsonCount(3)
                ->assertJsonFragment(['id' => $draft->id])
                ->assertJsonFragment(['id' => $legacy->id])
                ->assertJsonFragment(['id' => $published->id])
                ->assertJsonMissing(['id' => $closed->id]);
        }

        $this->getJson('/api/catalogue-health')
            ->assertOk()
            ->assertJsonPath('code', 'public-scope-v2')
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total_courses', 4)
            ->assertJsonPath('public_courses', 3)
            ->assertJsonPath('hidden_courses', 1);
    }

    public function test_catalogue_health_distinguishes_no_rows_from_all_hidden_rows(): void
    {
        $this->getJson('/api/catalogue-health')
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('total_courses', 0)
            ->assertJsonPath('public_courses', 0)
            ->assertJsonPath(
                'hint',
                'No course rows exist. Create a course in the provider workspace or seed real catalogue data.'
            );

        $this->course($this->providerUser(), 'Suspended', 'Withdrawn course');

        $this->getJson('/api/catalogue-health')
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('total_courses', 1)
            ->assertJsonPath('public_courses', 0)
            ->assertJsonPath(
                'hint',
                'Courses exist, but all of them have a hidden/withdrawn status. Normalise or publish their statuses.'
            );
    }

    public function test_status_migration_canonicalises_legacy_rows(): void
    {
        $provider = $this->providerUser();

        $published = $this->course($provider, 'approved', 'Legacy published');
        $hidden = $this->course($provider, 'suspended', 'Legacy withdrawn');
        $unknown = $this->course($provider, 'Live', 'Legacy unknown');
        $missing = $this->course($provider, null, 'Missing status');

        // The migration already ran in setUp(); running it again must be safe.
        $migration = require database_path(
            'migrations/2026_11_02_000001_normalize_course_statuses.php'
        );
        $migration->up();

        $this->assertDatabaseHas('courses', ['id' => $published->id, 'status' => 'Published']);
        $this->assertDatabaseHas('courses', ['id' => $hidden->id, 'status' => 'Closed']);
        $this->assertDatabaseHas('courses', ['id' => $unknown->id, 'status' => 'Draft']);
        $this->assertDatabaseHas('courses', ['id' => $missing->id, 'status' => 'Draft']);
    }

    public function test_top_providers_counts_only_public_courses(): void
    {
        $owner = $this->providerUser();

        Provider::create([
            'id'                => (string) Str::uuid(),
            'legal_name'        => 'Catalogue Training Ltd',
            'provider_type'     => 'Private',
            'region'            => 'Dar es Salaam',
            'contact_name'      => 'Jane Doe',
            'contact_phone'     => '0712345678',
            'contact_email'     => 'provider@example.test',
            'status'            => 'APPROVED',
            'provider_slug'     => 'catalogue-training-ltd',
            'created_by'        => $owner->id,
            'user_id'           => $owner->id,
        ]);

        $this->course($owner, 'Draft', 'Visible course');
        $this->course($owner, 'Closed', 'Hidden course');

        $this->getJson('/api/top-providers')
            ->assertOk()
            ->assertJsonPath('0.courses_count', 1)
            ->assertJsonMissingPath('0.user_id');
    }
}
