<?php
define('MAX_FILE_SIZE', 6000000);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '0');

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use RequirementsChecker\ProjectRequirements;

if(array_key_exists(\App\ProxyController::COOKIE_CLOUD_SESSION_ID_NAME, $_REQUEST)){
    $_SERVER['HTTP_USER_AUTHORIZATION'] = $_REQUEST[\App\ProxyController::COOKIE_CLOUD_SESSION_ID_NAME];
}else{
    $_SERVER['HTTP_USER_AUTHORIZATION'] = 'USER_LOGGED_OUT';
}

try {
    $configDistPath = __DIR__ . '/config/config.json.dist';
    if (is_file($configDistPath)) {
        $configDist = json_decode(file_get_contents($configDistPath), true);
        $allowed = [];
        if (is_array($configDist) && !empty($configDist['allowed_query_params'])) {
            $allowed = array_filter(array_map('trim', explode('|', $configDist['allowed_query_params'])));
        }
        if (!empty($_GET)) {
            $allowedFlip = array_flip($allowed);
            $filteredGet = array_intersect_key($_GET, $allowedFlip);

            $queryString = http_build_query($filteredGet);
            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $parsed = parse_url($requestUri);
            $path = $parsed['path'] ?? $requestUri;
            $newRequestUri = $path . ($queryString !== '' ? ('?' . $queryString) : '');

            $_GET = $filteredGet;

            $requestOrder = ini_get('variables_order');
            $order = $requestOrder ?: 'GPCS';

            $newRequest = [];
            foreach (str_split($order) as $ch) {
                if ($ch === 'G') { $newRequest += $_GET; }
                if ($ch === 'P') { $newRequest += $_POST; }
                if ($ch === 'C') { $newRequest += $_COOKIE; }
            }
            $_REQUEST = $newRequest;

            $_SERVER['QUERY_STRING'] = $queryString;
            $_SERVER['REQUEST_URI'] = $newRequestUri;
        }
    }
} catch (\Throwable $e) {
}

if(!ProjectRequirements::isApplicationInstalled()){
    require __DIR__ . '/requirements-checker/requirements-checker.php';
}

$kernel = new \App\Kernel('prod', false);
$request = Request::createFromGlobals();

if(!ProjectRequirements::isApplicationConnectedWithCloud() && !str_contains($request->getUri(),'cloud/connect')) {
    $kernel->boot();
    $kernel->getContainer()->get('cloud_connection_service')->checkIfProjectIsRootProject($request);
    $kernel->getContainer()->get('cloud_connection_service')->establishConnection($request);
}

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);