<?php

namespace Mods\Theme\Console\Command;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class Compile extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'theme:compile
        {--area= : The area to be compile.}
    	{--theme= : The theme to be compile.}
    	{--module= : The module to be compile for the theme or area.}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile the Theme & Module asset and ship it.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $app = $this->getFreshAsset();
        $area = null;
        if($this->hasOption('area')) {
            $area = $this->option('area');
        }
        $theme = null;
        if($this->hasOption('theme')) {
            $theme = $this->option('theme');
        }
        $module = null;
        if($this->hasOption('module')) {
            $module = $this->option('module');
        }

        $deployer = $app['theme.deployer']->setConsole($this);
        $complier = $app['theme.complier']->setConsole($this);
        $preprocessor = $app['theme.preprocessor']->setConsole($this);

        $deployer->clear($area, $theme, $module);

        $deployer->deploy($area, $theme, $module);

        $preprocessor->process($area, $theme, $module);        

        $complier->compile($area, $theme, $module); 
    }

    /**
     * Boot a fresh copy of the application configuration.
     *
     * @return array
     */
    protected function getFreshAsset()
    {
        $app = require $this->laravel->bootstrapPath().'/app.php';

        $app['events']->listen(
            'bootstrapped: Illuminate\Foundation\Bootstrap\LoadConfiguration',
            function ($app) {
                $app['config']->set('deploying', true);
            }
        );
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        return $app;
    }
}