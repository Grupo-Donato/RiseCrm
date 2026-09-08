<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Services/Campaign_schedule.php';
use Chatwoot_plugin\Services\Campaign_schedule as Schedule;
$checks = 0;
function check($expected, $actual): void { global $checks; if ($expected !== $actual) throw new RuntimeException(var_export([$expected, $actual], true)); $checks++; }
check('2026-09-08T09:30:15-03:00', Schedule::date('2026-09-08 09:30:15', 'America/Sao_Paulo'));
$s = ['at' => '2026-09-08T09:30:15-03:00', 'timezone' => 'America/Sao_Paulo', 'days_of_week' => [1,3,5], 'ends_at' => '2026-09-12T18:00:00-03:00'];
check('2026-09-09T09:30:15-03:00', Schedule::occurrence($s, strtotime($s['at'])));
check('2026-09-11T09:30:15-03:00', Schedule::occurrence($s, strtotime('2026-09-10T18:00:00-03:00')));
check(null, Schedule::occurrence($s, strtotime('2026-09-12T18:00:00-03:00')));
check(true, Schedule::expired($s, strtotime($s['ends_at'])));
check(false, Schedule::expired($s, strtotime($s['ends_at']) - 1));
check(15, Schedule::interval('15'));
check(0, Schedule::interval(0));
foreach ([-1, 3601, 2.5, 'x'] as $invalid) { try { Schedule::interval($invalid); throw new RuntimeException('Accepted invalid interval'); } catch (InvalidArgumentException $e) { $checks++; } }
foreach (['2026-02-30 10:00', 'invalid'] as $invalid) { try { Schedule::date($invalid, 'America/Sao_Paulo'); throw new RuntimeException('Accepted invalid date'); } catch (InvalidArgumentException $e) { $checks++; } }
echo "$checks campaign schedule checks passed.\n";
