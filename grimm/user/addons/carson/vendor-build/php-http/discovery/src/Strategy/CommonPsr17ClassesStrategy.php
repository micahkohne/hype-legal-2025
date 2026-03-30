<?php

namespace BoldMinded\Carson\Dependency\Http\Discovery\Strategy;

use BoldMinded\Carson\Dependency\Psr\Http\Message\RequestFactoryInterface;
use BoldMinded\Carson\Dependency\Psr\Http\Message\ResponseFactoryInterface;
use BoldMinded\Carson\Dependency\Psr\Http\Message\ServerRequestFactoryInterface;
use BoldMinded\Carson\Dependency\Psr\Http\Message\StreamFactoryInterface;
use BoldMinded\Carson\Dependency\Psr\Http\Message\UploadedFileFactoryInterface;
use BoldMinded\Carson\Dependency\Psr\Http\Message\UriFactoryInterface;
/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * Don't miss updating src/Composer/Plugin.php when adding a new supported class.
 */
final class CommonPsr17ClassesStrategy implements DiscoveryStrategy
{
    /**
     * @var array
     */
    private static $classes = [RequestFactoryInterface::class => ['BoldMinded\\Carson\\Dependency\\Phalcon\\Http\\Message\\RequestFactory', 'BoldMinded\\Carson\\Dependency\\Nyholm\\Psr7\\Factory\\Psr17Factory', 'BoldMinded\\Carson\\Dependency\\GuzzleHttp\\Psr7\\HttpFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Diactoros\\RequestFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Guzzle\\RequestFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Slim\\RequestFactory', 'BoldMinded\\Carson\\Dependency\\Laminas\\Diactoros\\RequestFactory', 'BoldMinded\\Carson\\Dependency\\Slim\\Psr7\\Factory\\RequestFactory', 'BoldMinded\\Carson\\Dependency\\HttpSoft\\Message\\RequestFactory'], ResponseFactoryInterface::class => ['BoldMinded\\Carson\\Dependency\\Phalcon\\Http\\Message\\ResponseFactory', 'BoldMinded\\Carson\\Dependency\\Nyholm\\Psr7\\Factory\\Psr17Factory', 'BoldMinded\\Carson\\Dependency\\GuzzleHttp\\Psr7\\HttpFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Diactoros\\ResponseFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Guzzle\\ResponseFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Slim\\ResponseFactory', 'BoldMinded\\Carson\\Dependency\\Laminas\\Diactoros\\ResponseFactory', 'BoldMinded\\Carson\\Dependency\\Slim\\Psr7\\Factory\\ResponseFactory', 'BoldMinded\\Carson\\Dependency\\HttpSoft\\Message\\ResponseFactory'], ServerRequestFactoryInterface::class => ['BoldMinded\\Carson\\Dependency\\Phalcon\\Http\\Message\\ServerRequestFactory', 'BoldMinded\\Carson\\Dependency\\Nyholm\\Psr7\\Factory\\Psr17Factory', 'BoldMinded\\Carson\\Dependency\\GuzzleHttp\\Psr7\\HttpFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Diactoros\\ServerRequestFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Guzzle\\ServerRequestFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Slim\\ServerRequestFactory', 'BoldMinded\\Carson\\Dependency\\Laminas\\Diactoros\\ServerRequestFactory', 'BoldMinded\\Carson\\Dependency\\Slim\\Psr7\\Factory\\ServerRequestFactory', 'BoldMinded\\Carson\\Dependency\\HttpSoft\\Message\\ServerRequestFactory'], StreamFactoryInterface::class => ['BoldMinded\\Carson\\Dependency\\Phalcon\\Http\\Message\\StreamFactory', 'BoldMinded\\Carson\\Dependency\\Nyholm\\Psr7\\Factory\\Psr17Factory', 'BoldMinded\\Carson\\Dependency\\GuzzleHttp\\Psr7\\HttpFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Diactoros\\StreamFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Guzzle\\StreamFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Slim\\StreamFactory', 'BoldMinded\\Carson\\Dependency\\Laminas\\Diactoros\\StreamFactory', 'BoldMinded\\Carson\\Dependency\\Slim\\Psr7\\Factory\\StreamFactory', 'BoldMinded\\Carson\\Dependency\\HttpSoft\\Message\\StreamFactory'], UploadedFileFactoryInterface::class => ['BoldMinded\\Carson\\Dependency\\Phalcon\\Http\\Message\\UploadedFileFactory', 'BoldMinded\\Carson\\Dependency\\Nyholm\\Psr7\\Factory\\Psr17Factory', 'BoldMinded\\Carson\\Dependency\\GuzzleHttp\\Psr7\\HttpFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Diactoros\\UploadedFileFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Guzzle\\UploadedFileFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Slim\\UploadedFileFactory', 'BoldMinded\\Carson\\Dependency\\Laminas\\Diactoros\\UploadedFileFactory', 'BoldMinded\\Carson\\Dependency\\Slim\\Psr7\\Factory\\UploadedFileFactory', 'BoldMinded\\Carson\\Dependency\\HttpSoft\\Message\\UploadedFileFactory'], UriFactoryInterface::class => ['BoldMinded\\Carson\\Dependency\\Phalcon\\Http\\Message\\UriFactory', 'BoldMinded\\Carson\\Dependency\\Nyholm\\Psr7\\Factory\\Psr17Factory', 'BoldMinded\\Carson\\Dependency\\GuzzleHttp\\Psr7\\HttpFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Diactoros\\UriFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Guzzle\\UriFactory', 'BoldMinded\\Carson\\Dependency\\Http\\Factory\\Slim\\UriFactory', 'BoldMinded\\Carson\\Dependency\\Laminas\\Diactoros\\UriFactory', 'BoldMinded\\Carson\\Dependency\\Slim\\Psr7\\Factory\\UriFactory', 'BoldMinded\\Carson\\Dependency\\HttpSoft\\Message\\UriFactory']];
    public static function getCandidates($type)
    {
        $candidates = [];
        if (isset(self::$classes[$type])) {
            foreach (self::$classes[$type] as $class) {
                $candidates[] = ['class' => $class, 'condition' => [$class]];
            }
        }
        return $candidates;
    }
}
