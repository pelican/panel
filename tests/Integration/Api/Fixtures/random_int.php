<?php

namespace App\Helpers;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;

// Utilities::randomStringWithSpecialCharacters salts generated database passwords through
// unqualified random_int() calls, which no Laravel hook can pin. Shadowing the function inside
// App\Helpers lets fixture tests opt into determinism through the flag on FixtureTestCase while
// every other caller falls through to the real generator.
//
// PHP caches an unqualified call site to the global function the first time it runs, so this
// must be defined before any test touches Utilities. Composer loads it through autoload-dev
// files, which is why it is not declared inside FixtureTestCase.php.
if (!function_exists(__NAMESPACE__ . '\random_int')) {
    function random_int(int $min, int $max): int
    {
        return FixtureTestCase::$pinRandomInt
            ? $min
            : \random_int($min, $max);
    }
}
