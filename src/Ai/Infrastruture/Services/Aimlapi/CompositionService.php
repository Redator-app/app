<?php

declare(strict_types=1);

namespace Ai\Infrastruture\Services\Aimlapi;

use Ai\Domain\ValueObjects\CompositionDetails;
use Ai\Domain\Composition\CompositionResponse;
use Ai\Domain\Composition\CompositionServiceInterface;
use Ai\Domain\Exceptions\ApiException;
use Ai\Domain\ValueObjects\Model;
use Ai\Domain\ValueObjects\Title;
use Ai\Infrastruture\Services\CostCalculator;
use Override;
use Traversable;

class CompositionService implements CompositionServiceInterface
{
    private array $models = [
        'chirp-v3-5',
        'chirp-v3-0',
    ];

    public function __construct(
        private readonly Client $client,
        private readonly CostCalculator $calc,
    ) {}

    #[Override]
    public function supportsModel(Model $model): bool
    {
        return in_array($model->value, $this->models);
    }

    #[Override]
    public function getSupportedModels(): Traversable
    {
        foreach ($this->models as $model) {
            yield new Model($model);
        }
    }

    #[Override]
    public function generateComposition(
        Model $model,
        array $params = []
    ): Traversable {
        $custom = true;

        if (isset($params['instrumental']) && (bool) $params['instrumental']) {
            $custom = false;
        } else if (isset($params['tags']) && trim($params['tags']) !== '') {
            $custom = true;
        }

        $data = [
            'wait_audio' => true,
        ];

        if (isset($params['prompt'])) {
            $data['prompt'] = $params['prompt'];
        }

        if (isset($params['instrumental'])) {
            $data['make_instrumental'] = (bool) $params['instrumental'];
        }

        if ($custom) {
            $data['title'] = ' ';
            $data['tags'] = ' ';

            if (isset($params['tags']) && trim($params['tags']) !== '') {
                $data['tags'] = $params['tags'];
            }
        }

        $resp = $this->client->sendRequest('POST', '/generate/' . ($custom ? 'custom-mode' : ''), $data);

        if ($resp->getStatusCode() !== 201) {
            throw new ApiException('Failed to generate composition: ' . $resp->getBody()->getContents());
        }

        $content = $resp->getBody()->getContents();
        $data = json_decode($content);

        foreach ($data as $gen) {
            $audioUrl = $gen->audio_url;

            $res = $this->client->sendRequest('GET', $audioUrl);

            if ($res->getStatusCode() !== 200) {
                throw new ApiException('Failed to download composition.');
            }

            $image = null;
            if ($gen->image_url) {
                // Get image
                $imgRes = $this->client->sendRequest('GET', $gen->image_url);

                if ($imgRes->getStatusCode() !== 200) {
                    throw new ApiException('Failed to download composition image.');
                }

                $image = imagecreatefromstring($imgRes->getBody()->getContents());
            }

            $cost = $this->calc->calculate(
                ($custom ? 200000 : 157500) / count($data), // Fixed cost according to https://docs.aimlapi.com/api-overview/audio-models-music-and-vocal/custom-audio-generation
                $model
            );

            $audioContent = $res->getBody();

            $details =  new CompositionDetails(
                $gen->lyric && trim($gen->lyric) !== '' ? $gen->lyric : null,
                $gen->tags && trim($gen->tags) !== '' ? $gen->tags : null,
            );

            yield new CompositionResponse(
                $audioContent,
                $cost,
                $image,
                new Title($gen->title && trim($gen->title) !== '' ? $gen->title : null),
                $details
            );
        }
    }
}
