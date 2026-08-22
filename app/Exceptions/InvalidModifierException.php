<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An add-on selection that the menu does not permit: an option belonging to
 * another product, too many choices from one group, or a required group left
 * empty. Checked server-side because the customer's phone will be posting
 * these ids.
 */
class InvalidModifierException extends RuntimeException
{
    public static function notOnProduct(string $productName): self
    {
        return new self("That option is not available for '{$productName}'.");
    }

    public static function tooMany(string $groupName, int $max): self
    {
        return new self("Choose at most {$max} option(s) for '{$groupName}'.");
    }

    public static function required(string $groupName, int $min): self
    {
        return new self("Choose at least {$min} option(s) for '{$groupName}'.");
    }
}
