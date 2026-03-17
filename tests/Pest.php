<?php

pest()->extend(Tests\TestCase::class)
    ->in(
        'Feature',
        '../Modules/Notification/tests/Feature',
        '../Modules/Settings/tests/Feature',
        '../Modules/Finance/tests/Feature',
        '../Modules/Ecommerce/Tests/Feature',
        '../Modules/Blog/Tests/Feature',
        '../Modules/Permission/Tests/Feature',
    );
