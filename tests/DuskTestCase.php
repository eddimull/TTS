<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Prepare for Dusk test execution.
     *
     * @beforeClass
     * @return void
     */
    public static function prepare()
    {
        // Use the remote Selenium container instead of a local chromedriver binary.
        // DUSK_DRIVER_URL is read from .env.dusk.local.

        // Force production-build asset resolution: if a contributor has
        // `npm run dev` running, public/hot points Vite at a host-only URL
        // the selenium container can't reach. Removing it makes the
        // app render via the manifest (which we just built).
        $hot = dirname(__DIR__) . '/public/hot';
        if (is_file($hot)) {
            @unlink($hot);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function driver()
    {
        $options = (new ChromeOptions)->addArguments(collect([
            '--headless',
            '--window-size=1920,1080',
            '--ignore-certificate-errors',
            '--allow-insecure-localhost',
        ])->unless($this->hasHeadlessDisabled(), function ($items) {
            return $items->merge([
                '--disable-gpu',
                '--headless',
            ]);
        })->all());

        $url = $_ENV['DUSK_DRIVER_URL']
            ?? $_SERVER['DUSK_DRIVER_URL']
            ?? getenv('DUSK_DRIVER_URL')
            ?: env('DUSK_DRIVER_URL', 'http://localhost:9515');

        return RemoteWebDriver::create(
            $url,
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * Determine whether the Dusk command has disabled headless mode.
     *
     * @return bool
     */
    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
               isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }
}
