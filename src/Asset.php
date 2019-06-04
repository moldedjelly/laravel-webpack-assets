<?php

namespace Moldedjelly\WebpackAssets;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Moldedjelly\WebpackAssets\Exceptions\AssetException;

class Asset
{
    /**
     * Array of loaded assets.
     *
     * @var array
     */
    protected $assets;

    /**
     * Array of loaded legacy assets.
     *
     * @var array
     */
    protected $assetsLegacy;

    /**
     * Dev Server URL
     *
     * @var string
     */
    protected $devServerUrl;

    /**
     * Array of cached files content retrieved via "content" method.
     *
     * @var array
     */
    protected $cachedFileContents = [];

    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var UrlGenerator
     */
    protected $urlGenerator;

    /**
     * Manifest file path.
     *
     * @var string
     */
    protected $manifestFile;

    /**
     * Manifest legacy file path.
     *
     * @var string
     */
    protected $manifestLegacyFile;

    /**
     * Whether to throw exception when given chunk name real path is missing in filesystem.
     *
     * @var bool
     */
    protected $failOnLoad;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    public function __construct(array $config, UrlGenerator $generator, Filesystem $filesystem)
    {
        $this->initFromConfig($config);

        $this->urlGenerator = $generator;
        $this->filesystem = $filesystem;
    }

    /**
     * Returns all loaded assets from file.
     *
     * @return array
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function assets(): array
    {
        if ($this->assets === null) {
            $this->fresh();
        }

        return $this->assets;
    }

    /**
     * Returns all loaded legacy assets from file.
     *
     * @return array
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function assetsLegacy(): array
    {
        if ($this->assetsLegacy === null) {
            $this->fresh();
        }

        return $this->assetsLegacy;
    }

    /**
     * Reloads array of assets from manifest file.
     *
     * @return self
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function fresh(): self
    {
        $contents = [];
        try {
            if ($this->devServerUrl != "") {
              $contents = @file_get_contents($this->devServerUrl.$this->manifestFile);
            }
            if (empty($contents)) {
              $contents = $this->filesystem->get($this->manifestFile);
            }
            $contents = json_decode($contents, true);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $exception) {
            if ($this->failOnLoad) {
                // If we need to fail on load, then throw exception
                throw new AssetException("Manifest file {$exception->getMessage()} does not exist");
            }
        }

        // Fresh assets with new contents
        $this->assets = $contents;


        $contents = [];
        try {
            if ($this->devServerUrl != "") {
              $contents = @file_get_contents($this->devServerUrl.$this->manifestLegacyFile);
            }
            if (empty($contents)) {
              $contents = $this->filesystem->get($this->manifestLegacyFile);
            }
            $contents = json_decode($contents, true);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $exception) {
            if ($this->failOnLoad) {
                // If we need to fail on load, then throw exception
                throw new AssetException("Manifest file {$exception->getMessage()} does not exist");
            }
        }

        // Fresh assets with new contents
        $this->assetsLegacy = $contents;

        return $this;
    }

    /**
     * Initializes properties from given config.
     * Checks given config for validity and throw exception if it's invalid.
     *
     * @param array $config Configuration data array.
     *
     * @throws Exceptions\InvalidConfigurationException
     */
    private function initFromConfig(array $config)
    {
        foreach ($required = ['fail_on_load', 'file'] as $item) {
            if (! array_key_exists($item, $config)) {
                throw new \Moldedjelly\WebpackAssets\Exceptions\InvalidConfigurationException($required);
            }
        }

        $this->devServerUrl = $config['dev_server'];
        $this->manifestFile = $config['file'];
        $this->manifestLegacyFile = $config['file_legacy'];
        $this->failOnLoad = (bool) $config['fail_on_load'];
    }

