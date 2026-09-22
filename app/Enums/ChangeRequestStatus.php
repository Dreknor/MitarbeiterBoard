<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case Beantragt = 'beantragt';
    case Genehmigt = 'genehmigt';
    case Abgelehnt = 'abgelehnt';

    public function label(): string
    {
        return match ($this) {
            self::Beantragt => 'Beantragt',
            self::Genehmigt => 'Genehmigt',
            self::Abgelehnt => 'Abgelehnt',
        };
    }
}

