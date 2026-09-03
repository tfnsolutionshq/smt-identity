<?php

namespace App\Integrations\Contracts;

use Illuminate\Http\Request;

interface DepartmentIntegrationInterface
{
    /**
     * Fetch a list of departments.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function getDepartments(Request $request);
}
