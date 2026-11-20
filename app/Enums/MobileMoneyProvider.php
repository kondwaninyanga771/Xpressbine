<?php

namespace App\Enums;

enum MobileMoneyProvider: string
{
    case AirtelMoney = 'airtel_money';
    case MtnMomo = 'mtn_momo';
    case ZamtelKwacha = 'zamtel_kwacha';

    public function label(): string
    {
        return match ($this) {
            self::AirtelMoney => 'Airtel Money',
            self::MtnMomo => 'MTN MoMo',
            self::ZamtelKwacha => 'Zamtel Kwacha',
        };
    }
}
