<?php


namespace App;

use App\Services\ParserService;
use App\Services\Utils;
use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheKernel extends HttpCache
{


    protected function invalidate(Request $request, bool $catch = false)
    {
        if ('PURGE' !== $request->getMethod()) {
            return parent::invalidate($request, $catch);
        }

        $response = new Response();


        if(str_contains($request->getUri(),'*all*')){
            Utils::rrmdir($this->kernel->getCacheDir().'/http_cache');
            Utils::rrmdir($this->kernel->getProjectDir().'/var/assets');
            $response->setStatusCode(Response::HTTP_OK, 'Cache cleaned up');

            return $response;
        }


        if ($this->getStore()->purge($request->getUri())) {
            Utils::rrmdir($this->kernel->getProjectDir().'/var/assets'. ParserService::getPageAssetsPath($request->getPathInfo()));
            $response->setStatusCode(Response::HTTP_OK, 'Purged');
        } else {
            $response->setStatusCode(Response::HTTP_NOT_FOUND, 'Not found');
        }

        return $response;
    }
}