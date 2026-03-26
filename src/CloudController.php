<?php

namespace App;


use App\Services\CloudConnectionService;
use App\Services\ConfigService;
use App\Services\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;


class CloudController extends AbstractController
{
    private ConfigService $configService;
    private KernelInterface $kernel;
    private CloudConnectionService $cloudConnectionService;

    public function __construct(
        KernelInterface $kernel,
        ConfigService $configService,
        CloudConnectionService $cloudConnectionService
    )
    {
        $this->kernel = $kernel;
        $this->configService = $configService;
        $this->cloudConnectionService = $cloudConnectionService;

    }

    public function indexAction(Request $request)
    {
        if($request->headers->get('token') != $this->configService->getAppId()){
            return new JsonResponse('UNAUTHORIZED',JsonResponse::HTTP_UNAUTHORIZED);
        };

        return new JsonResponse(['message'=> 'Connected!!']);
    }

    public function clearCacheAction(Request $request)
    {
        if($request->headers->get('token') != $this->configService->getAppId()){
            return new JsonResponse('UNAUTHORIZED',JsonResponse::HTTP_UNAUTHORIZED);
        };

        Utils::rrmdir($this->kernel->getCacheDir().'/http_cache');
        Utils::rrmdir($this->kernel->getProjectDir().'/var/assets');

        return new JsonResponse(['message'=> 'Cache cleared!!']);
    }

    public function cloudReconnectAction(Request $request)
    {

        Utils::rrmdir($this->kernel->getCacheDir().'/http_cache');
        Utils::rrmdir($this->kernel->getProjectDir().'/var/assets');
        Utils::rrmdir($this->kernel->getProjectDir().'/var/application_installed.json');
        unlink($this->kernel->getProjectDir().'/var/application_installed.json');

        return new RedirectResponse('/'.$this->configService->getBaseUrl());
    }

    public function cloudHealthcheckAction(Request $request): JsonResponse
    {
        if($request->headers->get('token') != $this->configService->getAppId()){
            return new JsonResponse('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        };

        return new JsonResponse(['app_id' =>$this->configService->getAppId()]);
    }
}
