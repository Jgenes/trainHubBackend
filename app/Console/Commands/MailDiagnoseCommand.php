<?php

namespace App\Console\Commands;

use App\Support\MailDiagnostics;
use Illuminate\Console\Command;

class MailDiagnoseCommand extends Command
{
    protected $signature = 'mail:diagnose
        {--to= : Send a real test message to this address}
        {--no-network : Only check the configuration, skip DNS/TCP tests}';

    protected $description = 'Explain why activation and OTP emails never arrive';

    public function handle(MailDiagnostics $diagnostics): int
    {
        $to      = $this->option('to');
        $report  = $diagnostics->run(! $this->option('no-network'), is_string($to) && $to !== '' ? $to : null);

        $this->newLine();
        $this->line('  <options=bold>Mail configuration</>');
        $this->line('  '.str_repeat('-', 62));

        foreach ($report['config'] as $key => $value) {
            $this->line(sprintf('  %-18s %s', $key, $this->printable($value)));
        }

        $this->newLine();
        $this->line('  <options=bold>Checks</>');
        $this->line('  '.str_repeat('-', 62));

        foreach ($report['checks'] as $check) {
            $this->line(sprintf('  %s %s: %s', $this->icon($check['status']), $check['name'], $check['message']));

            if ($check['hint'] !== null) {
                $this->line('      <fg=yellow>→ '.$check['hint'].'</>');
            }
        }

        $this->newLine();
        $summary = $report['summary'];
        $this->line(sprintf(
            '  %d ok, %d warning(s), %d failure(s).',
            $summary['ok'],
            $summary['warn'],
            $summary['fail'],
        ));

        if ($report['ok']) {
            $this->line('  <fg=green>No blocking problem found in the mail configuration.</>');

            if ($to === null || $to === '') {
                $this->line('  Run again with --to=you@example.com to prove delivery end to end.');
            }
        } else {
            $this->line('  <fg=red>Email cannot be delivered until the failures above are fixed.</>');
        }

        $this->newLine();

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }

    private function icon(string $status): string
    {
        return match ($status) {
            'ok'   => '<fg=green>✔</>',
            'warn' => '<fg=yellow>!</>',
            'fail' => '<fg=red>✘</>',
            default => '<fg=gray>·</>',
        };
    }

    private function printable(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<fg=gray>not set</>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
