<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Slice 10 (T-10.6): CI gate that scans app/Events for non-@deprecated event
 * classes that have NEITHER a Laravel listener registered in
 * AppServiceProvider NOR a WebSocket consumer in the frontend (Reverb/Echo).
 *
 * Emits a non-zero exit code when an event is truly orphan so CI fails
 * before merge.
 *
 * Usage:
 *   php artisan sdd:check-events
 *   php artisan sdd:check-events --events-path=app/Events
 *   php artisan sdd:check-events --frontend-path=resources/js
 *
 * Per the user-approved decision for this change (bugfix-2026-08 slice 10):
 * "the broadcasts Reverb/WebSocket count as in-use" — so an event with a
 * frontend listener on its broadcastAs() event name counts as wired even
 * if no Laravel listener is registered.
 */
class SddCheckEvents extends Command
{
    protected $signature = 'sdd:check-events
        {--events-path=app/Events : Directory to scan for event classes}
        {--frontend-path=resources/js : Directory to scan for frontend consumers}
        {--provider-path=app/Providers : Directory to scan for Event::listen registrations}';

    protected $description = 'CI gate: flag events that are neither listened-to nor consumed by WebSocket clients';

    public function handle(): int
    {
        $eventsPath = base_path($this->option('events-path'));
        $frontendPath = base_path($this->option('frontend-path'));
        $providerPath = base_path($this->option('provider-path'));

        if (!is_dir($eventsPath)) {
            $this->error("Events path not found: {$eventsPath}");
            return self::FAILURE;
        }

        $eventClasses = $this->collectEventClasses($eventsPath);
        if (empty($eventClasses)) {
            $this->info('No event classes discovered.');
            return self::SUCCESS;
        }

        $listenedEvents = $this->collectListenedEvents($providerPath);
        $wsConsumers = $this->collectWebSocketConsumers($frontendPath);

        $orphans = [];
        foreach ($eventClasses as $shortName => $fqcn) {
            $source = $this->readSource($fqcn);
            if ($source === null) {
                continue;
            }

            // @deprecated events are explicitly allowed to be orphan.
            if (str_contains($source, '@deprecated')) {
                continue;
            }

            $hasListener = isset($listenedEvents[$shortName]);
            $hasWsConsumer = $this->hasWebSocketConsumer($shortName, $source, $wsConsumers);

            if (!$hasListener && !$hasWsConsumer) {
                $orphans[] = $shortName;
            }
        }

        if (empty($orphans)) {
            $this->info(sprintf(
                'sdd:check-events OK — %d event classes scanned, 0 orphan.',
                count($eventClasses)
            ));
            return self::SUCCESS;
        }

        $this->error(sprintf(
            'sdd:check-events FAIL — %d orphan event(s) with no listener and no WebSocket consumer:',
            count($orphans)
        ));
        foreach ($orphans as $shortName) {
            $this->line("  - {$shortName}");
        }

        return self::FAILURE;
    }

    /**
     * @return array<string,string> short-name => fully-qualified class name
     */
    private function collectEventClasses(string $path): array
    {
        $classes = [];
        $files = $this->phpFiles($path);
        $fs = new Filesystem();

        foreach ($files as $file) {
            $contents = $fs->get($file);
            if (!preg_match('/namespace\s+([^;]+);.*?class\s+(\w+)/s', $contents, $m)) {
                continue;
            }
            $fqn = trim($m[1]) . '\\' . $m[2];
            $classes[$m[2]] = $fqn;
        }

        return $classes;
    }

    /**
     * @return array<string,bool> short-name => true
     */
    private function collectListenedEvents(string $providerPath): array
    {
        $listened = [];
        $files = $this->phpFiles($providerPath);

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (!preg_match_all('/(\w+)\s*::class/', $contents, $matches)) {
                continue;
            }
            foreach ($matches[1] as $shortName) {
                $listened[$shortName] = true;
            }
        }

        return $listened;
    }

    /**
     * Grep frontend (resources/js) for the event's broadcastAs() name AND
     * for the class short-name as a subscription handle. The Reverb consumer
     * may use either the `.event.name` (from broadcastAs) or the channel
     * name from broadcastOn().
     *
     * @return array<string,string[]> short-name => list of consumer matchers
     */
    private function collectWebSocketConsumers(string $frontendPath): array
    {
        $consumers = [];
        if (!is_dir($frontendPath)) {
            return $consumers;
        }

        $contents = $this->collectTextFiles($frontendPath);

        foreach ($contents as $relPath => $source) {
            // Match `.event.name` style listeners (Laravel Echo dot notation).
            if (preg_match_all("/\.([a-z][a-z0-9\-_.]+[a-z0-9])/i", $source, $matches)) {
                foreach ($matches[1] as $eventName) {
                    $consumers[$eventName][] = $relPath;
                }
            }
        }

        return $consumers;
    }

    /**
     * Decide if the event is consumed in the frontend. The event's
     * broadcastAs() name is the conventional key (e.g. "appointment.checked_in").
     * Also accepts the older signature without `: string` return type.
     */
    private function hasWebSocketConsumer(string $shortName, string $source, array $wsConsumers): bool
    {
        // Two broadcastAs signatures coexist in this codebase: one with
        // `: string` return type (PHP 7.4+) and one without. Match both.
        $patterns = [
            '/function\s+broadcastAs\s*\(\s*\)\s*:\s*string\s*\{[^}]*return\s+\'([^\']+)\'/s',
            '/function\s+broadcastAs\s*\(\s*\)\s*\{[^}]*return\s+\'([^\']+)\'/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source, $m)) {
                $eventName = $m[1];
                if (isset($wsConsumers[$eventName])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function phpFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $files = [];
        foreach ($rii as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $files[] = $f->getPathname();
            }
        }
        return $files;
    }

    /**
     * @return array<string,string> relative-path => contents
     */
    private function collectTextFiles(string $path): array
    {
        $out = [];
        if (!is_dir($path)) {
            return $out;
        }
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($rii as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $ext = strtolower($f->getExtension());
            if (!in_array($ext, ['js', 'ts', 'vue', 'mjs'], true)) {
                continue;
            }
            $rel = ltrim(str_replace(base_path(), '', $f->getPathname()), '/\\');
            try {
                $out[$rel] = file_get_contents($f->getPathname());
            } catch (Throwable $e) {
                continue;
            }
        }
        return $out;
    }

    private function readSource(string $fqcn): ?string
    {
        try {
            $r = new \ReflectionClass($fqcn);
            return file_get_contents($r->getFileName());
        } catch (Throwable $e) {
            return null;
        }
    }
}
