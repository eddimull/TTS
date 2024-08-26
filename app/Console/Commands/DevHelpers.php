<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DevHelpers extends Command
{
    protected $signature = 'dev-helpers:run';
    protected $description = 'run development scripts on env=local only';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        if (config('app.env') === 'local')
        {
            $this->info('generating ide helper files...');
            $this->call('ide-helper:generate');
            $this->call('ide-helper:meta');
            $this->info('... generating ide helper files done');
        }
    }
}
