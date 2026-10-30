<?php

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('PHP preset')->preset()->php();

arch('enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();
