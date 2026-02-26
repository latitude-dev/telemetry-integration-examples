<?php

namespace App;

use GuzzleHttp\Client;

class LatitudeClient
{
    private Client $http;

    public function __construct(private string $apiKey)
    {
        $this->http = new Client([
            'base_uri' => 'https://gateway.latitude.so',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Fetch a prompt document from Latitude.
     *
     * @return array{content: string, config: array{provider: string, model: string}}
     */
    public function getPrompt(int $projectId, string $versionUuid, string $path): array
    {
        $url = sprintf(
            '/api/v3/projects/%d/versions/%s/documents/%s',
            $projectId,
            $versionUuid,
            $path,
        );

        $response = $this->http->get($url);
        return json_decode($response->getBody()->getContents(), true);
    }
}
