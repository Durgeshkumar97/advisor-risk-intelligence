<?php

namespace Tests\Feature;

use App\Mail\AdminFreeTrialLeadSubmittedMail;
use App\Models\ClientIntake;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class IfaTrialLeadSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_ifa_trial_lead_is_stored_and_admin_email_is_queued(): void
    {
        Mail::fake();
        $this->createStarterPlan();

        config()->set('risksignal.lead_notifications.admin_email', 'owner@risksignal.test');
        config()->set('risksignal.lead_notifications.queue', 'mail');

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
        ])->from(route('home'))->post(route('ifa.submit'), [
            'advisor_name' => 'Durgesh Kumar',
            'whatsapp' => '+91 98765 43210',
            'email' => 'durgesh@example.test',
            'firm_name' => 'RiskSignal Advisors',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Trial started successfully.');

        $this->assertDatabaseHas('client_intakes', [
            'name' => 'Durgesh Kumar',
            'whatsapp' => '+91 98765 43210',
            'email' => 'durgesh@example.test',
            'firm_name' => 'RiskSignal Advisors',
            'status' => 'trial',
        ]);

        Mail::assertQueued(
            AdminFreeTrialLeadSubmittedMail::class,
            function (AdminFreeTrialLeadSubmittedMail $mail): bool {
                return $mail->hasTo('owner@risksignal.test')
                    && $mail->advisorName === 'Durgesh Kumar'
                    && $mail->whatsapp === '+91 98765 43210'
                    && $mail->email === 'durgesh@example.test'
                    && $mail->firmName === 'RiskSignal Advisors'
                    && $mail->ipAddress === '203.0.113.10'
                    && $mail->queue === 'mail';
            }
        );
    }

    public function test_duplicate_ifa_trial_lead_does_not_queue_admin_email(): void
    {
        Mail::fake();
        $this->createStarterPlan();

        config()->set('risksignal.lead_notifications.admin_email', 'owner@risksignal.test');

        ClientIntake::query()->create([
            'submission_uuid' => (string) Str::uuid(),
            'name' => 'Existing Advisor',
            'whatsapp' => '+91 90000 00000',
            'email' => 'existing@example.test',
            'firm_name' => 'Existing Firm',
            'status' => 'trial',
        ]);

        $response = $this->from(route('home'))->post(route('ifa.submit'), [
            'advisor_name' => 'Existing Advisor',
            'whatsapp' => '+91 91111 11111',
            'email' => 'existing@example.test',
            'firm_name' => 'Existing Firm',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Already registered.');

        $this->assertDatabaseCount('client_intakes', 1);

        Mail::assertNothingQueued();
    }

    private function createStarterPlan(): void
    {
        Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 0,
            'duration_days' => 30,
            'portfolio_limit' => 1,
            'trial_days' => 14,
            'is_active' => true,
        ]);
    }
}
