<?php

namespace App\Console\Commands\Realtime;

use App\Services\Realtime\OnlinePresence;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('realtime:presence:prune')]
#[Description('Mark stale realtime presence sessions offline and prune expired realtime audit data')]
class PrunePresenceStateCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OnlinePresence $onlinePresence): int
    {
        $result = $onlinePresence->cleanup();

        $this->components->info(sprintf(
            'Presence cleanup completed. Timed out: %d, pruned sessions: %d, pruned events: %d.',
            $result['timed_out'],
            $result['pruned_sessions'],
            $result['pruned_events'],
        ));

        return self::SUCCESS;
    }
}
