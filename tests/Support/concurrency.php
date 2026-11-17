<?php

// Uses only the dedicated disposable pbl_concurrency database.
use App\Models\QueueTicket;
use App\Enums\QueueType;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'pbl_concurrency', 'database.connections.mysql.url' => null]);
DB::purge('mysql');
if (DB::selectOne('SELECT DATABASE() AS name')->name !== 'pbl_concurrency') {
    throw new RuntimeException('Unsafe concurrency database');
}
if (($argv[1] ?? '') === 'worker') {
    $queue = app(QueueService::class);
    try {
        if ($argv[2] === 'join') {
            $ticket = $queue->issue(['queue_type' => $argv[4], 'customer_name' => 'Concurrent demo', 'party_size' => 2], bin2hex(random_bytes(32)), $argv[3]);
        } else {
            $ticket = $queue->callNext($argv[3], (int) $argv[4], QueueType::from($argv[5]));
        }
        echo $ticket?->number ?? 'empty';
    } catch (ValidationException) {
        echo 'rejected';
    }
    exit;
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
$joins = batch(array_map(fn ($i) => ['join', (string) Str::uuid(), $i % 2 ? 'dine_in' : 'takeaway'], range(1, 10)));
if (count(array_unique($joins)) !== 10 || QueueTicket::count() !== 10) {
    throw new RuntimeException('Duplicate registration numbers');
}
$operation = (string) Str::uuid();
$dineNumbers = QueueTicket::where('queue_type', 'dine_in')->orderBy('number')->pluck('number')->map(fn ($number) => (string) $number)->all();
$takeNumbers = QueueTicket::where('queue_type', 'takeaway')->orderBy('number')->pluck('number')->map(fn ($number) => (string) $number)->all();
if (count($dineNumbers) !== 5 || count($takeNumbers) !== 5) {
    throw new RuntimeException('Registrations entered the wrong queues');
}
$same = batch([['call', $operation, (string) $staff->id, 'takeaway'], ['call', $operation, (string) $staff->id, 'takeaway']]);
if ($same[0] !== $same[1] || QueueTicket::where('status', 'called')->count() !== 1) {
    throw new RuntimeException('Call replay claimed multiple tickets');
}
if ($same[0] !== $takeNumbers[0] || QueueTicket::where('queue_type', 'dine_in')->where('status', 'called')->exists()) {
    throw new RuntimeException('Takeaway call modified the dine-in queue');
}
$distinct = batch([['call', (string) Str::uuid(), (string) $staff->id, 'dine_in'], ['call', (string) Str::uuid(), (string) $staff->id, 'dine_in']]);
sort($distinct, SORT_NUMERIC);
if ($distinct !== array_slice($dineNumbers, 0, 2)) {
    throw new RuntimeException('Concurrent calls broke FIFO');
}
$cross = batch([['call', (string) Str::uuid(), (string) $staff->id, 'dine_in'], ['call', (string) Str::uuid(), (string) $staff->id, 'takeaway']]);
if ($cross !== [$dineNumbers[2], $takeNumbers[1]]) {
    throw new RuntimeException('Simultaneous cross-queue calls broke independent FIFO');
}
$misusedOperation = (string) Str::uuid();
$before = QueueTicket::where('status', 'called')->count();
$misused = batch([['call', $misusedOperation, (string) $staff->id, 'dine_in'], ['call', $misusedOperation, (string) $staff->id, 'takeaway']]);
if (count(array_filter($misused, fn ($result) => $result === 'rejected')) !== 1 || QueueTicket::where('status', 'called')->count() !== $before + 1) {
    throw new RuntimeException('Cross-queue operation reuse was not rejected');
}
$queue = app(QueueService::class);
while ($queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway)) {
    // Drain only the disposable takeaway queue to exercise an empty replay.
}
$emptyOperation = (string) Str::uuid();
$queue->callNext($emptyOperation, $staff->id, QueueType::Takeaway);
$newTicket = $queue->issue(['queue_type' => 'takeaway', 'customer_name' => 'Empty replay test', 'party_size' => 1], bin2hex(random_bytes(32)), (string) Str::uuid());
if ($queue->callNext($emptyOperation, $staff->id, QueueType::Takeaway) !== null || $newTicket->fresh()->status->value !== 'waiting') {
    throw new RuntimeException('Empty call replay advanced a later registration');
}
echo "PASS MySQL: mixed-queue simultaneous registrations; same-queue FIFO/replay; cross-queue calls and operation isolation; empty replay.\n";
