<?php

declare(strict_types=1);

namespace OpenTelemetry\DevTools\Tests\Unit\Behavior;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

trait UsesVfsTrait
{
    private vfsStreamDirectory $root;

    protected function setUpVcs()
    {
        $this->root = vfsStream::setup(
            $this->getVcsRootName()
        );
    }

    protected function getVcsRootName(): string
    {
        return UsesVfsConstants::ROOT_DIR;
    }
}
