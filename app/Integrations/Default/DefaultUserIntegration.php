<?php

namespace App\Integrations\Default;

use App\Integrations\Contracts\UserIntegrationInterface;

class DefaultUserIntegration implements UserIntegrationInterface
{
    /**
     * Look up user details locally or return null.
     *
     * @param string $identifier
     * @return array|null
     */
    public function lookupUser(string $identifier): ?array
    {
        // By default, no external data to provide for registration pre-filling
        return null;
    }
}
