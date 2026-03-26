<?php

namespace App\Services;

use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class CloudConnectionService
{

    private ConfigService $configService;
    private KernelInterface $kernel;
    private ?Request $request;

    public function __construct(
        ConfigService   $configService,
        KernelInterface $kernel

    )
    {

        $this->configService = $configService;
        $this->kernel = $kernel;

    }

    private function getClient(): Client
    {
        return new Client([
            'defaults' => [
                'exceptions' => false,
                'verify' => $this->kernel->getProjectDir() . '/certificates/ca-bundle.crt',
                'timeout'
            ]
        ]);
    }

    private function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function establishConnection(Request $request)
    {

        $this->setRequest($request);

        try {
            $response = $this->getClient()->post($this->configService->getDeployUrl() . '/export/check-connection', [
                'form_params' => [
                    'base_url' => $this->getBaseUrl('', ''),
                    'connect_url' => $this->getBaseUrl('', '/cloud/connect'),
                    'project_uid' => $this->configService->getAppId(),
                    'is_localhost' => $this->IsLocalhost(),
                    'version' => $this->configService->getVersion()
                ]
            ]);


            if ($response->getStatusCode() != 200) {
                (new Response('Connection error: ' . $response->getBody()->getContents()))->send();
                exit;
            }


            file_put_contents($this->kernel->getProjectDir() . '/var/application_installed.json', json_encode(['base_url'=> $request->getBaseUrl()]));


        } catch (\Throwable $e) {
            (new Response('Connection error: ' . $e->getMessage()))->send();
            exit;
        }

    }

    public function checkIfProjectIsRootProject(Request $request)
    {

        if( !in_array($request->getBaseUrl(), ['/',''] ) ){
            echo '<h1>The project can be used like a root project: www.example.com/</h1>';
            echo '<h1>Is not advised to be used like a subproject: www.example.com/someroutes/</h1>';
            exit;
        }

    }

    public function getBaseUrl($route_from, $route_to)
    {
        $baseUrl = $this->request->getScheme() . '://' . $this->request->getHost();
        if ($this->request->getPort() != 80 && $this->request->getPort() != 443) {
            $baseUrl .= ':' . $this->request->getPort();
        }

        $prefix = str_replace($route_from, '', $this->request->getBaseUrl());
        if ($prefix != '') {
            $baseUrl = $baseUrl . $prefix . $route_to;
        } else {
            $baseUrl = $baseUrl . $route_to;
        }

        return $baseUrl;
    }

    private function IsLocalhost()
    {
        $clientIP = $this->getClientIP();


        $isLocalhost = null;
        if ($clientIP == '127.0.0.1' || $clientIP == '::1') {
            $isLocalhost = 1;
        };

        return $isLocalhost;
    }

    public function getClientIP()
    {
        if ($this->request->server->get("HTTP_X_FORWARDED_FOR")) {
            $clientIP = explode(",", $this->request->server->get("HTTP_X_FORWARDED_FOR"));
            $clientIP = $clientIP[0];
        } else {
            $clientIP = $this->request->getClientIp();
        }

        return $clientIP;
    }
}