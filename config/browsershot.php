<?php


return [
    'node_binary' => env('NODE_BINARY', '/usr/local/bin/node'),
    'npm_binary' => env('NPM_BINARY', '/usr/local/bin/npm'),
    'temp_path' => env('TEMP_PATH', '/tmp'),
    'executablePath' => env('BROWSERSHOT_EXECUTABLE_PATH', '/usr/bin/google-chrome'),
    'timeout' => 60,
    'delay' => 0,
    'no_sandbox' => true,
    'debug' => false,
    'debugger' => null,
];
