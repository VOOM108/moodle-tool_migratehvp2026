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
 * CLI command to rewrite links from migrated mod_hvp URLs to mod_h5pactivity URLs.
 *
 * @package    tool_migratehvp2026
 * @copyright  2026 Andreas Giesen <andreas@108design.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_migratehvp2026\api;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once("{$CFG->libdir}/clilib.php");

/**
 * Process a text column and optionally update records.
 *
 * @param moodle_database $DB
 * @param string $tablename
 * @param string $textfield
 * @param array $replacements
 * @param bool $execute
 * @param int $courseid
 * @return array [records, replacements, updated]
 */
function tool_migratehvp2026_process_textarea(
    moodle_database $DB,
    string $tablename,
    string $textfield,
    array $replacements,
    bool $execute,
    int $courseid = 0
): array {
    $fields = "id, course, $textfield, timemodified";
    $where = '';
    $params = [];

    if ($courseid > 0) {
        $where = 'course = :courseid';
        $params['courseid'] = $courseid;
    }

    $records = $DB->get_recordset_select($tablename, $where, $params, 'id ASC', $fields);

    $changedrecords = 0;
    $totalreplacements = 0;
    $updatedrecords = 0;
    $now = time();

    foreach ($records as $record) {
        $original = (string)$record->{$textfield};
        if ($original === '') {
            continue;
        }

        $updated = strtr($original, $replacements);
        if ($updated === $original) {
            continue;
        }

        $changedrecords++;

        foreach ($replacements as $old => $new) {
            if ($old !== '' && strpos($original, $old) !== false) {
                $totalreplacements += substr_count($original, $old);
            }
        }

        if ($execute) {
            $updaterecord = (object) [
                'id' => $record->id,
                $textfield => $updated,
                'timemodified' => $now,
            ];
            $DB->update_record($tablename, $updaterecord);
            $updatedrecords++;
        }
    }
    $records->close();

    return [
        'records' => $changedrecords,
        'replacements' => $totalreplacements,
        'updated' => $updatedrecords,
    ];
}

list($options, $unrecognized) = cli_get_params(
    [
        'execute' => false,
        'help' => false,
        'courseid' => 0,
        'include' => 'pages,labels,sections',
        'exportcsv' => '',
    ], [
        'e' => 'execute',
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOT
Rewrite migrated links from mod_hvp to mod_h5pactivity in text areas.

Options:
 -h, --help                    Print out this help
 -e, --execute                 Apply changes (default is dry-run)
     --courseid=N              Restrict to one course id (default 0 = all)
     --include=LIST            Comma-separated targets: pages,labels,sections
                               Default: pages,labels,sections
     --exportcsv="PATH"        Optional CSV file path to export the link map

Examples:
\$sudo -u www-data /usr/bin/php admin/tool/migratehvp2026/cli/replace_links.php
\$sudo -u www-data /usr/bin/php admin/tool/migratehvp2026/cli/replace_links.php --execute --courseid=42
\$sudo -u www-data /usr/bin/php admin/tool/migratehvp2026/cli/replace_links.php --exportcsv="/tmp/hvp_h5p_map.csv"

EOT;

    echo $help;
    die;
}

if (CLI_MAINTENANCE) {
    echo "CLI maintenance mode active, execution suspended.\n";
    exit(1);
}

if (moodle_needs_upgrading()) {
    echo "Moodle upgrade pending, execution suspended.\n";
    exit(1);
}

$courseid = $options['courseid'] ?? 0;
if (!is_numeric($courseid)) {
    echo "courseid must be an integer.\n";
    exit(1);
}
$courseid = (int)$courseid;
if ($courseid < 0) {
    echo "courseid must be 0 or a valid course id.\n";
    exit(1);
}

$include = trim((string)($options['include'] ?? ''));
$allowedtargets = ['pages', 'labels', 'sections'];
$targets = array_filter(array_map('trim', explode(',', $include)), static function($item): bool {
    return $item !== '';
});

if (empty($targets)) {
    $targets = $allowedtargets;
}

foreach ($targets as $target) {
    if (!in_array($target, $allowedtargets, true)) {
        echo "Invalid include target '$target'. Allowed values: pages,labels,sections.\n";
        exit(1);
    }
}

$execute = !empty($options['execute']);
$exportcsv = trim((string)($options['exportcsv'] ?? ''));

core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);
\core\cron::setup_user();

$maprows = api::get_migrated_link_map($courseid);
if (empty($maprows)) {
    mtrace('No completed migrated activity map rows found for the selected scope.');
    exit(0);
}

mtrace('Loaded ' . count($maprows) . ' migrated activity mapping rows.');

$replacements = [];
foreach ($maprows as $row) {
    $replacements[$row->oldurl] = $row->newurl;
    $replacements[$row->oldrelativeurl] = $row->newrelativeurl;
}

if ($exportcsv !== '') {
    $handle = @fopen($exportcsv, 'wb');
    if ($handle === false) {
        mtrace("Cannot open CSV path for writing: {$exportcsv}");
        exit(1);
    }

    fputcsv($handle, [
        'courseid',
        'hvpid',
        'h5pid',
        'oldcmid',
        'newcmid',
        'oldrelativeurl',
        'newrelativeurl',
        'oldurl',
        'newurl',
    ]);

    foreach ($maprows as $row) {
        fputcsv($handle, [
            $row->courseid,
            $row->hvpid,
            $row->h5pid,
            $row->oldcmid,
            $row->newcmid,
            $row->oldrelativeurl,
            $row->newrelativeurl,
            $row->oldurl,
            $row->newurl,
        ]);
    }

    fclose($handle);
    mtrace("CSV link map exported to: {$exportcsv}");
}

$targetsmeta = [
    'pages' => ['table' => 'page', 'field' => 'content'],
    'labels' => ['table' => 'label', 'field' => 'intro'],
    'sections' => ['table' => 'course_sections', 'field' => 'summary'],
];

$totals = ['records' => 0, 'replacements' => 0, 'updated' => 0];

foreach ($targets as $target) {
    $meta = $targetsmeta[$target];

    $result = tool_migratehvp2026_process_textarea(
        $DB,
        $meta['table'],
        $meta['field'],
        $replacements,
        $execute,
        $courseid
    );

    $totals['records'] += $result['records'];
    $totals['replacements'] += $result['replacements'];
    $totals['updated'] += $result['updated'];

    mtrace(sprintf(
        'Target %-8s | changed records: %d | matched links: %d | updated records: %d',
        $target,
        $result['records'],
        $result['replacements'],
        $result['updated']
    ));
}

if ($execute) {
    mtrace("Completed. Updated {$totals['updated']} records with {$totals['replacements']} link replacements.");
} else {
    mtrace('Dry-run only (no database writes). Use --execute to apply changes.');
    mtrace("Would update {$totals['records']} records with {$totals['replacements']} link replacements.");
}
