<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\BandAccess;
use App\Mcp\Support\McpScopes;
use App\Models\Bands;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_bands')]
#[Title('List bands')]
#[Description('Lists the bands you own or are a member of, with your role in each and the tools you can use there with this connection. Call this first: every other band tool needs a band_id from here.')]
#[IsReadOnly]
#[IsIdempotent]
class ListBands extends Tool
{
    public function __construct(
        private BandAccess $access,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $bands = $this->access->bands($user)->map(fn (Bands $band) => [
            'id' => $band->id,
            'name' => $band->name,
            'role' => $this->access->role($user, $band),
            'tools' => $this->access->toolsFor($user, $band),
        ])->values();

        return Response::json([
            'user' => ['id' => $user->id, 'name' => $user->name],
            'granted_scopes' => array_values(array_filter(
                McpScopes::ids(),
                fn (string $scope) => McpScopes::granted($user, $scope),
            )),
            'bands' => $bands,
            'total' => $bands->count(),
        ]);
    }
}
