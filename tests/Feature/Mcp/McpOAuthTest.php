<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Support\McpScopes;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Tests\Concerns\UsesPassportKeys;
use Tests\TestCase;

/**
 * The OAuth 2.1 surface claude.ai's custom connectors use: discovery, dynamic
 * client registration, consent, PKCE code exchange, and the 401 challenge on
 * /mcp — plus a full end-to-end connect-and-call.
 */
class McpOAuthTest extends TestCase
{
    use RefreshDatabase;
    use UsesPassportKeys;

    private const CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassportKeys();
        Bus::fake();

        $this->user = User::factory()->create(['name' => 'Olive Owner', 'email' => 'olive@example.com']);
        $this->band = Bands::factory()->create(['name' => 'Alpha Band']);
        $this->band->owners()->create(['user_id' => $this->user->id]);
    }

    private function registerClient(): string
    {
        return $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [self::CALLBACK],
        ])->assertCreated()->json('client_id');
    }

    /**
     * @return array{0: string, 1: string} [verifier, authorize query string]
     */
    private function authorizeQuery(string $clientId, ?string $scope = null): array
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $query = http_build_query(array_filter([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::CALLBACK,
            'state' => 'state-123',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'resource' => url('/mcp'),
            'scope' => $scope,
        ]));

        return [$verifier, $query];
    }

    private function mcp(string $token, string $method, array $params = [], int $id = 1)
    {
        return $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params]);
    }

    /**
     * Register, consent and exchange the code for tokens, like claude.ai does.
     */
    private function connect(?string $scope = null): array
    {
        $clientId = $this->registerClient();
        [$verifier, $query] = $this->authorizeQuery($clientId, $scope);

        $this->actingAs($this->user)->get('/oauth/authorize?' . $query)->assertOk();
        $authToken = session('authToken');

        $redirect = $this->post('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => $authToken])
            ->assertRedirect()
            ->headers->get('Location');

        parse_str(parse_url($redirect, PHP_URL_QUERY), $params);
        $this->assertSame('state-123', $params['state']);

        return $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::CALLBACK,
            'code_verifier' => $verifier,
            'code' => $params['code'],
        ])->assertOk()->json();
    }

    // ── Discovery ───────────────────────────────────────────────────────

    public function test_protected_resource_metadata_for_the_mcp_endpoint(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJson([
                'resource' => url('/mcp'),
                'authorization_servers' => [url('/')],
                'scopes_supported' => McpScopes::ids(),
                'bearer_methods_supported' => ['header'],
            ]);

        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('scopes_supported', McpScopes::ids());
    }

    public function test_authorization_server_metadata(): void
    {
        foreach (['/.well-known/oauth-authorization-server', '/.well-known/oauth-authorization-server/mcp'] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJson([
                    'issuer' => url('/'),
                    'authorization_endpoint' => url('/oauth/authorize'),
                    'token_endpoint' => url('/oauth/token'),
                    'registration_endpoint' => url('/oauth/register'),
                    'response_types_supported' => ['code'],
                    'code_challenge_methods_supported' => ['S256'],
                    'grant_types_supported' => ['authorization_code', 'refresh_token'],
                    'scopes_supported' => McpScopes::ids(),
                    'token_endpoint_auth_methods_supported' => ['none'],
                ]);
        }
    }

    public function test_unauthenticated_mcp_request_gets_401_with_resource_metadata_challenge(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]);

        $response->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
        $this->assertSame(
            'Bearer realm="mcp", resource_metadata="' . url('/.well-known/oauth-protected-resource/mcp') . '"',
            $response->headers->get('WWW-Authenticate')
        );

        // Never the login redirect, whatever the Accept header.
        $this->call('POST', '/mcp', server: ['HTTP_ACCEPT' => 'text/html'])->assertUnauthorized();
        $this->withToken('not-a-real-token')->postJson('/mcp', [])->assertUnauthorized();
    }

    public function test_get_on_mcp_is_405(): void
    {
        $this->get('/mcp')->assertStatus(405)->assertHeader('Allow', 'POST');
    }

    // ── Dynamic client registration ─────────────────────────────────────

    public function test_dynamic_client_registration_creates_a_public_pkce_client(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [self::CALLBACK],
        ])->assertCreated()->assertJson([
            'client_name' => 'Claude',
            'redirect_uris' => [self::CALLBACK],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => implode(' ', McpScopes::ids()),
        ]);

        $client = Client::findOrFail($response->json('client_id'));
        $this->assertSame('Claude', $client->name);
        $this->assertFalse($client->confidential());
    }

    public function test_registration_rejects_unlisted_redirect_domains_but_allows_loopback(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Evil',
            'redirect_uris' => ['https://evil.example.com/callback'],
        ])->assertStatus(400)->assertJson(['error' => 'invalid_redirect_uri']);

        $this->postJson('/oauth/register', [
            'client_name' => 'Claude Code',
            'redirect_uris' => ['http://localhost:53682/callback'],
        ])->assertCreated();
    }

    // ── Consent screen ──────────────────────────────────────────────────

    public function test_guest_is_sent_to_login_and_back(): void
    {
        [, $query] = $this->authorizeQuery($this->registerClient());

        $this->get('/oauth/authorize?' . $query)->assertRedirect(route('login'));
        $this->assertStringContainsString('/oauth/authorize?', session('url.intended'));
    }

    public function test_consent_screen_lists_all_scopes_when_none_requested(): void
    {
        [, $query] = $this->authorizeQuery($this->registerClient());

        $this->actingAs($this->user)
            ->get('/oauth/authorize?' . $query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('OAuth/Authorize')
                ->where('client.name', 'Claude')
                ->where('user.name', 'Olive Owner')
                ->where('user.email', 'olive@example.com')
                ->where('scopes', collect(McpScopes::descriptions())
                    ->map(fn ($description, $id) => ['id' => $id, 'description' => $description])
                    ->values()
                    ->all())
                ->where('approveUrl', route('passport.authorizations.approve'))
                ->where('denyUrl', route('passport.authorizations.deny'))
                ->has('authToken')
                ->has('csrfToken'));
    }

    public function test_consent_screen_shows_only_requested_scopes(): void
    {
        [, $query] = $this->authorizeQuery($this->registerClient(), 'bookings:read events:read');

        $this->actingAs($this->user)
            ->get('/oauth/authorize?' . $query)
            ->assertInertia(fn (Assert $page) => $page
                ->where('scopes.0.id', 'bookings:read')
                ->where('scopes.1.id', 'events:read')
                ->count('scopes', 2));
    }

    public function test_inertia_visits_to_authorize_become_full_page_loads(): void
    {
        [, $query] = $this->authorizeQuery($this->registerClient());

        $this->actingAs($this->user)
            ->get('/oauth/authorize?' . $query, ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }

    public function test_deny_redirects_back_with_access_denied(): void
    {
        $clientId = $this->registerClient();
        [, $query] = $this->authorizeQuery($clientId);

        $this->actingAs($this->user)->get('/oauth/authorize?' . $query)->assertOk();

        $location = $this->delete('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => session('authToken')])
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith(self::CALLBACK . '?', $location);
        $this->assertStringContainsString('error=access_denied', $location);
        $this->assertStringContainsString('state=state-123', $location);
    }

    public function test_approve_rejects_a_forged_auth_token(): void
    {
        $clientId = $this->registerClient();
        [, $query] = $this->authorizeQuery($clientId);

        $this->actingAs($this->user)->get('/oauth/authorize?' . $query)->assertOk();

        $this->post('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => 'forged'])
            ->assertForbidden()
            ->assertHeaderMissing('Location');
    }

    // ── End to end ──────────────────────────────────────────────────────

    public function test_full_connect_flow_then_call_tools_over_http(): void
    {
        $tokens = $this->connect();

        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertEqualsWithDelta(8 * 3600, $tokens['expires_in'], 5);

        $this->mcp($tokens['access_token'], 'initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'claude-ai', 'version' => '1.0'],
        ])->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'TTS Bandmate')
            ->assertJsonPath('result.protocolVersion', '2025-06-18');

        $toolDefs = collect($this->mcp($tokens['access_token'], 'tools/list', [], 2)->assertOk()->json('result.tools'));
        $tools = $toolDefs->pluck('name');
        $this->assertEqualsCanonicalizing([
            'list_bands', 'list_event_types', 'check_availability', 'list_events', 'list_bookings',
            'get_booking', 'create_booking', 'update_booking', 'delete_booking', 'add_booking_contact', 'estimate_price',
        ], $tools->all());

        // Optional contact fields accept null, matching the server-side rules.
        $contactProps = $toolDefs->firstWhere('name', 'add_booking_contact')['inputSchema']['properties'];
        foreach (['phone', 'role', 'is_primary'] as $field) {
            $this->assertContains('null', (array) $contactProps[$field]['type'], "{$field} should be nullable");
        }

        $result = $this->mcp($tokens['access_token'], 'tools/call', ['name' => 'list_bands', 'arguments' => (object) []], 3)
            ->assertOk()
            ->json('result');

        $this->assertFalse($result['isError']);
        $data = json_decode($result['content'][0]['text'], true);
        $this->assertSame(McpScopes::ids(), $data['granted_scopes']);
        $this->assertSame([['id' => $this->band->id, 'name' => 'Alpha Band', 'role' => 'owner', 'tools' => [
            'check_availability', 'list_bookings', 'get_booking', 'create_booking',
            'update_booking', 'delete_booking', 'add_booking_contact', 'list_events', 'estimate_price',
        ]]], $data['bands']);
    }

    public function test_token_with_narrower_scope_is_limited(): void
    {
        $tokens = $this->connect('bookings:read');

        $result = $this->mcp($tokens['access_token'], 'tools/call', [
            'name' => 'create_booking',
            'arguments' => ['band_id' => $this->band->id],
        ])->assertOk()->json('result');

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString("'bookings:write' scope", $result['content'][0]['text']);
    }

    public function test_refresh_token_grant_works(): void
    {
        $tokens = $this->connect();
        $clientId = Client::query()->latest()->value('id');

        $refreshed = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->json();

        $this->mcp($refreshed['access_token'], 'ping')->assertOk();
    }

    public function test_oauth_and_sanctum_tokens_do_not_cross_over(): void
    {
        $tokens = $this->connect();

        // An MCP OAuth token is not a mobile (Sanctum) token...
        $this->app['auth']->forgetGuards();
        $this->withToken($tokens['access_token'])->getJson('/api/mobile/auth/me')->assertUnauthorized();

        // ...and a mobile token doesn't open /mcp.
        $this->app['auth']->forgetGuards();
        $sanctum = $this->user->createToken('device')->plainTextToken;
        $this->withToken($sanctum)->postJson('/mcp', [])->assertUnauthorized();
        $this->withToken($sanctum)->getJson('/api/mobile/auth/me')->assertOk();
    }
}
