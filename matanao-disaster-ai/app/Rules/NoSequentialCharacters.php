<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoSequentialCharacters implements ValidationRule
{
    /**
     * Minimum length of a sequential run that triggers a rejection.
     */
    protected int $threshold;

    public function __construct(int $threshold = 3)
    {
        $this->threshold = $threshold;
    }

    /**
     * Run the validation rule.
     *
     * Rejects passwords that contain sequential runs (ascending or
     * descending) of letters or digits, e.g. "abc", "123", "cba", "987".
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $chars = str_split(strtolower($value));
        $run = 1;

        for ($i = 1; $i < count($chars); $i++) {
            $prev = ord($chars[$i - 1]);
            $curr = ord($chars[$i]);

            $bothAlnum = ctype_alnum($chars[$i - 1]) && ctype_alnum($chars[$i]);

            if ($bothAlnum && ($curr - $prev === 1 || $curr - $prev === -1)) {
                $run++;

                if ($run >= $this->threshold) {
                    $fail('Password must not contain sequential characters (e.g. "abc", "123", "cba", "987").');

                    return;
                }
            } else {
                $run = 1;
            }
        }
    }
}
