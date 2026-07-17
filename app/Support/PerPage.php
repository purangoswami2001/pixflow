<?php

namespace App\Support;

use Illuminate\Http\Request;

class PerPage
{
    /**
     * @param  list<int>  $allowed
     */
    public static function resolve(Request $request, int $default = 10, array $allowed = [10, 20, 40, 50]): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }
}
