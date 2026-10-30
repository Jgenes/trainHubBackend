<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * SMTP replacement that posts messages over HTTPS to a transactional email
 * API (Resend, Brevo or SendGrid).
 *
 * Why this exists: several hosts — Render's free plan among them — block
 * outbound SMTP ports (25, 465, 587) and only allow HTTPS, so any SMTP
 * configuration fails with "connection timed out" and OTP mail never leaves
 * the server. Posting to the provider's REST API works on those plans.
 *
 * Enable it with:
 *
 *     MAIL_MAILER=http
 *     MAIL_HTTP_PROVIDER=resend     # resend | brevo | sendgrid
 *     MAIL_HTTP_API_KEY=re_xxx
 */
class HttpApiTransport implements TransportInterface
{
    private string $provider;

    private string $apiKey;

    private string $endpoint;

    private int $timeout;

    public function __construct(array $config = [])
    {
        $provider       = strtolower(trim((string) ($config['provider'] ?? '')));
        $this->provider = $provider !== '' ? $provider : 'resend';
        $this->apiKey   = (string) ($config['api_key'] ?? '');
        $this->timeout  = (int) ($config['timeout'] ?? 15);

        // An empty MAIL_HTTP_ENDPOINT (the .env.example default) must fall
        // back to the provider's URL, not disable the transport.
        $endpoint       = trim((string) ($config['endpoint'] ?? ''));
        $this->endpoint = $endpoint !== '' ? $endpoint : $this->defaultEndpoint($this->provider);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (! $message instanceof Email) {
            throw new TransportException('The [http] mailer can only send Symfony Mime Email messages.');
        }

        if ($this->apiKey === '') {
            throw new TransportException(
                "MAIL_HTTP_API_KEY is empty, so the [{$this->provider}] mail API cannot be called."
            );
        }

        if ($this->endpoint === '') {
            throw new TransportException(
                "Unknown MAIL_HTTP_PROVIDER [{$this->provider}]. Use resend, brevo or sendgrid, "
                .'or set MAIL_HTTP_ENDPOINT to the provider\'s send-message URL.'
            );
        }

        $payload = $this->payload($message, $envelope ??= Envelope::create($message));

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint, $payload);
        } catch (\Throwable $e) {
            throw new TransportException(
                "Could not reach the [{$this->provider}] mail API: ".$e->getMessage(), 0, $e
            );
        }

        if ($response->failed()) {
            throw new TransportException(sprintf(
                'The [%s] mail API rejected the message with HTTP %d: %s',
                $this->provider,
                $response->status(),
                $this->shorten($response->body())
            ));
        }

        $sent = new SentMessage($message, $envelope);

        $id = $response->json('id') ?? $response->json('messageId');

        if (is_string($id) && $id !== '') {
            $sent->setMessageId($id);
        }

        return $sent;
    }

    public function __toString(): string
    {
        return 'http://'.$this->provider;
    }

    /* ------------------------------------------------------------------ */

    private function headers(): array
    {
        return match ($this->provider) {
            'brevo'    => ['api-key' => $this->apiKey],
            'sendgrid' => ['Authorization' => 'Bearer '.$this->apiKey],
            default    => ['Authorization' => 'Bearer '.$this->apiKey],
        };
    }

    private function defaultEndpoint(string $provider): string
    {
        return match ($provider) {
            'resend'   => 'https://api.resend.com/emails',
            'brevo'    => 'https://api.brevo.com/v3/smtp/email',
            'sendgrid' => 'https://api.sendgrid.com/v3/mail/send',
            default    => '',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $message, Envelope $envelope): array
    {
        $from    = $message->getFrom()[0] ?? $envelope->getSender();
        $subject = (string) $message->getSubject();
        $html    = $message->getHtmlBody();
        $text    = $message->getTextBody();

        $to = array_map(fn (Address $a) => $a->getAddress(), $message->getTo());

        if ($to === []) {
            // Mail::to() sets the header, but fall back to the envelope so a
            // message with only recipients there is still delivered.
            $to = array_map(fn (Address $a) => $a->getAddress(), $envelope->getRecipients());
        }

        $cc  = array_map(fn (Address $a) => $a->getAddress(), $message->getCc());
        $bcc = array_map(fn (Address $a) => $a->getAddress(), $message->getBcc());

        $attachments = [];

        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = [
                'filename' => $attachment->getFilename() ?? 'attachment',
                'content'  => base64_encode($attachment->getBody()),
                'type'     => $attachment->getContentType(),
            ];
        }

        return match ($this->provider) {
            'brevo'    => $this->brevoPayload($from, $to, $cc, $bcc, $subject, $html, $text, $attachments),
            'sendgrid' => $this->sendgridPayload($from, $to, $cc, $bcc, $subject, $html, $text, $attachments),
            default    => $this->resendPayload($from, $to, $cc, $bcc, $subject, $html, $text, $attachments),
        };
    }

    private function resendPayload(Address $from, array $to, array $cc, array $bcc, string $subject, ?string $html, ?string $text, array $attachments): array
    {
        $payload = array_filter([
            'from'    => $this->formatAddress($from),
            'to'      => $to,
            'cc'      => $cc,
            'bcc'     => $bcc,
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ], fn ($value) => $value !== null && $value !== []);

        if ($attachments !== []) {
            $payload['attachments'] = array_map(fn (array $a) => [
                'filename' => $a['filename'],
                'content'  => $a['content'],
            ], $attachments);
        }

        return $payload;
    }

    private function brevoPayload(Address $from, array $to, array $cc, array $bcc, string $subject, ?string $html, ?string $text, array $attachments): array
    {
        $payload = array_filter([
            'sender'      => ['email' => $from->getAddress(), 'name' => $from->getName() ?: $from->getAddress()],
            'to'          => array_map(fn (string $email) => ['email' => $email], $to),
            'cc'          => array_map(fn (string $email) => ['email' => $email], $cc),
            'bcc'         => array_map(fn (string $email) => ['email' => $email], $bcc),
            'subject'     => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
        ], fn ($value) => $value !== null && $value !== []);

        if ($attachments !== []) {
            $payload['attachment'] = array_map(fn (array $a) => [
                'name'    => $a['filename'],
                'content' => $a['content'],
            ], $attachments);
        }

        return $payload;
    }

    private function sendgridPayload(Address $from, array $to, array $cc, array $bcc, string $subject, ?string $html, ?string $text, array $attachments): array
    {
        $personalization = array_filter([
            'to'  => array_map(fn (string $email) => ['email' => $email], $to),
            'cc'  => array_map(fn (string $email) => ['email' => $email], $cc),
            'bcc' => array_map(fn (string $email) => ['email' => $email], $bcc),
        ], fn ($value) => $value !== []);

        $content = [];

        if ($text !== null && $text !== '') {
            $content[] = ['type' => 'text/plain', 'value' => $text];
        }

        if ($html !== null && $html !== '') {
            $content[] = ['type' => 'text/html', 'value' => $html];
        }

        if ($content === []) {
            $content[] = ['type' => 'text/plain', 'value' => ' '];
        }

        $payload = [
            'personalizations' => [$personalization],
            'from'             => ['email' => $from->getAddress(), 'name' => $from->getName() ?: $from->getAddress()],
            'subject'          => $subject,
            'content'          => $content,
        ];

        if ($attachments !== []) {
            $payload['attachments'] = array_map(fn (array $a) => [
                'content'     => $a['content'],
                'filename'    => $a['filename'],
                'type'        => $a['type'] ?? 'application/octet-stream',
                'disposition' => 'attachment',
            ], $attachments);
        }

        return $payload;
    }

    private function formatAddress(Address $address): string
    {
        return $address->getName() === ''
            ? $address->getAddress()
            : $address->getName().' <'.$address->getAddress().'>';
    }

    private function shorten(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        return strlen($body) > 300 ? substr($body, 0, 300).'…' : $body;
    }
}
