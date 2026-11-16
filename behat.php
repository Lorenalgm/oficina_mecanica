<?php

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;

return (new Config())
    ->withProfile((new Profile('default'))
        ->withSuite((new Suite('saga'))
            ->withPaths('%paths.base%/features')
            ->withContexts(SagaContext::class)));