    /**
     * Generates style link for chunk.
     *
     * @param $chunkName
     * @param array $attributes
     * @param boolean $legacy
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function style($chunkName, array $attributes = [], $legacy = false): string
    {
        $defaults = ['media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet'];

        $attributes = array_merge($defaults, $attributes);

        $attributes['href'] = $url = $this->url($chunkName);

        return $url ? $this->toHtmlString('<link'.$this->attributes($attributes).'>'.PHP_EOL) : '';
    }

    /**
     * Generates inline styles with content of given chunk.
     *
     * @param $chunkName
     * @param array $attributes
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function rawStyle($chunkName, array $attributes = []): string
    {
        $content = $this->content($chunkName);

        return $content ? '<style'.$this->attributes($attributes).'>'.$content.'</style>' : '';
    }

    /**
     * Generates inline script with content of given chunk.
     *
     * @param $chunkName
     * @param array $attributes
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function rawScript($chunkName, array $attributes = []): string
    {
        $content = $this->content($chunkName);

        return $content ? '<script type="text/javascript"'.$this->attributes($attributes).'>'.$content.'</script>' : '';
    }

    /**
     * Generates script attribute for chunk.
     *
     * @param $chunkName
     * @param array $attributes
     * @param boolean $legacy
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function script($chunkName, array $attributes = [], $legacy = false): string
    {
        $attributes['src'] = $url = $this->url($chunkName, $legacy);

        return $url ? $this->toHtmlString('<script'.$this->attributes($attributes).'></script>'.PHP_EOL) : '';
    }

    /**
     * Generates HTML image element for chunk.
     *
     * @param $chunkName
     * @param null $alt
     * @param array $attributes
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function image($chunkName, $alt = null, array $attributes = []): string
    {
        $defaults = ['alt' => $alt];

        $attributes = array_merge($defaults, $attributes);

        $attributes['src'] = $url = $this->url($chunkName);

        return $url ? $this->toHtmlString('<img'.$this->attributes($attributes).'>') : '';
    }

    /**
     * Returns full url for chunk.
     *
     * @param $chunkName
     * @param boolean $legacy
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function url($chunkName, $legacy = false): string
    {
        $path = $this->chunkPath($chunkName, $legacy);

        return $path !== '' ? $this->urlGenerator->url($path) : '';
    }

    /**
     * Returns chunk path as inside manifest.
     *
     * @param string $chunkName
     * @param boolean $legacy
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function chunkPath(string $chunkName, $legacy = false): string
    {
        if ($legacy) {
          return (string) Arr::get($this->assetsLegacy(), $chunkName, '');
        } else {
          return (string) Arr::get($this->assets(), $chunkName, '');
        }
    }

    /**
     * Returns path of chunk from manifest array.
     *
     * @param string $chunkName Name of chunk.
     * @param boolean $legacy
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function path($chunkName, $legacy = false): string
    {
        $path = $this->chunkPath($chunkName, $legacy);

        return $path === '' ? '' : $this->urlGenerator->path($path);
    }

    /**
     * Returns content of chunk.
     *
     * @param $chunk
     * @param boolean $legacy
     *
     * @return string
     * @throws \Moldedjelly\WebpackAssets\Exceptions\AssetException
     */
    public function content($chunk, $legacy = false): string
    {
        $path = $this->path($chunk);

        if (array_key_exists($path, $this->cachedFileContents)) {
            return $this->cachedFileContents[$path];
        }

        try {
            return $this->cachedFileContents[$path] = $this->filesystem->get($path);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $exception) {
            return '';
        }
    }

    /**
     * Build an HTML attribute string from an array.
     *
     * @param array $attributes
     *
     * @return string
     */
    public function attributes($attributes): string
    {
        $html = [];

        foreach ((array) $attributes as $key => $value) {
            $element = $this->attributeElement($key, $value);

            if ($element !== null) {
                $html[] = $element;
            }
        }

        return count($html) > 0 ? ' '.implode(' ', $html) : '';
    }

    /**
     * Convert all applicable characters to HTML entities.
     *
     * @param string $value
     *
     * @return string
     */
    public function escapeAll($value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build a single attribute element.
     *
     * @param string $key
     * @param string $value
     *
     * @return string
     */
    protected function attributeElement($key, $value): string
    {
        // For numeric keys we will assume that the key and the value are the same
        // as this will convert HTML attributes such as "required" to a correct
        // form like required="required" instead of using incorrect numerics.
        if (is_numeric($key)) {
            $key = $value;
        }

        if ($value !== null) {
            return $key.'="'.$this->escapeAll($value).'"';
        }

        return '';
    }

    /**
     * Transform the string to an Html serializable object.
     *
     * @param $html
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function toHtmlString($html): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString($html);
    }
}
