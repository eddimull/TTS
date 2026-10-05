<?php

namespace Tests\Feature\Web\Chat;

use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class MessagesPageTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_messages_page_renders_the_inbox_with_no_selection(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'hi']);

        $this->actingAs($owner)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Messages/Index')
                ->has('conversations', 2)
                ->where('conversations.0.type', 'dm')
                ->where('conversations.0.unread_count', 1)
                ->where('initialConversationId', null));
    }

    public function test_messages_page_preselects_a_viewable_conversation(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->get(route('messages.index', ['conversation' => $dm->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('initialConversationId', $dm->id));
    }

    public function test_messages_page_403s_for_a_conversation_the_viewer_cannot_see(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('messages.index', ['conversation' => $dm->id]))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('messages.index'))->assertRedirect(route('login'));
    }
}
