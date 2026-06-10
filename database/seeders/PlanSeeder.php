<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [

            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 999,
                'duration_days' => 30,
                'portfolio_limit' => 1,
                'daily_reports' => true,
                'client_script' => true,
                'whatsapp_delivery' => true,
                'branded_pdf' => false,
                'priority_support' => false,
                'multi_advisor' => false,
                'trial_days' => 14,
                'is_active' => true,
            ],

            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 2499,
                'duration_days' => 30,
                'portfolio_limit' => 5,
                'daily_reports' => true,
                'client_script' => true,
                'whatsapp_delivery' => true,
                'branded_pdf' => true,
                'priority_support' => true,
                'multi_advisor' => false,
                'trial_days' => 14,
                'is_active' => true,
            ],

            [
                'name' => 'Team',
                'slug' => 'team',
                'price' => 4999,
                'duration_days' => 30,
                'portfolio_limit' => 25,
                'daily_reports' => true,
                'client_script' => true,
                'whatsapp_delivery' => true,
                'branded_pdf' => true,
                'priority_support' => true,
                'multi_advisor' => true,
                'trial_days' => 14,
                'is_active' => true,
            ],

        ];

        foreach ($plans as $plan) {

            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
