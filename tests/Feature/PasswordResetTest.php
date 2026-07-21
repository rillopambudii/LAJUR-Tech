<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function owner(): User
    {
        $tenant = Tenant::create(['name' => 'Co', 'slug' => 'co', 'plan' => 'basic', 'subscription_status' => 'active']);

        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Ucup', 'email' => 'ucup@co.id',
            'password' => 'lama12345', 'role' => User::ROLE_OWNER,
        ]);
    }

    public function test_full_reset_flow_lets_user_login_with_new_password(): void
    {
        Notification::fake();
        $user = $this->owner();

        $this->post('/lupa-password', ['email' => 'ucup@co.id'])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class);

        $token = null;
        Notification::sent($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'ucup@co.id',
            'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Auth::attempt(['email' => 'ucup@co.id', 'password' => 'baru12345']));
        $this->assertFalse(Auth::attempt(['email' => 'ucup@co.id', 'password' => 'lama12345']));
    }

    public function test_reset_email_is_branded_and_localized(): void
    {
        Notification::fake();
        $user = $this->owner();

        $this->post('/lupa-password', ['email' => 'ucup@co.id']);

        // Override AppServiceProvider (ResetPassword::toMailUsing) dipakai, bukan
        // template bawaan Laravel yg berbahasa Inggris & tak berbrand.
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Atur Ulang Kata Sandi — Lajur'
                && $mail->actionText === 'Atur Ulang Kata Sandi'
                && str_contains(implode(' ', $mail->introLines), 'kata sandi akun Lajur')
                && ! str_contains(implode(' ', $mail->introLines), 'You are receiving');
        });
    }

    public function test_request_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();

        // Email tak terdaftar → tetap redirect + pesan sama, tak ada error/bocor.
        $this->post('/lupa-password', ['email' => 'tidakada@x.id'])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_reset_rejects_invalid_token(): void
    {
        $this->owner();

        $this->post('/reset-password', [
            'token' => 'token-palsu', 'email' => 'ucup@co.id',
            'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Auth::attempt(['email' => 'ucup@co.id', 'password' => 'lama12345']));
    }
}
