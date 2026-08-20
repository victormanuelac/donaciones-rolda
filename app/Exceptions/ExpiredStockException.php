<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ExpiredStockException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este lote está vencido y no puede despacharse.');
    }
}
