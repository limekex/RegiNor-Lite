<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Domain\LocalDateTime;
use RegiNor\Lite\Domain\Scheduling\{StoredSession, ScheduledTime};
use function RegiNor\Lite\translate as __;

trait CourseSourceAutomation
{
    /** Settings and their baseline share the same versioned course transaction. */
    public function saveAutomationSettings(int $id, int $version, array $config): void
    {
        LetsRegMapping::authorize();
        Mutation::run(function () use ($id, $version, $config): void {
            $state = $this->get($id, 'group'); $this->assertVersion($state, $version);
            $this->get($state['data']['period_id'], 'period');
            $this->write($id, $state, $state['data'], 'saved', true);
            CourseAutomation::put($id, CourseAutomation::META, $config);
        }, true);
    }

    /** Only explicit time/price authority; description baselines remain untouched. */
    public function applySourceAutomation(int $id, int $version, string $hash): array
    {
        LetsRegMapping::authorize();
        return Mutation::run(function () use ($id, $version, $hash): array {
            $state = $this->get($id, 'group'); $this->assertVersion($state, $version);
            $data = $state['data']; $config = CourseAutomation::settings($id);
            $this->get($data['period_id'], 'period');
            if (!$data['letsreg_mapping'] || $config['key'] !== LetsRegChanges::key($data['letsreg_mapping']) || $config['actor'] !== get_current_user_id()) { throw new \RuntimeException(__('Lagre automatikkvalgene på nytt for gjeldende kurskobling.', 'reginor-lite'), 409); }
            $source = LetsRegChanges::assertReview($id, $data, $hash);
            $accepted = LetsRegChanges::inspect($id, $data)['previous'];
            if ($accepted === null) { return []; }
            $messages = []; $changedSessions = [];
            if ($config['time'] && (($source['startDate'] ?? null) !== ($config['seen']['startDate'] ?? null) || ($source['endDate'] ?? null) !== ($config['seen']['endDate'] ?? null))) {
                foreach (['start_time', 'end_time', 'timezone'] as $key) {
                    if ($data[$key] !== ($config['local'][$key] ?? null)) { throw new \RuntimeException(__('Klokkeslett er endret lokalt. Kontroller tidene og lagre automatikkvalgene på nytt.', 'reginor-lite'), 409); }
                }
                $oldStart = LetsRegCourseSuggestions::local($config['seen']['startDate'] ?? null, $data['timezone']);
                $oldEnd = LetsRegCourseSuggestions::local($config['seen']['endDate'] ?? null, $data['timezone']);
                $start = LetsRegCourseSuggestions::local($source['startDate'] ?? null, $data['timezone']);
                $end = LetsRegCourseSuggestions::local($source['endDate'] ?? null, $data['timezone']);
                if (!$oldStart || !$oldEnd || !$start || !$end || $start->format('Y-m-d') !== $oldStart->format('Y-m-d') || $end->format('Y-m-d') !== $oldEnd->format('Y-m-d') || $end->format('H:i') <= $start->format('H:i')) {
                    throw new \RuntimeException(__('Datoendring eller uklare klokkeslett må gjennomgås manuelt. Lokale kursopplysninger er beholdt.', 'reginor-lite'), 409);
                }
                if ($oldStart->format('H:i') !== $data['start_time'] || $oldEnd->format('H:i') !== $data['end_time']) { throw new \RuntimeException(__('Tidene hos LetsReg og det lokale grunnlaget er forskjellige. Kontroller dem manuelt.', 'reginor-lite'), 409); }
                foreach ($data['sessions'] as &$session) {
                    if ($session['status'] !== 'scheduled' || new \DateTimeImmutable($session['starts_at']) <= $this->clock->now() || $session['start_time'] !== $data['start_time'] || $session['end_time'] !== $data['end_time']) { continue; }
                    $time = new ScheduledTime(LocalDateTime::at($session['date'], $start->format('H:i'), $session['timezone']), LocalDateTime::at($session['date'], $end->format('H:i'), $session['timezone']));
                    if ($time->startsAt <= $this->clock->now()) { throw new \RuntimeException(__('Nytt klokkeslett ville flyttet en kurskveld tilbake i tid. Kontroller endringen manuelt.', 'reginor-lite'), 409); }
                    $session = array_replace($session, array_intersect_key(StoredSession::create($session['id'], $time, $session['room_id'], $session['instructor_ids']), array_flip(['start_time','end_time','starts_at','ends_at'])));
                    $changedSessions[] = $id . '/' . $session['id'];
                }
                unset($session);
                $messages[] = sprintf(__('Klokkeslett: %1$s–%2$s → %3$s–%4$s. Tidligere og særskilt endrede kurskvelder er beholdt.', 'reginor-lite'), $data['start_time'], $data['end_time'], $start->format('H:i'), $end->format('H:i'));
                $data['start_time'] = $start->format('H:i'); $data['end_time'] = $end->format('H:i');
                foreach (['start_time', 'end_time', 'timezone'] as $key) { $config['local'][$key] = $data[$key]; }
                foreach (['startDate', 'endDate'] as $key) { $accepted[$key] = $config['seen'][$key] = $source[$key]; }
            }
            if ($config['price']) {
                $prices = array_column($source['prices'] ?? [], null, 'id'); $oldPrices = array_column($config['seen']['prices'] ?? [], null, 'id');
                $price = $prices[$config['category']] ?? null; $oldPrice = $oldPrices[$config['category']] ?? null;
                if ($price !== $oldPrice) {
                    if ($data['registration_status'] === 'dropin') { throw new \RuntimeException(__('Dette kurset har kun drop-in. Drop-in-prisen må endres manuelt.', 'reginor-lite'), 409); }
                    $categories = array_column($data['letsreg_mapping']['categories'], null, 'id'); $category = $categories[$config['category']] ?? null;
                    if (!$category || !$price || empty($price['active']) || !isset($price['price_minor']) || !is_int($price['price_minor']) || $price['price_minor'] < 0 || $data['currency'] !== 'NOK') { throw new \RuntimeException(__('Valgt priskategori mangler en aktiv og entydig pris. Kontroller prisen manuelt.', 'reginor-lite'), 409); }
                    foreach (['price_minor', 'price_basis', 'currency'] as $key) { if ($data[$key] !== ($config['local'][$key] ?? null)) { throw new \RuntimeException(__('Prisen er endret lokalt. Kontroller den og lagre automatikkvalgene på nytt.', 'reginor-lite'), 409); } }
                    if ($data['price_basis'] === 'pair' && $category['registration'] !== 'pair') { throw new \RuntimeException(__('Pris per par krever en kategori for parpåmelding.', 'reginor-lite'), 409); }
                    $newPrice = $price['price_minor'] * ($data['price_basis'] === 'pair' ? 2 : 1);
                    if ($data['price_minor'] !== $newPrice) { $messages[] = sprintf(__('Pris: %1$s → %2$s NOK. Prisgrunnlaget er beholdt.', 'reginor-lite'), number_format_i18n($data['price_minor'] / 100, 2), number_format_i18n($newPrice / 100, 2)); $data['price_minor'] = $newPrice; }
                    // Accept the price amount only, never names, activity or other category changes.
                    foreach ($accepted['prices'] as &$row) { if ($row['id'] === $config['category']) { $row['price_minor'] = $price['price_minor']; } } unset($row);
                    $config['seen']['prices'] = $source['prices'];
                    foreach (['price_minor', 'price_basis', 'currency'] as $key) { $config['local'][$key] = $data[$key]; }
                }
            }
            $data = $this->validate('group', $data);
            if ($changedSessions) {
                foreach ($this->conflicts($id, $data) as $conflict) {
                    if (in_array($conflict['first'], $changedSessions, true) || in_array($conflict['second'], $changedSessions, true)) { throw new \RuntimeException(__('Nytt klokkeslett gir en kollisjon for sal eller instruktør. Endringen må gjennomgås manuelt.', 'reginor-lite'), 409); }
                }
            }
            if ($data !== $state['data']) { $this->write($id, $state, $data, 'saved', true); CourseCalendar::published(new \RegiNor\Lite\Frontend\Catalog($this->clock), $data['period_id']); }
            $config['status'] = '';
            if ($config !== CourseAutomation::settings($id)) { $config['at'] = time(); CourseAutomation::put($id, CourseAutomation::META, $config); }
            if ($accepted !== LetsRegChanges::inspect($id, $data)['previous']) { LetsRegChanges::baseline($id, $data['letsreg_mapping'], $accepted); }
            if ($messages && $config['email']) { CourseAutomation::queueCurrent($id, $data, $messages); }
            return $messages;
        }, true);
    }
}
