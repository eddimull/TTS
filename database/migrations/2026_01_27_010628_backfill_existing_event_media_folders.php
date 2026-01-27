<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill media_folder_path for existing events
        $events = DB::table('events')
            ->whereNull('media_folder_path')
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $usedPaths = [];

        foreach ($events as $event) {
            // Parse the date
            $date = new \DateTime($event->date);
            $year = $date->format('Y');
            $month = $date->format('m');

            // Sanitize event title for folder name
            $eventName = !empty($event->title)
                ? Str::limit($event->title, 50, '')
                : "Event-{$event->id}";

            $slug = Str::slug($eventName);

            // Handle duplicate slugs in same month
            $basePath = "{$year}/{$month}";
            $fullPath = "{$basePath}/{$slug}";
            $counter = 1;

            while (in_array($fullPath, $usedPaths)) {
                $fullPath = "{$basePath}/{$slug}-{$counter}";
                $counter++;
            }

            $usedPaths[] = $fullPath;

            // Update the event
            DB::table('events')
                ->where('id', $event->id)
                ->update(['media_folder_path' => $fullPath]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse data population
    }
};
