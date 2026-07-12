<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageAttachmentsTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_message_with_images_stores_attachments_and_serves_them(): void
    {
        Storage::fake(config('filesystems.default'));
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $response = $this->actingAs($owner)->post(
            "/api/mobile/conversations/{$dm->id}/messages",
            ['body' => 'look', 'images' => [UploadedFile::fake()->image('pic.jpg', 640, 480)]],
            ['Accept' => 'application/json'],
        )->assertStatus(201);

        $attachment = $response->json('message.attachments.0');
        $this->assertSame(640, $attachment['width']);
        $this->assertSame(480, $attachment['height']);
        $this->assertDatabaseHas('message_attachments', ['id' => $attachment['id']]);

        // Clients construct the binary URL from message id + attachment id.
        $url = "/api/mobile/messages/{$response->json('message.id')}/attachments/{$attachment['id']}";

        // Participant can fetch the binary.
        $this->actingAs($member)->get($url)->assertOk();

        // Non-participant cannot.
        $outsider = \App\Models\User::factory()->create();
        $this->actingAs($outsider)->get($url)->assertStatus(403);
    }

    public function test_image_only_message_needs_no_body(): void
    {
        Storage::fake(config('filesystems.default'));
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $this->actingAs($owner)->post(
            "/api/mobile/conversations/{$channel->id}/messages",
            ['images' => [UploadedFile::fake()->image('solo.png')]],
            ['Accept' => 'application/json'],
        )->assertStatus(201)->assertJsonPath('message.body', null);
    }

    public function test_more_than_four_images_is_rejected(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $images = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 5));

        $this->actingAs($owner)->post(
            "/api/mobile/conversations/{$channel->id}/messages",
            ['images' => $images],
            ['Accept' => 'application/json'],
        )->assertStatus(422);
    }

    public function test_non_image_files_are_rejected(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $this->actingAs($owner)->post(
            "/api/mobile/conversations/{$channel->id}/messages",
            ['images' => [UploadedFile::fake()->create('evil.pdf', 100, 'application/pdf')]],
            ['Accept' => 'application/json'],
        )->assertStatus(422);
    }

    public function test_deleted_messages_attachments_are_not_served(): void
    {
        Storage::fake(config('filesystems.default'));
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $response = $this->actingAs($owner)->post(
            "/api/mobile/conversations/{$channel->id}/messages",
            ['images' => [UploadedFile::fake()->image('gone.jpg')]],
            ['Accept' => 'application/json'],
        )->assertStatus(201);
        $messageId = $response->json('message.id');
        $url = "/api/mobile/messages/{$messageId}/attachments/" . $response->json('message.attachments.0.id');

        $this->actingAs($owner)->deleteJson("/api/mobile/messages/{$messageId}")->assertStatus(204);

        $this->actingAs($owner)->get($url)->assertStatus(404);
    }
}
