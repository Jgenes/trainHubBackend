<?php

namespace Tests\Feature;

use App\Mail\MailTestMail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailDiagnosticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_mail_check_endpoint_does_not_exist_until_a_token_is_configured(): void
    {
        config(['mail.diagnostics.token' => null]);

        $this->getJson('/api/mail-check')->assertNotFound();
    }

    public function test_mail_check_endpoint_requires_the_configured_token(): void
    {
        config(['mail.diagnostics.token' => 'super-secret']);

        $this->getJson('/api/mail-check')->assertForbidden();
        $this->getJson('/api/mail-check?token=wrong')->assertForbidden();
    }

    public function test_mail_check_flags_a_log_mailer_as_a_failure(): void
    {
        config([
            'mail.diagnostics.token' => 'super-secret',
            'mail.default'           => 'log',
        ]);

        $response = $this->getJson('/api/mail-check?token=super-secret')->assertStatus(503);

        $response->assertJsonPath('ok', false);
        $this->assertSame('fail', $response->json('checks.0.status'));
        $this->assertStringContainsString('never delivers', $response->json('checks.0.message'));
    }

    public function test_mail_check_reports_a_healthy_configuration_and_hides_the_password(): void
    {
        config([
            'mail.diagnostics.token'    => 'super-secret',
            'mail.default'              => 'smtp',
            'mail.mailers.smtp.host'     => 'mail.example.test',
            'mail.mailers.smtp.port'     => 465,
            'mail.mailers.smtp.scheme'   => 'smtps',
            'mail.mailers.smtp.username' => 'info@example.test',
            'mail.mailers.smtp.password' => 'do-not-leak-me',
            'mail.from.address'          => 'info@example.test',
        ]);

        // network=0 keeps the test offline: no DNS lookups, no sockets.
        $response = $this->getJson('/api/mail-check?token=super-secret&network=0')->assertOk();

        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('config.password', 'set (14 characters)');
        $response->assertJsonStructure(['ok', 'config', 'checks', 'summary']);
        $this->assertStringNotContainsString('do-not-leak-me', $response->getContent());
    }

    public function test_mail_check_detects_a_port_scheme_mismatch(): void
    {
        config([
            'mail.diagnostics.token'    => 'super-secret',
            'mail.default'              => 'smtp',
            'mail.mailers.smtp.host'     => 'mail.example.test',
            'mail.mailers.smtp.port'     => 587,
            'mail.mailers.smtp.scheme'   => 'smtps',
            'mail.mailers.smtp.username' => 'info@example.test',
            'mail.mailers.smtp.password' => 'do-not-leak-me',
            'mail.from.address'          => 'info@example.test',
        ]);

        $response = $this->getJson('/api/mail-check?token=super-secret&network=0')->assertStatus(503);

        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('implicit TLS', $response->json('checks.2.message'));
    }

    public function test_mail_check_flags_placeholder_credentials(): void
    {
        config([
            'mail.diagnostics.token'    => 'super-secret',
            'mail.default'              => 'smtp',
            'mail.mailers.smtp.host'     => 'mail.example.test',
            'mail.mailers.smtp.port'     => 465,
            'mail.mailers.smtp.scheme'   => 'smtps',
            'mail.mailers.smtp.username' => 'info@example.test',
            'mail.mailers.smtp.password' => 'your-password',
            'mail.from.address'          => 'info@example.test',
        ]);

        $response = $this->getJson('/api/mail-check?token=super-secret&network=0')->assertStatus(503);

        $this->assertStringContainsString(
            'still holds the example value',
            $response->json('checks.4.message')
        );
    }

    public function test_registration_reports_whether_the_activation_email_was_sent(): void
    {
        Mail::fake();

        $this->postJson('/api/register', [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.test',
            'phone'                 => '0712345678',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('mail_sent', true);
    }

    public function test_registration_survives_a_broken_mail_server(): void
    {
        // Nothing listens on 127.0.0.1:1, so the SMTP connection is refused
        // immediately. The account must still be created.
        config([
            'mail.default'              => 'smtp',
            'mail.mailers.smtp.host'    => '127.0.0.1',
            'mail.mailers.smtp.port'    => 1,
            'mail.mailers.smtp.scheme'  => null,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        $this->postJson('/api/register', [
            'name'                  => 'Broken Mail',
            'email'                 => 'broken@example.test',
            'phone'                 => '0712345679',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('mail_sent', false);
    }

    public function test_http_mailer_posts_the_message_to_the_provider_api(): void
    {
        Http::fake([
            'api.resend.com/*' => Http::response(['id' => 'msg_123'], 200),
        ]);

        config([
            'mail.default'             => 'http',
            'mail.mailers.http.provider' => 'resend',
            'mail.mailers.http.api_key'  => 're_test_key',
            'mail.from.address'        => 'noreply@example.test',
            'mail.from.name'           => 'Training Hub',
        ]);

        Mail::to('learner@example.test')->send(new MailTestMail());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.resend.com/emails')
                && $request->hasHeader('Authorization', 'Bearer re_test_key')
                && $request['to'] === ['learner@example.test']
                && $request['subject'] === 'Training Hub mail delivery test'
                && str_contains((string) $request['html'], 'mail delivery test');
        });
    }
}
