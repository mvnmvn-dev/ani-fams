<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use PDO;

trait UsesPdo
{
    protected function pdo(): PDO
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }
}