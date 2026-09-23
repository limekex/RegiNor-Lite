<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use DateTimeImmutable;
use InvalidArgumentException;

final class ScheduleReplanner
{
    /** The request describes the entire series, including already started sessions.
     * @param list<array> $existing @param callable():string $newId
     * @return array{sessions: array, changes: array, issues: array}
     */
    public function preview(array $existing, ScheduleRequest $request, DateTimeImmutable $now, int $roomId, array $instructors, callable $newId): array
    {
        $byDate = [];
        $oldById = [];
        $next = [];
        $issues = [];
        $blockedDates = [];
        foreach ($existing as $session) {
            $time = StoredSession::time($session);
            if (isset($byDate[$session['original_date']]) || isset($oldById[$session['id']])) {
                throw new InvalidArgumentException(__('Øktene må ha unike ID-er og opprinnelige datoer.', 'reginor-lite'));
            }
            $byDate[$session['original_date']] = $session;
            $oldById[$session['id']] = $session;
            if ($session['status'] !== 'scheduled' || $time->startsAt <= $now) {
                $next[$session['id']] = $session;
                foreach (array_merge($request->periodBreaks, $request->groupBreaks) as $break) {
                    if ($session['status'] === 'moved' && $time->startsAt > $now && $break->includes($session['date'])) {
                        $issues[] = __('Et opphold treffer en manuelt flyttet kurskveld den ', 'reginor-lite') . $session['date'] . __('. Flytt kurskvelden eller juster oppholdet.', 'reginor-lite');
                    }
                }
            }
        }
        foreach ((new ScheduleGenerator())->preview($request) as $time) {
            $date = $time->startsAt->format('Y-m-d');
            $old = $byDate[$date] ?? null;
            if ($old !== null && isset($next[$old['id']])) {
                continue;
            }
            if ($time->startsAt <= $now) {
                $blockedDates[] = $date;
                continue;
            }
            $id = $old['id'] ?? $newId();
            if ($id === '' || ($old === null && (isset($next[$id]) || isset($oldById[$id])))) {
                throw new InvalidArgumentException(__('Ny økt-ID må være unik.', 'reginor-lite'));
            }
            $next[$id] = StoredSession::create($id, $time, $roomId, $instructors);
        }
        // Cancelled sessions retain identity; replacement is always an explicit later operation.
        $active = count(array_filter($next, static fn (array $s): bool => $s['status'] !== 'cancelled'));
        if ($blockedDates) {
            $issues[] = sprintf(
                /* translators: 1: dates that cannot be added, 2: current local date and time. */
                __('Disse kursdatoene kan ikke legges til ved endring av et eksisterende kurs: %1$s. Starttidspunktene har allerede passert per %2$s. Tidligere lagrede kurskvelder beholdes. Behold kursets opprinnelige oppstart, og gjør tidsplanendringer for kommende kvelder.', 'reginor-lite'),
                implode(', ', $blockedDates), $now->setTimezone(new \DateTimeZone($request->timezone))->format('d.m.Y H:i')
            );
        } elseif ($active !== $request->sessionCount) {
            $issues[] = sprintf(
                /* translators: 1: requested total sessions, 2: actual non-cancelled sessions after preserving history. */
                __('Du har valgt %1$d undervisningskvelder totalt, men planen inneholder %2$d kvelder som ikke er avlyst. Tidligere og manuelt flyttede kvelder beholdes, mens avlyste kvelder ikke teller med. Kontroller datolisten og sett antallet til ønsket total, eller planlegg en erstatningskveld.', 'reginor-lite'),
                $request->sessionCount, $active
            );
        }
        uasort($next, static fn (array $a, array $b): int => strcmp($a['starts_at'], $b['starts_at']) ?: strcmp($a['id'], $b['id']));
        $changes = [];
        foreach ($oldById as $id => $before) {
            $after = $next[$id] ?? null;
            if ($after !== $before) {
                $changes[] = ['id' => $id, 'before' => $before, 'after' => $after];
            }
        }
        foreach ($next as $id => $after) {
            if (!isset($oldById[$id])) {
                $changes[] = ['id' => $id, 'before' => null, 'after' => $after];
            }
        }
        return ['sessions' => array_values($next), 'changes' => $changes, 'issues' => array_values(array_unique($issues))];
    }
}
