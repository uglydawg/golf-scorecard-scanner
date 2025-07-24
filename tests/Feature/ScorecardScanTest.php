<?php

declare(strict_types=1);

namespace Tests\Feature;

use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScorecardScanTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_authenticated_user_can_upload_scorecard_image(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('scorecard.jpg', 800, 600);

        $response = $this->postJson('/api/scorecard-scans', [
            'image' => $file
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                    'original_image_url',
                    'created_at'
                ]
            ]);

        $this->assertDatabaseHas('scorecard_scans', [
            'user_id' => $user->id,
            'status' => 'completed' // Our mock service completes immediately
        ]);
    }

    public function test_unauthenticated_user_cannot_upload_scorecard(): void
    {
        $file = UploadedFile::fake()->image('scorecard.jpg');

        $response = $this->postJson('/api/scorecard-scans', [
            'image' => $file
        ]);

        $response->assertStatus(401);
    }

    public function test_invalid_file_upload_fails_validation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/scorecard-scans', [
            'image' => 'not-a-file'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_user_can_view_their_own_scorecard_scan(): void
    {
        $user = User::factory()->create();
        $scan = ScorecardScan::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/scorecard-scans/{$scan->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'created_at'
                ]
            ]);
    }

    public function test_user_cannot_view_other_users_scorecard_scan(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $scan = ScorecardScan::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/scorecard-scans/{$scan->id}");

        $response->assertStatus(403);
    }
}
