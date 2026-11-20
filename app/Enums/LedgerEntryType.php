<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Payment = 'payment';
    case WalletTopup = 'wallet_topup';
    case WalletPayment = 'wallet_payment';
    case Hold = 'hold';
    case Release = 'release';
    case Commission = 'commission';
    case Payout = 'payout';
    case Refund = 'refund';
    case PartialRefund = 'partial_refund';
    case CallOutFee = 'call_out_fee';
}
