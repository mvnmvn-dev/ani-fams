<?php

namespace App\Console\Commands;

use App\Models\IntegrationClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateIntegrationClient extends Command
{
    protected $signature = 'integration:client
        {system : Short system code such as hr1 or hr4}
        {name : Human-readable client name}
        {scopes* : Allowed scopes, for example employees:write documents:write}
        {--ip= : Comma-separated allowlisted IP addresses}
        {--hmac : Generate an HMAC signing secret}';

    protected $description = 'Create or rotate a scoped FAMS integration client token';

    public function handle(): int
    {
        $system = strtolower((string) $this->argument('system'));
        $scopes = array_values(array_filter(array_map('trim', (array) $this->argument('scopes'))));
        if (! preg_match('/^[a-z0-9_-]{2,20}$/', $system) || $scopes === []) {
            $this->error('Use a valid system code and at least one scope.');
            return self::FAILURE;
        }

        $prefix = 'fams_' . Str::lower(Str::random(8));
        $secret = Str::random(48);
        $client = IntegrationClient::updateOrCreate(
            ['system_code' => $system],
            [
                'name' => (string) $this->argument('name'),
                'key_prefix' => $prefix,
                'key_hash' => Hash::make($secret),
                'signing_secret' => $this->option('hmac') ? Str::random(64) : null,
                'scopes' => $scopes,
                'allowed_ips' => $this->option('ip'),
                'is_active' => true,
            ]
        );

        $this->info('Integration client created/rotated: ' . $client->system_code);
        $this->line('Bearer token (store it now; it cannot be recovered):');
        $this->line($prefix . '.' . $secret);
        if ($client->signing_secret) {
            $this->line('HMAC secret (store it now): ' . $client->signing_secret);
        }
        return self::SUCCESS;
    }
}
