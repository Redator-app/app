<?php

declare(strict_types=1);

namespace Preset\Domain\ValueObjects;

use JsonSerializable;
use Override;

enum Type: string implements JsonSerializable
{
    case AUDIO_TRANSCRIPTION = 'audio-transcription';
    case AUDIO_TRANSLATION = 'audio-translation';
    case COMPLETION = 'completion';
    case CHAT_COLLECTION = 'chat-collection';
    case IMAGE_EDITING = 'image-editing';
    case IMAGE_VARIANTION = 'image-variation';
    case IMAGE_GENERATION = 'image-generation';

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
