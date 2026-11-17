<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\InvoiceStatus;
use DomainException;

final class InvalidInvoiceStatusTransitionException extends DomainException
{
    public function __construct(InvoiceStatus $from, InvoiceStatus $to)
    {
        parent::__construct(sprintf(
            'Cannot transition invoice status from [%s] to [%s].',
            $from->value,
            $to->value
        ));
    }
}
