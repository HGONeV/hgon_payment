<?php

declare(strict_types=1);

namespace HGON\HgonPayment\Payment;

use DERHANSEN\SfEventMgt\Payment\AbstractPayment;

final class Paypal extends AbstractPayment
{
    protected bool $enableRedirect = true;
    protected bool $enableSuccessLink = true;
    protected bool $enableFailureLink = true;
    protected bool $enableCancelLink = true;
}
