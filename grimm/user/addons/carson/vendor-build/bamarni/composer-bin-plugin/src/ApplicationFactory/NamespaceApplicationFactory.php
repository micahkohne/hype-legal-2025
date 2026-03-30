<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\Bamarni\Composer\Bin\ApplicationFactory;

use BoldMinded\Carson\Dependency\Composer\Console\Application;
interface NamespaceApplicationFactory
{
    public function create(Application $existingApplication) : Application;
}
