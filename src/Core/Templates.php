<?php

namespace App\Core;

/** TR bildirim şablonları ({yer_tutucu} tek kaynaktan). */
final class Templates
{
    public static function confirmation(string $shop, string $dateLabel, string $time, string $staff, string $service, string $price, string $cancelUrl): string
    {
        return "{$shop}\nRandevunuz alindi ✓\n{$dateLabel} {$time}\n{$staff} • {$service} • {$price}\nIptal icin: {$cancelUrl}";
    }

    public static function reminder(string $shop, string $time, string $staff, string $service, string $cancelUrl): string
    {
        return "{$shop} - Hatirlatma\nBugun {$time} randevunuz var.\n{$staff} • {$service}\nGelemeyecekseniz iptal: {$cancelUrl}";
    }

    public static function cancelCustomer(string $shop, string $dateLabel, string $time): string
    {
        return "{$shop}\nRandevunuz iptal edildi.\n{$dateLabel} {$time}\nYeni randevu icin sitemizi ziyaret edin.";
    }

    public static function cancelOwner(string $dateLabel, string $time, string $staff, string $customer): string
    {
        return "Randevu iptali\n{$dateLabel} {$time}\n{$staff} • {$customer}";
    }
}
