<?php

namespace App;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class TelemetrySetup
{
    private TracerProvider $tracerProvider;

    public function __construct(string $latitudeApiKey, ?string $tracesEndpoint = null)
    {
        $endpoint = $tracesEndpoint ?? 'https://gateway.latitude.so/api/v4/traces';

        $transport = (new OtlpHttpTransportFactory())->create(
            $endpoint,
            ContentTypes::PROTOBUF,
            ['Authorization' => 'Bearer ' . $latitudeApiKey],
        );

        $exporter = new SpanExporter($transport);

        $resource = ResourceInfo::create(Attributes::create([
            'service.name' => 'latitude-telemetry-php',
        ]));

        $this->tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($exporter))
            ->setResource($resource)
            ->build();
    }

    public function getTracer(string $name = 'latitude.manual'): TracerInterface
    {
        return $this->tracerProvider->getTracer($name);
    }

    public function shutdown(): void
    {
        $this->tracerProvider->shutdown();
    }
}
