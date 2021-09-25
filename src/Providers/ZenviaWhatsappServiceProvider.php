<?php

namespace BotMan\Drivers\ZenviaWhatsapp\Providers;

use Illuminate\Support\ServiceProvider;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\ZenviaWhatsapp\ZenviaWhatsappDriver;
use BotMan\Drivers\ZenviaWhatsapp\ZenviaWhatsappFileDriver;
use BotMan\Drivers\ZenviaWhatsapp\ZenviaWhatsappAudioDriver;
use BotMan\Drivers\ZenviaWhatsapp\ZenviaWhatsappPhotoDriver;
use BotMan\Drivers\ZenviaWhatsapp\ZenviaWhatsappVideoDriver;
use BotMan\Studio\Providers\StudioServiceProvider;
use BotMan\Drivers\ZenviaWhatsapp\ZenviaWhatsappLocationDriver;
use BotMan\Drivers\ZenviaWhatsapp\ZenviaWhatsappContactDriver;

class ZenviaWhatsappServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__.'/../../stubs/zenviawhatsapp.php' => config_path('botman/zenviawhatsapp.php'),
            ]);

            $this->mergeConfigFrom(__DIR__.'/../../stubs/zenviawhatsapp.php', 'botman.zenvia');
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(ZenviaWhatsappDriver::class);
        DriverManager::loadDriver(ZenviaWhatsappAudioDriver::class);
        DriverManager::loadDriver(ZenviaWhatsappFileDriver::class);
        DriverManager::loadDriver(ZenviaWhatsappLocationDriver::class);
        DriverManager::loadDriver(ZenviaWhatsappContactDriver::class);
        DriverManager::loadDriver(ZenviaWhatsappPhotoDriver::class);
        DriverManager::loadDriver(ZenviaWhatsappVideoDriver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
