<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Pest bootstrap. Feature tests run on the Laravel test case; the existing
| PHPUnit-style classes keep working alongside Pest tests.
*/

pest()->extend(TestCase::class)->in('Feature');
