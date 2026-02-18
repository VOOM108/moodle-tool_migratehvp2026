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
 * CLI command to migrate mod_hvp to mod_h5pactivity.
 *
 * @package    tool_migratehvp2026
 * @copyright  2020 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_migratehvp2026\api;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once("{$CFG->libdir}/clilib.php");

list($options, $unrecognized) = cli_get_params(
    [
        'execute' => false,
        'help' => false,
        'limit' => 100,
        'keeporiginal' => 1,
        'copy2cb' => api::COPY2CBYESWITHLINK,
        'contenttypes' => [],
        'suffix' => '',
        'preserveavailability' => 1,
        'courseid' => 0,
    ], [
        'e' => 'execute',
        'h' => 'help',
        'l' => 'limit',
        'k' => 'keeporiginal',
        'c' => 'copy2cb',
        't' => 'contenttypes',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOT
Migration command from mod_hvp to mod_h5pactivity.

Options:
 -h, --help                Print out this help
 -e, --execute             Run the migration tool
 -k, --keeporiginal=N      After migration 0 will remove the original activity, 1 will keep it and 2 will hide it
 -c, --copy2cb=N           Whether H5P files should be added to the content bank with a link (1), as a copy (2) or not added (0)
 -t, --contenttypes=N      The library ids, separated by commas, for the mod_hvp contents to migrate.
                           Only contents having these libraries defined as main library will be migrated.
     --courseid=N          Restrict migration to activities in a single course id.
 -l  --limit=N             The maximmum number of activities per execution (default 100).
                           Already migrated activities will be ignored.
     --preserveavailability=N
                           Keep visibility and availability from mod_hvp in mod_h5pactivity (1 yes, 0 no).
     --suffix="TEXT"       Optional suffix added to original mod_hvp names when originals are kept or hidden.

Example:
\$sudo -u www-data /usr/bin/php admin/tool/migratehvp2026/cli/migrate.php --execute

\$sudo -u www-data /usr/bin/php admin/tool/migratehvp2026/cli/migrate.php --execute --courseid=42 --keeporiginal=2 --suffix="(old hidden copy)" --preserveavailability=1

EOT;

    echo $help;
    die;
}

if (CLI_MAINTENANCE) {
    echo "CLI maintenance mode active, cron execution suspended.\n";
    exit(1);
}

if (moodle_needs_upgrading()) {
    echo "Moodle upgrade pending, cron execution suspended.\n";
    exit(1);
}

if (!isset($options['keeporiginal'])) {
    $options['keeporiginal'] = 1;
}

if (!isset($options['copy2cb'])) {
    $options['copy2cb'] = api::COPY2CBYESWITHLINK;
}

if (!isset($options['preserveavailability'])) {
    $options['preserveavailability'] = 1;
}

if (!empty($options['contenttypes'])) {
    $ctparam = explode(',', $options['contenttypes']);
} else {
    $ctparam = [];
}

$keeporiginal = $options['keeporiginal'];
$copy2cb = $options['copy2cb'];
$suffix = trim((string)($options['suffix'] ?? ''));
$preserveavailability = $options['preserveavailability'];
$courseid = $options['courseid'] ?? 0;
$limit = $options['limit'] ?? 100;
$execute = (empty($options['execute'])) ? false : true;

if (!is_numeric($limit)) {
    echo "Limit must be an integer.\n";
    exit(1);
}
$limit = intval($limit);

if (!is_numeric($keeporiginal)) {
    echo "keeporiginal must be an integer.\n";
    exit(1);
}
$keeporiginal = intval($keeporiginal);
if (!in_array($keeporiginal, [api::DELETEORIGINAL, api::KEEPORIGINAL, api::HIDEORIGINAL], true)) {
    echo "keeporiginal must be 0 (delete), 1 (keep) or 2 (hide).\n";
    exit(1);
}

if (!is_numeric($copy2cb)) {
    echo "copy2cb must be an integer.\n";
    exit(1);
}
$copy2cb = intval($copy2cb);
if (!in_array($copy2cb, [api::COPY2CBNO, api::COPY2CBYESWITHLINK, api::COPY2CBYESWITHOUTLINK], true)) {
    echo "copy2cb must be 0 (no content bank), 1 (link) or 2 (copy).\n";
    exit(1);
}

if (!is_numeric($preserveavailability)) {
    echo "preserveavailability must be an integer (0 or 1).\n";
    exit(1);
}
$preserveavailability = intval($preserveavailability);
if (!in_array($preserveavailability, [0, 1], true)) {
    echo "preserveavailability must be 0 or 1.\n";
    exit(1);
}

if (!is_numeric($courseid)) {
    echo "courseid must be an integer.\n";
    exit(1);
}
$courseid = intval($courseid);
if ($courseid < 0) {
    echo "courseid must be 0 or a valid course id.\n";
    exit(1);
}

if ($keeporiginal === api::DELETEORIGINAL) {
    $suffix = '';
}

$contenttypes = [];
if (!empty($ctparam)) {
    foreach ($ctparam as $contenttype) {
        if (!is_numeric($contenttype)) {
            echo "contenttypes must be a list of library ids separated by commas.\n";
            exit(1);
        } else {
            $contenttypes[] = intval($contenttype);
        }
    }
}

core_php_time_limit::raise();

// Increase memory limit.
raise_memory_limit(MEMORY_EXTRA);

// Emulate normal session - we use admin account by default.
\core\cron::setup_user();

$humantimenow = date('r', time());

mtrace("Server Time: {$humantimenow}\n");

mtrace("Search for $limit non migrated hvp activites" . ($courseid > 0 ? " in course {$courseid}" : '') . "\n");

list($sql, $params) = api::get_sql_hvp_to_migrate(false, null, $contenttypes, $courseid);
$activities = $DB->get_records_sql($sql, $params, 0, $limit);

if (empty($activities)) {
    mtrace(" * No activites are found.\n");
    exit(1);
}

foreach ($activities as $hvpid => $info) {
    mtrace("Migrating ID:$hvpid\t{$info->name}\t course:{$info->courseid}\t{$info->course}");
    if (empty($execute)) {
        mtrace("\t ...Skipping\n");
        continue;
    }
    try {
        $messages = tool_migratehvp2026\api::migrate_hvp2h5p(
            $hvpid,
            $keeporiginal,
            $copy2cb,
            $suffix,
            $preserveavailability
        );
        if (empty($messages)) {
            mtrace("\t ...Successful\n");
        } else {
            foreach ($messages as $message) {
                mtrace("\t ...$message[0]\n");
            }
        }
    } catch (moodle_exception $e) {
        mtrace("\tException: ".$e->getMessage()."\n");
        mtrace("\t ...Failed!\n");
    }
}
