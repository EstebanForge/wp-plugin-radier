<?php

declare(strict_types=1);

arch('source files declare strict types')
    ->expect('WpPluginRadier')
    ->toUseStrictTypes();

arch('source files do not use debugging functions')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();

arch('services are final classes')
    ->expect('WpPluginRadier\Services')
    ->classes()
    ->toBeFinal();

arch('value objects are readonly')
    ->expect(['WpPluginRadier\PluginContext'])
    ->toBeReadonly();

arch('services do not call WordPress procedural hook functions directly')
    ->expect(['add_action', 'add_filter'])
    ->not->toBeUsedIn('WpPluginRadier\Services');
