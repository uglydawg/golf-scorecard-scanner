<?php

declare(strict_types=1);

use App\Models\ScorecardScan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    DB::beginTransaction();
    Storage::fake('public');
});

afterEach(function () {
    DB::rollBack();
});

it('allows authenticated user to upload scorecard image', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->image('scorecard.jpg', 800, 600);

    $response = $this->postJson('/api/scorecard-scans', [
        'image' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'status',
                'original_image_url',
                'created_at',
            ],
        ]);

    $this->assertDatabaseHas('scorecard_scans', [
        'user_id' => $user->id,
        'status' => 'completed',
    ]);
});

it('prevents unauthenticated user from uploading scorecard', function () {
    $file = UploadedFile::fake()->image('scorecard.jpg');

    $response = $this->postJson('/api/scorecard-scans', [
        'image' => $file,
    ]);

    $response->assertStatus(401);
});

it('fails validation for invalid file upload', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/scorecard-scans', [
        'image' => 'not-a-file',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

it('allows user to view their own scorecard scan', function () {
    $user = User::factory()->create();
    $scan = ScorecardScan::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/scorecard-scans/{$scan->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'status',
                'created_at',
            ],
        ]);
});

it('prevents user from viewing other users scorecard scan', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $scan = ScorecardScan::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/scorecard-scans/{$scan->id}");

    $response->assertStatus(403);
});
