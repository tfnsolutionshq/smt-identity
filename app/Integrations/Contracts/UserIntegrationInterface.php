<?php

namespace App\Integrations\Contracts;

interface UserIntegrationInterface
{
    /**
     * Look up user details by identifier (e.g., Staff ID).
     *
     * @param string $identifier
     * @return array|null
     */
    public function lookupUser(string $identifier): ?array;
}
