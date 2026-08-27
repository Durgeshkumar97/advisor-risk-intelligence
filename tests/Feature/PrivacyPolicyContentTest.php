<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The privacy policy carries commitments that were previously absent or vague:
 * a concrete retention period, a way to exercise data rights, and a named
 * Grievance Officer (DPDP Act 2023 §13). These assertions exist so a future
 * edit cannot quietly drop them.
 *
 * This checks the page states them — not that a lawyer has approved them.
 */
class PrivacyPolicyContentTest extends TestCase
{
    public function test_it_states_a_concrete_retention_period(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('permanently purged within 30 days')
            ->assertSee('days of account closure');
    }

    public function test_it_names_a_grievance_officer_with_a_response_window(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Grievance Officer')
            ->assertSee('Digital Personal Data Protection Act')
            ->assertSee('acknowledgement within 48 hours');
    }

    public function test_it_gives_a_mechanism_for_exercising_data_rights(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('To exercise these rights')
            ->assertSee('support@risksignal.in');
    }

    public function test_the_postal_address_placeholder_is_still_flagged(): void
    {
        // Deliberately asserts the placeholder is PRESENT. When the real
        // address is filled in this test should fail — that failure is the
        // reminder to delete this test, not a regression.
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('[ADD YOUR POSTAL ADDRESS]');
    }
}
