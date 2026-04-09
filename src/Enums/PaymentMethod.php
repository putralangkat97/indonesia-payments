<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Enums;

enum PaymentMethod: string
{
    case VIRTUAL_ACCOUNT = "va";
    case EWALLET = "ewallet";
    case CARD = "card";
    case QRIS = "qris";
}
