<?php

namespace App\Enums;

enum AchievementRuleType: string
{
    case FirstPurchase = 'first_purchase';
    case PurchaseCount = 'purchase_count';
}