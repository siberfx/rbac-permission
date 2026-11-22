<?php

declare(strict_types=1);

use Siberfx\RbacPermission\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
| Every test in tests/Feature and tests/Unit boots the Testbench application.
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');
