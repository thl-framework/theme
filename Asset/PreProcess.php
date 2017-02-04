<?php

namespace  Mods\Theme\Asset;

use Mods\Theme\ThemeResolver;
use Mods\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use MJS\TopSort\ElementNotFoundException;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Output\OutputInterface;
use MJS\TopSort\Implementations\FixedArraySort as SortAssets;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class PreProcess extends Console
{
    /**
     * The view finder implementation.
     *
     * @var \Mods\Theme\ThemeResolver
     */
    protected $themeResolver;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $application;

    /**
     * The view factory.
     *
     * @var \Mods\View\Factory
     */
    protected $viewFactory;

    /**
     * The config instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    protected $basePath;
    /**
     * Create a new config cache command instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \Mods\View\Factory $viewFactory
     * @param  \Mods\Theme\ThemeResolver $themeResolver
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    public function __construct(
        Application $app,
        Filesystem $files,
        ViewFactory $viewFactory,
        ThemeResolver $themeResolver,
        ConfigContract $config
    ) {
        $this->files = $files;
        $this->config = $config;
        $this->application = $app;
        $this->viewFactory = $viewFactory;
        $this->themeResolver = $themeResolver;
        $this->basePath = $this->application['path.resources'];
    }

    public function process($areas, $theme = null)
    {
        $manifest = [];
        $oldArea = $this->application['area'];
        $oldLayoutXmlLocation = $this->config->get('layout.xml_location');

        $page = $this->viewFactory->getPageFactory();
        $pageUpdates = $page->getLayout()->getUpdate();
        foreach ($areas as $area) {
            $this->info("Pre Processing for {$area} section.");
            $manifest[$area] = [];
            $this->application['area'] = $area;
            $handles = $pageUpdates->collectHandlesFromUpdates();
            $currentTheme = $this->themeResolver->getActive($area);
            $themes = $this->themeResolver->themeCollection($area);
            $themes = $themes->only($theme);
            foreach ($themes as $key => $theme) {
                $this->info("  => Moking app for {$key} theme in {$area} section");
                $manifest[$area][$key] = [];
                $this->mockApplicationTheme($area, $key, $currentTheme);
                $currentTheme = $key;
                foreach ($handles as $handle) {
                    if ($handle == 'default') {
                        continue;
                    }
                    $this->info("\t* Preparing Asset config for `{$handle}`", OutputInterface::VERBOSITY_DEBUG);
                    $page->resetPage()
                        ->addHandle('default')
                        ->addHandle($handle)
                        ->buildLayout();
                    $manifest[$area][$key] = $this->prepareAsset(
                        $page->getLayout()->generateHeadElemets(),
                        $handle,
                        $manifest[$area][$key]
                    );
                }
            }
            $this->info("Pre Processing for {$area} section done.");
            $this->line("==============================================");
        }

        $this->writeConfig($manifest);

        $this->application['area'] = $oldArea;
        $this->config->set('layout.xml_location', $oldLayoutXmlLocation);
    }

    protected function mockApplicationTheme($area, $themeKey, $currentTheme)
    {
        $locations = $this->config->get('layout.xml_location.'.$area);
        $currentPaths = $this->themeResolver->getPaths($area, $currentTheme)->toArray();

        foreach ($currentPaths as $path) {
            unset($locations[md5($path.'/layouts/')]);
        }
        $paths = $this->themeResolver->getPaths($area, $themeKey)->toArray();
        foreach ($paths as $path) {
            if (is_dir($appPath = $path.'/layouts/')) {
                $locations[md5($appPath)] = $appPath;
            }
        }
        $this->config->set('layout.xml_location.'.$area, $locations);
    }

    protected function prepareAsset($assets, $handle, $manifest)
    {
        $scripts = $this->prepareScripts($assets['js']);
        $styles = $this->prepareStyles($assets['css']);

        return array_merge_recursive($manifest, [
            'js' => [$handle => $scripts],
            'css' => [$handle => $styles]
        ]);
    }

    protected function prepareScripts($scripts)
    {
        $scripts =  '<?xml version="1.0"?>'
            . '<scripts xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . $scripts
            . '</scripts>';
        $xml = simplexml_load_string($scripts);
        $jsSorter =  new SortAssets();
        foreach ($xml->script as $script) {
            $attributes = $script->attributes();
            $jsSorter->add(
                (string) $attributes->src,
                ($attributes->depends)?(string) $attributes->depends:null
            );
        }

        return $jsSorter->sort();
    }

    protected function prepareStyles($styles)
    {
        $styles =  '<?xml version="1.0"?>'
            . '<styles xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . $styles
            . '</styles>';
        $xml = simplexml_load_string($styles);
        $css = [];
        foreach ($xml->link as $style) {
            $attributes = $style->attributes();
            $css[] = (string) $attributes->href;
        }

        return $css;
    }

    protected function writeConfig($manifest)
    {
        $configPath = formPath(
            [$this->basePath, 'assets', 'config.json']
        );
        $manifestOld = [];
        try {
            $manifestOld = json_decode($this->files->get($configPath), true);
            foreach ($manifest as $area => $themes) {
                foreach ($themes as $theme => $assets) {
                    foreach ($assets as $key => $asset) {
                        $manifestOld['areas'][$area][$theme][$key] = $asset;
                    }
                }
            }
        } catch (FileNotFoundException $e) {
        }
        $this->files->put(
            $configPath, json_encode($manifestOld, JSON_PRETTY_PRINT)
        );
    }
}