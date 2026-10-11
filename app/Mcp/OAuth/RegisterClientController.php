<?php

namespace App\Mcp\OAuth;

use App\Mcp\Support\McpScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/**
 * Dynamic client registration (RFC 7591). The package controller creates a
 * public authorization-code (PKCE) Passport client; we only correct the
 * `scope` it reports so clients request our scopes rather than `mcp:use`.
 */
class RegisterClientController extends OAuthRegisterController
{
    public function __invoke(Request $request): JsonResponse
    {
        $response = parent::__invoke($request);

        if ($response->isSuccessful()) {
            $data = $response->getData(true);
            $data['client_name'] = (string) ($request->input('client_name') ?? $request->input('name'));
            $data['scope'] = implode(' ', McpScopes::ids());
            $response->setData($data)->setStatusCode(201);
        }

        return $response;
    }
}
