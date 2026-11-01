<?php

// Uses only the dedicated disposable pbl_concurrency database.
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'pbl_concurrency', 'database.connections.mysql.url' => null]);
DB::purge('mysql');
if (($argv[1] ?? '') === 'worker') {
    $queue = app(QueueService::class);
    if ($argv[2] === 'join') {
        $ticket = $queue->issue(['customer_name' => 'Concurrent demo', 'party_size' => 2], bin2hex(random_bytes(32)), $argv[3]);
    } else {
        $ticket = $queue->callNext($argv[3], (int) $argv[4]);
    }
    echo $ticket?->number ?? 'empty';
    exit;
}
if (DB::selectOne('SELECT DATABASE() AS name')->name !== 'pbl_concurrency') {
    throw new RuntimeException('Unsafe concurrency database');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$staff = User::create(['name' => 'Concurrency test', 'email' => 'concurrency@example.test', 'password' => bin2hex(random_bytes(24))]);
function batch(array $jobs): array
{
    $processes = [];
    foreach ($jobs as $job) {
        $p = new Process([PHP_BINARY, __FILE__, 'worker', ...$job], dirname(__DIR__, 2));
        $p->setTimeout(30);
        $p->start();
        $processes[] = $p;
    }
    $results = [];
    foreach ($processes as $p) {
        $p->wait();
        if (! $p->isSuccessful()) {
            throw new RuntimeException($p->getErrorOutput().$p->getOutput());
        }$results[] = trim($p->getOutput());
    }

    return $results;
}
$joins = batch(array_map(fn () => ['join', (string) Str::uuid()], range(1, 10)));
if (count(array_unique($joins)) !== 10 || QueueTicket::count() !== 10) {
    throw new RuntimeException('Duplicate registration numbers');
}
$operation = (string) Str::uuid();
$same = batch([['call', $operation, (string) $staff->id], ['call', $operation, (string) $staff->id]]);
if ($same[0] !== $same[1] || QueueTicket::where('status', 'called')->count() !== 1) {
    throw new RuntimeException('Call replay claimed multiple tickets');
}
$distinct = batch([['call', (string) Str::uuid(), (string) $staff->id], ['call', (string) Str::uuid(), (string) $staff->id]]);
sort($distinct);
if ($distinct !== ['2', '3']) {
    throw new RuntimeException('Concurrent calls broke FIFO');
}
echo "PASS MySQL: 10 simultaneous registrations; duplicate-operation replay; two intentional concurrent FIFO calls.\n";
