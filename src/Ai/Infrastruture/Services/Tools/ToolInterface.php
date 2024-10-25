<?php

declare(strict_types=1);

namespace Ai\Infrastruture\Services\Tools;

use File\Domain\Entities\FileEntity;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

interface ToolInterface
{
    public function isEnabled(): bool;
    public function getDescription(): string;
    public function getDefinitions(): array;

    /**
     * @param array<FileEntity> $files
     */
    public function call(
        UserEntity $user,
        WorkspaceEntity $workspace,
        array $params = [],
        array $files = [],
    ): CallResponse;
}
