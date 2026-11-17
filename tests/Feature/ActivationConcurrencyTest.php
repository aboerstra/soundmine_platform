<?php

namespace Tests\Feature;

use App\Domain\Activations\Enums\ActivationStatus;
use App\Domain\Licensing\Models\License;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesActivationRecords;
use Tests\TestCase;

class ActivationConcurrencyTest extends TestCase
{
    use CreatesActivationRecords, DatabaseMigrations;

    /** Committed fixtures are required: workers use independent database connections. */
    private function compete(License $lockedLicense, array $requests, int $expectedWaiters = 2): array
    {
        $processes = [];
        $readyFiles = [];
        $environment = [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => 'pgsql', 'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password'), 'DB_URL' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];
        DB::beginTransaction();
        try {
            License::whereKey($lockedLicense->id)->lockForUpdate()->firstOrFail();
            foreach ($requests as $request) {
                $readyFile = sys_get_temp_dir().'/soundmine-activation-'.Str::uuid();
                $readyFiles[] = $readyFile;
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/activation-request-worker.php')], base_path(), $environment);
                $process->setInput(json_encode($request + ['ready_file' => $readyFile], JSON_THROW_ON_ERROR));
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while (count(array_filter($readyFiles, 'is_file')) !== count($readyFiles) && microtime(true) < $deadline) {
                usleep(10000);
                clearstatcache();
            }
            $this->assertCount(count($readyFiles), array_filter($readyFiles, 'is_file'), 'Activation workers did not reach the barrier.');
            $waiters = 0;
            while ($waiters < $expectedWaiters && microtime(true) < $deadline) {
                DB::select('select pg_stat_clear_snapshot()');
                $waiters = DB::table('pg_stat_activity')->whereIn('application_name', $readyFiles)->where('wait_event_type', 'Lock')->count();
                if ($waiters < $expectedWaiters) {
                    usleep(10000);
                }
            }
            $this->assertSame($expectedWaiters, $waiters, 'Workers must contend on the held license lock before release.');
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach ($readyFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function workerRequest(License $license, string $hash): array
    {
        return ['path' => '/api/v1/products/'.$license->product->slug.'/activations', 'request_id' => (string) Str::uuid(), 'body' => $this->activationPayload($this->authorization($license, $hash), $hash)];
    }

    public function test_competing_installations_cannot_both_claim_the_final_seat(): void
    {
        $license = $this->activationLicense(attributes: ['max_activations' => 1]);
        $requests = [$this->workerRequest($license, str_repeat('a', 64)), $this->workerRequest($license, str_repeat('b', 64))];

        $results = $this->compete($license, $requests);

        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 409], $statuses);
        $this->assertSame(1, $license->activations()->where('status', ActivationStatus::Active)->count());
        $this->assertDatabaseCount('installation_tokens', 1);
    }

    public function test_simultaneous_exact_retries_share_one_activation_and_credential(): void
    {
        $license = $this->activationLicense(attributes: ['max_activations' => 1]);
        $request = $this->workerRequest($license, str_repeat('a', 64));

        $results = $this->compete($license, [$request, $request]);

        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame($results[0]['body']['installation_token'], $results[1]['body']['installation_token']);
        $this->assertSame($results[0]['body']['data']['activation']['id'], $results[1]['body']['data']['activation']['id']);
        $this->assertDatabaseCount('activations', 1);
        $this->assertDatabaseCount('installation_tokens', 1);
    }

    public function test_concurrent_products_on_one_device_do_not_share_a_quota(): void
    {
        $first = $this->activationLicense(attributes: ['max_activations' => 1]);
        $second = $this->activationLicense($first->customer, $this->product('Another Soundmine product'), ['max_activations' => 1]);

        $results = $this->compete($first, [$this->workerRequest($first, str_repeat('a', 64)), $this->workerRequest($second, str_repeat('a', 64))], expectedWaiters: 1);

        $this->assertSame([201, 201], array_column($results, 'status'));
        $this->assertDatabaseCount('activations', 2);
        $this->assertNotSame($results[0]['body']['installation_token'], $results[1]['body']['installation_token']);
    }
}
