<?php

namespace App\Console\Commands;

use App\Models\Events;
use Illuminate\Console\Command;

class AssignEventMemberSlots extends Command
{
    protected $signature = 'roster:assign-slots
                            {--dry-run : Show what would be assigned without making changes}
                            {--event= : Only process a specific event ID}';

    protected $description = 'Assign slot_id to existing event members based on their band_role_id matching roster slots';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $eventId = $this->option('event');

        if ($dryRun) {
            $this->info('[DRY RUN] No changes will be saved.');
        }

        $query = Events::whereNotNull('roster_id')
            ->whereHas('eventMembers', fn($q) => $q->whereNull('slot_id'))
            ->with([
                'eventMembers' => fn($q) => $q->whereNull('slot_id')->with('rosterMember'),
                'roster.slots',
            ]);

        if ($eventId) {
            $query->where('id', $eventId);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('No events found with unassigned members.');
            return self::SUCCESS;
        }

        $totalAssigned = 0;
        $totalSkipped = 0;

        foreach ($events as $event) {
            $slots = $event->roster->slots;

            if ($slots->isEmpty()) {
                $this->line("  Event #{$event->id} \"{$event->title}\": roster has no slots, skipping.");
                continue;
            }

            $this->line("Event #{$event->id} \"{$event->title}\" ({$event->eventMembers->count()} unassigned members)");

            foreach ($event->eventMembers as $member) {
                // Primary: use the slot already set on the linked roster member
                $slotId = $member->rosterMember?->slot_id;
                $slotName = $slots->firstWhere('id', $slotId)?->name;

                if ($slotId && $slotName) {
                    $this->line("    ASSIGN {$member->display_name} → {$slotName} (from roster)");

                    if (!$dryRun) {
                        $member->update(['slot_id' => $slotId]);
                    }

                    $totalAssigned++;
                    continue;
                }

                // No roster member link — skip, can't reliably guess
                $this->line("    SKIP  {$member->display_name} — no roster member link");
                $totalSkipped++;
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would assign' : 'Assigned';
        $this->info("{$verb} {$totalAssigned} member(s). Skipped {$totalSkipped} (no role or no matching slot).");

        return self::SUCCESS;
    }
}
