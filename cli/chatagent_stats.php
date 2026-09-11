<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Report what the chat agent has been doing, from the recorded measurements.
 *
 * Written to answer two questions that would otherwise be settled by impression: whether the
 * agent is behaving well enough to build on, and what a lesson costs in calls and bytes - the
 * figure that decides whether brokering the model call through a shared server is affordable.
 *
 * Usage:
 *   php cli/chatagent_stats.php              # everything recorded
 *   php cli/chatagent_stats.php --days=7     # the last week only
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_minilesson\constants;

[$options, $unrecognised] = cli_get_params(['help' => false, 'days' => 0], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help']) {
    cli_writeln(file_get_contents(__FILE__, false, null, 0, 1500));
    exit(0);
}

$since = $options['days'] ? time() - ((int) $options['days'] * DAYSECS) : 0;
$where = 'timecreated >= ?';
$params = [$since];

$calls = $DB->get_records_select(constants::M_CHATAGENTMETRIC_TABLE, $where, $params, 'timecreated ASC');
if (!$calls) {
    cli_writeln('No chat agent activity recorded' . ($options['days'] ? ' in the last ' . $options['days'] . ' days' : '') . '.');
    exit(0);
}

/**
 * The value below which the given proportion of a sorted list falls.
 *
 * Reported alongside the median because a median turn and a worst turn tell different stories,
 * and it is the worst one that has to be sized for.
 *
 * @param array $values
 * @param float $fraction
 * @return int
 */
function chatagent_percentile(array $values, float $fraction): int {
    if (!$values) {
        return 0;
    }
    sort($values);
    $index = (int) floor($fraction * (count($values) - 1));
    return (int) $values[$index];
}

$conversations = [];
$turnkeys = [];
$bytes = [];
$attachments = [];
$replays = 0;
$rejections = [];
$errors = [];
$inputtokens = 0;
$outputtokens = 0;

foreach ($calls as $call) {
    $conversations[$call->conversationid] = true;
    $turnkeys[$call->conversationid . ':' . $call->turn][] = $call->toolname ? 1 : 0;
    $bytes[] = $call->requestbytes;
    if ($call->attachmentbytes) {
        $attachments[] = $call->attachmentbytes;
    }
    $replays += $call->replayed ? 1 : 0;
    if ($call->toolrejected) {
        $rejections[$call->rejectreason ?: 'unknown'] = ($rejections[$call->rejectreason ?: 'unknown'] ?? 0) + 1;
    }
    if ($call->errorcode) {
        $errors[$call->errorcode] = ($errors[$call->errorcode] ?? 0) + 1;
    }
    $inputtokens += $call->inputtokens;
    $outputtokens += $call->outputtokens;
}

$toolsperturn = array_map('array_sum', $turnkeys);
$turnsper = [];
foreach (array_keys($turnkeys) as $key) {
    [$conversationid] = explode(':', $key);
    $turnsper[$conversationid] = ($turnsper[$conversationid] ?? 0) + 1;
}

cli_writeln('Chat agent activity' . ($options['days'] ? ' over the last ' . $options['days'] . ' days' : '') . "\n");
cli_writeln('  conversations            ' . count($conversations));
cli_writeln('  turns                    ' . count($turnkeys));
cli_writeln('  model calls              ' . count($calls));
cli_writeln('  tokens in / out          ' . $inputtokens . ' / ' . $outputtokens);
cli_writeln('');
cli_writeln('  turns per conversation   median ' . chatagent_percentile(array_values($turnsper), 0.5)
    . ', p95 ' . chatagent_percentile(array_values($turnsper), 0.95));
cli_writeln('  tool calls per turn      median ' . chatagent_percentile(array_values($toolsperturn), 0.5)
    . ', p95 ' . chatagent_percentile(array_values($toolsperturn), 0.95)
    . ', max ' . ($toolsperturn ? max($toolsperturn) : 0));
cli_writeln('  request bytes per call   median ' . chatagent_percentile($bytes, 0.5)
    . ', p95 ' . chatagent_percentile($bytes, 0.95)
    . ', max ' . ($bytes ? max($bytes) : 0));
if ($attachments) {
    cli_writeln('  attachment bytes         median ' . chatagent_percentile($attachments, 0.5)
        . ', max ' . max($attachments) . ' (' . count($attachments) . ' calls carried one)');
}
cli_writeln('  replayed transcripts     ' . $replays . ' of ' . count($calls) . ' calls ('
    . round(100 * $replays / count($calls), 1) . '%)');
cli_writeln('');

// The readiness criteria: these are the ones that say whether to build on this yet.
cli_writeln('  rejected tool calls      ' . (array_sum($rejections) ?: 'none'));
foreach ($rejections as $reason => $count) {
    cli_writeln('      ' . str_pad($reason, 20) . $count);
}
cli_writeln('  provider errors          ' . (array_sum($errors) ?: 'none'));
foreach ($errors as $code => $count) {
    cli_writeln('      ' . str_pad($code, 20) . $count);
}

$cap = (int) get_config(constants::M_COMPONENT, 'chatagentmaxtoolcalls');
$cap = $cap > 0 ? $cap : 8;
$athecap = count(array_filter($toolsperturn, fn($n) => $n >= $cap));
cli_writeln('  turns that hit the cap   ' . $athecap . ' of ' . count($turnkeys) . ' (cap is ' . $cap . ')');
