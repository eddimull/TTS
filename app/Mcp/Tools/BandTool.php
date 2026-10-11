<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\BandAccess;
use App\Models\Bands;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * A tool that acts on one of the user's bands, chosen by `band_id`.
 *
 * Subclasses call {@see band()} first; it enforces the token scope, band
 * membership and band permission for the tool (see {@see BandAccess::TOOLS}).
 */
abstract class BandTool extends Tool
{
    public function __construct(
        protected BandAccess $access,
    ) {}

    /**
     * The band to act on, or an error Response to return as-is.
     */
    protected function band(Request $request): Bands|Response
    {
        $request->validate(['band_id' => 'required|integer']);

        $band = $this->access->resolve($request->user(), $request->get('band_id'), $this->name());

        return is_string($band) ? Response::error($band) : $band;
    }

    protected function bandIdSchema(JsonSchema $schema): Type
    {
        return $schema->integer()
            ->description('ID of the band to act on (from list_bands).')
            ->required();
    }

    /**
     * Encode like the REST API does (JSON_PRESERVE_ZERO_FRACTION keeps 4250.0
     * as a float), without escaping slashes or unicode.
     */
    protected function json(mixed $data): Response
    {
        return Response::text(json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        ));
    }
}
