<?php

use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| AI / MCP routes
|--------------------------------------------------------------------------
|
| Loaded by laravel/mcp's service provider with no middleware group.
|
| Mcp::oauthRoutes() registers the OAuth discovery documents
| (/.well-known/oauth-protected-resource[/mcp],
| /.well-known/oauth-authorization-server) and dynamic client registration
| (POST /oauth/register). Passport itself serves /oauth/authorize (consent
| screen) and /oauth/token. See docs/mcp-server.md.
|
*/

Mcp::oauthRoutes();
