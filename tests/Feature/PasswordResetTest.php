<?php

namespace Tests\Feature;

use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_forgot_password_form_is_available(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_forgot_password_submit_succeeds_even_for_unknown_email(): void
    {
        $this->post('/forgot-password', [
            'email' => 'nobody@example.com',
        ])->assertRedirect();
    }
}
