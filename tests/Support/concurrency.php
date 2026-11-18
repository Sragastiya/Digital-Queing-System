<?php

// Uses only the dedicated disposable pbl_concurrency database.
use App\Models\QueueTicket;
use App\Enums\QueueType;
use App\Models\QueueEvent;
use App\Models\QueueSetting;
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
        } elseif ($argv[2] === 'call') {
            $ticket = $queue->callNext($argv[3], (int) $argv[4], QueueType::from($argv[5]));
        } elseif ($argv[2] === 'give') {
            $ticket = $queue->giveTurn(QueueTicket::findOrFail((int) $argv[4]), $argv[3]);
        } elseif ($argv[2] === 'switch') {
            $result = $queue->switchService(QueueTicket::findOrFail((int) $argv[4]), trim(stream_get_contents(STDIN)), QueueType::from($argv[5]), $argv[3]);
            $ticket = $result['ticket'];
        } elseif ($argv[2] === 'cancel') {
            $ticket = $queue->transition(QueueTicket::findOrFail((int) $argv[4]), 'cancel', $argv[3], null, 'other');
        } else {
            throw new RuntimeException('Unknown concurrency action');
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
        $secret = $job[0] === 'switch' ? array_pop($job) : null;
        $p = new Process([PHP_BINARY, __FILE__, 'worker', ...$job], dirname(__DIR__, 2));
        if ($secret !== null) {
            $p->setInput($secret);
        }
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

// Drain pending test tickets without deleting history, then exercise customer action races.
foreach (QueueType::cases() as $type) {
    while ($queue->callNext((string) Str::uuid(), $staff->id, $type)) {}
}
$issue = function (QueueType $type) use ($queue): array {
    $secret = bin2hex(random_bytes(32));
    return [$queue->issue(['queue_type' => $type->value, 'customer_name' => 'Customer options concurrency', 'party_size' => 2], $secret, (string) Str::uuid()), $secret];
};
[$source, $secret] = $issue(QueueType::DineIn);
[$destination] = $issue(QueueType::Takeaway);
$operation = (string) Str::uuid();
$before = QueueTicket::count();
$sameTransfer = batch([['switch', $operation, (string) $source->id, 'takeaway', $secret], ['switch', $operation, (string) $source->id, 'takeaway', $secret]]);
if ($sameTransfer[0] !== $sameTransfer[1] || $sameTransfer[0] === 'rejected' || QueueTicket::count() !== $before + 1) {
    throw new RuntimeException('Concurrent transfer retries created duplicate replacement tickets');
}
foreach (QueueType::cases() as $type) {
    while ($queue->callNext((string) Str::uuid(), $staff->id, $type)) {}
}
[$source] = $issue(QueueType::DineIn);
$followers = [];
for ($i = 0; $i < 4; $i++) { [$followers[]] = $issue(QueueType::DineIn); }
[$other] = $issue(QueueType::Takeaway);
$otherBefore = $other->fresh()->getRawOriginal();
$operation = (string) Str::uuid();
$race = batch([['give', $operation, (string) $source->id], ['give', $operation, (string) $source->id], ['call', (string) Str::uuid(), (string) $staff->id, 'dine_in'], ['join', (string) Str::uuid(), 'dine_in']]);
$waitingIds = fn () => QueueTicket::where('queue_type', 'dine_in')->where('status', 'waiting')->orderBy('queue_order')->pluck('id')->all();
$new = QueueTicket::where('queue_type', 'dine_in')->orderByDesc('number')->firstOrFail();
if ($race[0] === 'rejected') {
    $expected = [...array_map(fn ($ticket) => $ticket->id, $followers), $new->id];
    $called = $source;
} else {
    $expected = [$followers[1]->id, $followers[2]->id, $source->id, $followers[3]->id, $new->id];
    $called = $followers[0];
}
if ($race[0] !== $race[1] || $waitingIds() !== $expected || $called->fresh()->status->value !== 'called' || $otherBefore !== $other->fresh()->getRawOriginal()) {
    throw new RuntimeException('Give-turn/call/registration race violated a serialized queue order');
}
foreach (QueueType::cases() as $type) {
    while ($queue->callNext((string) Str::uuid(), $staff->id, $type)) {}
}
[$source, $secret] = $issue(QueueType::DineIn);
$before = QueueTicket::count();
$race = batch([['switch', (string) Str::uuid(), (string) $source->id, 'takeaway', $secret], ['cancel', (string) Str::uuid(), (string) $source->id]]);
if (count(array_filter($race, fn ($result) => $result === 'rejected')) !== 1
    || QueueTicket::count() !== $before + ($race[0] === 'rejected' ? 0 : 1)
    || $source->fresh()->status->value !== 'cancelled') {
    throw new RuntimeException('Transfer/cancellation race was not atomic');
}
foreach (QueueType::cases() as $type) {
    while ($queue->callNext((string) Str::uuid(), $staff->id, $type)) {}
}
[$source, $secret] = $issue(QueueType::DineIn);
$before = QueueTicket::count();
$race = batch([['switch', (string) Str::uuid(), (string) $source->id, 'takeaway', $secret], ['call', (string) Str::uuid(), (string) $staff->id, 'dine_in']]);
if ($race[0] === 'rejected') {
    $valid = $source->fresh()->status->value === 'called' && $race[1] === (string) $source->number && QueueTicket::count() === $before;
} else {
    $valid = $source->fresh()->status->value === 'cancelled' && $race[1] === 'empty' && QueueTicket::count() === $before + 1;
}
if (! $valid) { throw new RuntimeException('Transfer/call race was not serialized'); }
[$source, $secret] = $issue(QueueType::DineIn);
$before = QueueTicket::count();
$race = batch([['switch', (string) Str::uuid(), (string) $source->id, 'takeaway', $secret], ['switch', (string) Str::uuid(), (string) $source->id, 'takeaway', $secret]]);
if (count(array_filter($race, fn ($result) => $result === 'rejected')) !== 1 || QueueTicket::count() !== $before + 1) {
    throw new RuntimeException('Distinct transfer operations created multiple replacements for one ticket');
}
[$dine, $dineSecret] = $issue(QueueType::DineIn);
[$take, $takeSecret] = $issue(QueueType::Takeaway);
$before = QueueTicket::count();
$opposite = batch([['switch', (string) Str::uuid(), (string) $dine->id, 'takeaway', $dineSecret], ['switch', (string) Str::uuid(), (string) $take->id, 'dine_in', $takeSecret]]);
if (in_array('rejected', $opposite, true) || count(array_unique($opposite)) !== 2 || QueueTicket::count() !== $before + 2
    || $dine->fresh()->status->value !== 'cancelled' || $take->fresh()->status->value !== 'cancelled') {
    throw new RuntimeException('Opposite-direction transfers lost a ticket or number');
}
if (QueueTicket::count() !== QueueTicket::distinct()->count('queue_order') || QueueTicket::max('queue_order') !== QueueTicket::max('number')) {
    throw new RuntimeException('Queue ordering slots were duplicated or escaped the global number range');
}
echo "PASS MySQL: transfer retries; give-turn/call/registration race; transfer/cancellation and transfer/call races; distinct transfer operations; opposite-direction transfers; queue slot invariants.\n";

while ($queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn)) {
    // Clear the existing waiting tickets in this disposable queue.
}
$settings = QueueSetting::findOrFail(1);
$settings->update(['auto_call_dine_in' => true]);
[$cancelled] = $issue(QueueType::DineIn);
$queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn);
[$first] = $issue(QueueType::DineIn);
[$second] = $issue(QueueType::DineIn);
[$third] = $issue(QueueType::DineIn);
[$other] = $issue(QueueType::Takeaway);
$cancelOperation = (string) Str::uuid();
$manualOperation = (string) Str::uuid();
$cancelJob = ['cancel', $cancelOperation, (string) $cancelled->id];
$results = batch([$cancelJob, $cancelJob, ['call', $manualOperation, (string) $staff->id, 'dine_in']]);
$cancelEvent = QueueEvent::where('operation_id', $cancelOperation)->firstOrFail();
$manualEvent = QueueEvent::where('operation_id', $manualOperation)->firstOrFail();
$calledIds = [$cancelEvent->related_ticket_id, $manualEvent->queue_ticket_id];
sort($calledIds);
if ($results[0] !== (string) $cancelled->number || $results[0] !== $results[1]
    || $calledIds !== [$first->id, $second->id]
    || QueueEvent::where('action', 'auto_call')->count() !== 1
    || $first->fresh()->status->value !== 'called' || $second->fresh()->status->value !== 'called'
    || $third->fresh()->status->value !== 'waiting' || $other->fresh()->status->value !== 'waiting') {
    throw new RuntimeException('Concurrent cancellation retry/manual call did not produce exactly one automatic call in the proper service.');
}
echo "PASS MySQL: concurrent called-ticket cancellation retries and a separate staff call claim distinct tickets without touching the other service.\n";
