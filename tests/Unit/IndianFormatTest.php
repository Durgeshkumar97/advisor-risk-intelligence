<?php

use App\Support\IndianFormat;

describe('IndianFormat::money', function () {

    it('groups rupees the Indian way: last three digits, then pairs', function (float $amount, string $expected) {
        expect(IndianFormat::money($amount))->toBe($expected);
    })->with([
        'zero' => [0, '₹0.00'],
        'three digits' => [999, '₹999.00'],
        'four digits' => [7500, '₹7,500.00'],
        'five digits' => [19197, '₹19,197.00'],
        'six digits (lakh)' => [114000, '₹1,14,000.00'],
        'seven digits' => [1234567.5, '₹12,34,567.50'],
        'eight digits (crore)' => [12345678.9, '₹1,23,45,678.90'],
        'nine digits' => [987654321, '₹98,76,54,321.00'],
        'rounds paise' => [1625.005, '₹1,625.01'],
    ]);

    it('puts the minus sign before the rupee symbol', function () {
        expect(IndianFormat::money(-7500))->toBe('-₹7,500.00');
        expect(IndianFormat::money(-114000.5))->toBe('-₹1,14,000.50');
    });

    it('adds a plus sign only when asked, and only for gains', function () {
        expect(IndianFormat::money(10000, signed: true))->toBe('+₹10,000.00');
        expect(IndianFormat::money(-7500, signed: true))->toBe('-₹7,500.00');
        expect(IndianFormat::money(0, signed: true))->toBe('₹0.00');
        expect(IndianFormat::money(10000))->toBe('₹10,000.00');
    });

    it('never prints a negative zero', function () {
        expect(IndianFormat::money(-0.004))->toBe('₹0.00');
        expect(IndianFormat::money(-0.004, signed: true))->toBe('₹0.00');
    });
});

describe('IndianFormat::quantity', function () {

    it('drops trailing zeros and keeps up to four decimals', function (float|string $quantity, string $expected) {
        expect(IndianFormat::quantity($quantity))->toBe($expected);
    })->with([
        'whole, stored as decimal:4' => ['400.0000', '400'],
        'one decimal' => ['12.5000', '12.5'],
        'four decimals' => ['0.1234', '0.1234'],
        'beyond four decimals rounds' => [0.12345, '0.1235'],
        'zero' => ['0.0000', '0'],
        'grouped the Indian way' => ['1234567.2500', '12,34,567.25'],
        'thousands' => [12000, '12,000'],
    ]);
});
