<?php

namespace Tests\Feature\Services\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class MessagePreviewTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_preview_snippet_trims_truncates_and_falls_back_to_photo(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $topic = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));

        $text  = $topic->messages()->create(['user_id' => $owner->id, 'body' => '  hello there  ']);
        $long  = $topic->messages()->create(['user_id' => $owner->id, 'body' => str_repeat('a', 100)]);
        $empty = $topic->messages()->create(['user_id' => $owner->id, 'body' => '   ']);
        $null  = $topic->messages()->create(['user_id' => $owner->id, 'body' => null]);

        $this->assertSame('hello there', $text->previewSnippet());
        $this->assertSame(str_repeat('a', 100), $long->previewSnippet());
        $this->assertSame(str_repeat('a', 80) . '...', $long->previewSnippet(80));
        $this->assertSame('📷 Photo', $empty->previewSnippet());
        $this->assertSame('📷 Photo', $null->previewSnippet(80));
    }

    public function test_sender_display_name_falls_back_for_deleted_users(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $topic   = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));
        $message = $topic->messages()->create(['user_id' => $owner->id, 'body' => 'x']);

        $this->assertSame($owner->name, $message->senderDisplayName());

        $message->forceFill(['user_id' => null])->save();
        $this->assertSame('Deleted user', $message->fresh()->senderDisplayName());
    }
}
