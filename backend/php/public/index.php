<?php

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

use App\LogHandler;
use App\TelemetryHandler;
use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../..');
$dotenv->load();

$latitudeApiKey = $_ENV['LATITUDE_API_KEY'];
$latitudeProjectId = (int) $_ENV['LATITUDE_PROJECT_ID'];
$latitudePromptPath = $_ENV['LATITUDE_PROMPT_PATH'];
$latitudeVersionUuid = $_ENV['LATITUDE_PROMPT_VERSION_UUID'] ?? 'live';
$openaiApiKey = $_ENV['OPENAI_API_KEY'];

// ---------------------------------------------------------------------------
// Slim app
// ---------------------------------------------------------------------------
$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// CORS middleware
$app->add(function (Request $request, $handler) {
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withStatus(200);
    }

    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
});

// ---------------------------------------------------------------------------
// POST /generate-wikipedia-article
// ---------------------------------------------------------------------------
$app->post('/generate-wikipedia-article', function (
    Request $request,
    Response $response,
) use (
    $latitudeApiKey,
    $latitudeProjectId,
    $latitudePromptPath,
    $latitudeVersionUuid,
    $openaiApiKey,
) {
    $body = $request->getParsedBody();
    $input = $body['input'] ?? '';

    if (($_ENV['USE_LATITUDE_LOG_API'] ?? 'false') === 'true') {
        LogHandler::handle(
            $input,
            $latitudeApiKey,
            $latitudeProjectId,
            $latitudePromptPath,
            $latitudeVersionUuid,
            $openaiApiKey,
        );
    } else {
        TelemetryHandler::handle(
            $input,
            $latitudeApiKey,
            $latitudeProjectId,
            $latitudePromptPath,
            $latitudeVersionUuid,
            $openaiApiKey,
        );
    }

    exit(0);
});

$app->run();
