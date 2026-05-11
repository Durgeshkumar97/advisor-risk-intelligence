<?php

if (!function_exists('money_in_rupees')) {

    function money_in_rupees(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}
