<?php

namespace App;

use App\Services\ConfigService;
use App\Services\ParserService;
use App\Services\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

class ProxyController extends AbstractController
{
    private ParserService $parserService;
    private ConfigService $configService;
    private CacheKernel $cacheKernel;

    const COOKIE_CLOUD_SESSION_ID_NAME = 'CLOUDSESSUID';

    public function __construct(
        ParserService $parserService,
        ConfigService $configService,
        CacheKernel   $cacheKernel
    )
    {
        $this->parserService = $parserService;
        $this->configService = $configService;
        $this->cacheKernel = $cacheKernel;
    }


    public function indexAction(Request $request, KernelInterface $kernel)
    {
        $this->parserService->removeNotAllowedQueryParams();

        if ($cachedResponse = $this->cacheKernel->getStore()->lookup($request)) {
            return $cachedResponse->setCache([
                'public' => false
            ]);
        }

        $previewUrl = $this->configService->getPreviewUrl();

        $previewUrlParsed = parse_url($previewUrl);
        $currentUrlParsed = parse_url($request->getUri());

        $proxyUrl = "${previewUrlParsed['scheme']}://${previewUrlParsed['host']}${currentUrlParsed['path']}?${currentUrlParsed['query']}";

        $headers = Utils::convertToAssociativeArray($request->headers->all());
        unset($headers['host']);

        $cookieJar = CookieJar::fromArray($request->cookies->all(), $previewUrlParsed['host']);

        try {
            $client = new Client(['cookies' => true]);
            $proxyResponse = $client->request($request->getMethod(), $proxyUrl,
                [
                    RequestOptions::ALLOW_REDIRECTS => false,
                    RequestOptions::JSON => json_decode(file_get_contents("php://input"), true),
                    RequestOptions::HEADERS => $headers,
                    RequestOptions::COOKIES => $cookieJar
                ]);

            if (in_array($proxyResponse->getStatusCode(), [301, 302, 303, 307, 308])) {
                $redirectUrl = $proxyResponse->getHeaderLine('Location');

                $newUrl = $this->configService->getBaseUrl() . parse_url($redirectUrl)['path'];

                if (!$newUrl) {
                    $newUrl = '/';
                }
                return new RedirectResponse($newUrl, $proxyResponse->getStatusCode());
            }


            $html = $this->parserService->parseHtml($proxyResponse->getBody()->getContents());
        } catch (ServerException $exception) {
            $html = $this->parserService->parseHtml($exception->getResponse()->getBody()->getContents());
            return new Response($html, $exception->getResponse()->getStatusCode());
        } catch (ClientException $exception) {
            $html = $this->parserService->parseHtml($exception->getResponse()->getBody()->getContents());
            return new Response($html, $exception->getResponse()->getStatusCode());
        }
        $response = (new Response($html));

        foreach ($cookieJar->toArray() as $cookieItem) {
            $cookie = new Cookie(
                $cookieItem['Name'],
                $cookieItem['Value'],
                $cookieItem['Expires'] ?? 0,
                $cookieItem['Path'],
                $request->getHost(),
                $cookieItem['Secure'],
                $cookieItem['HttpOnly'],
            );
            $response->headers->setCookie($cookie);
        }

        $response->setVary('USER_AUTHORIZATION', false);

        return $response->setCache([
            'public' => true,
            'max_age' => 31536000,
        ]);
    }

    public function robotsTxtAction()
    {

        $client = new Client();

        $res = $client->request('GET', $this->configService->getPreviewUrl() . '/robots.txt?native_url=1');
        $response = new Response($res->getBody()->getContents());
        $response->headers->set('Content-Type', 'text/plain');

        return $response;
    }

    public function sitemapAction(Request $request)
    {

        $client = new Client();
        $res = $client->request('GET', $this->configService->getPreviewUrl() . '/sitemap.xml?native_url=1');
        $clientHost = $request->getHost();
        $host = parse_url($this->configService->getPreviewUrl())['host'];
        $sitempatContent = $res->getBody()->getContents();
        $response = new Response(str_replace($host, $clientHost, $sitempatContent));
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }
}
