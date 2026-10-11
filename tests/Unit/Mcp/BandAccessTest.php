<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Servers\BandmateServer;
use App\Mcp\Support\BandAccess;
use App\Mcp\Tools\BandTool;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use ReflectionClass;
use Tests\TestCase;

class BandAccessTest extends TestCase
{
    public function test_every_band_tool_on_the_server_has_an_access_rule(): void
    {
        $defaults = (new ReflectionClass(BandmateServer::class))->getDefaultProperties();
        $this->assertArrayHasKey('tools', $defaults, 'BandmateServer no longer declares a $tools property');
        $tools = $defaults['tools'];

        $bandTools = array_filter($tools, fn (string $class) => is_subclass_of($class, BandTool::class));
        $this->assertNotEmpty($bandTools);

        foreach ($bandTools as $class) {
            $name = app($class)->name();
            $this->assertArrayHasKey($name, BandAccess::TOOLS, "{$class} ('{$name}') is missing from BandAccess::TOOLS");
        }
    }

    public function test_unknown_tool_fails_closed_with_an_error_message(): void
    {
        Exceptions::fake();
        $access = new BandAccess;
        $user = new User;

        $this->assertSame('mystery_tool is not available right now.', $access->resolve($user, 1, 'mystery_tool'));
        $this->assertSame('mystery_tool is not available right now.', $access->denial($user, new Bands, 'mystery_tool'));

        Exceptions::assertReported(\LogicException::class);
    }
}
