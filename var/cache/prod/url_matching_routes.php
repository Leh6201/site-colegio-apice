<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/robots.txt' => [[['_route' => 'robots_txt', '_controller' => 'App\\ProxyController::robotsTxtAction'], null, null, null, false, false, null]],
        '/sitemap.xml' => [[['_route' => 'sitemap', '_controller' => 'App\\ProxyController::sitemapAction'], null, null, null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/(.*)cloud/connect(*:25)'
                .'|/(.*)cloud/cache\\-clear(*:55)'
                .'|/(.*)cloud/reconnect(*:82)'
                .'|/(.*)cloud/healthcheck(*:111)'
                .'|/((?!.*\\.(?:.+)).*)(*:138)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        25 => [[['_route' => 'cloud_connect_route', '_controller' => 'App\\CloudController::indexAction'], ['prefix'], null, null, false, false, null]],
        55 => [[['_route' => 'cloud_clear_cache', '_controller' => 'App\\CloudController::clearCacheAction'], ['prefix'], null, null, false, false, null]],
        82 => [[['_route' => 'cloud_reconnect_route', '_controller' => 'App\\CloudController::cloudReconnectAction'], ['prefix'], null, null, false, false, null]],
        111 => [[['_route' => 'cloud_check_connection_route', '_controller' => 'App\\CloudController::cloudHealthcheckAction'], ['prefix'], null, null, false, false, null]],
        138 => [
            [['_route' => 'proxy', '_controller' => 'App\\ProxyController::indexAction'], ['dynamicRoute'], ['GET' => 0, 'POST' => 1], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
