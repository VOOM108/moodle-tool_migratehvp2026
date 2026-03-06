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
 * This tool can upgrade mod_hvp activities (Joubel) to the new mod_h5p activity (Moodle HQ).
 *
 * The upgrade can be done on any HVP activity instance.
 * The new HP5activity module was introduced in Moodle 3.9 and although it almost reproduces
 * the features of the existing mod_hvp, it wasn't designed to replace it entirely as there
 * are some features than the current mod_h5pactivity doesn't support, such as saving status or H5P hub.
 *
 * This screen is the main entry-point to the plugin, it gives the admin a list
 * of options available to them.
 *
 * @package     tool_migratehvp2026
 * @copyright   2020 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use tool_migratehvp2026\output\hvpactivities_table;
use tool_migratehvp2026\output\listnotmigrated;
use tool_migratehvp2026\api;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();

$activityids = optional_param_array('activityids', [], PARAM_INT);
$keeporiginal = optional_param('keeporiginal', api::HIDEORIGINAL, PARAM_INT);
$copy2cb = optional_param('copy2cb', api::COPY2CBYESWITHLINK, PARAM_INT);
$hidesuffix = trim(optional_param('hidesuffix', '', PARAM_TEXT));
$preserveavailability = optional_param('preserveavailability', 1, PARAM_INT);
$preserveavailability = ($preserveavailability === 0) ? 0 : 1;
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$coursevisible = optional_param('coursevisible', -1, PARAM_INT);
if (!in_array($coursevisible, [-1, 0, 1], true)) {
    $coursevisible = -1;
}
$contenttype = trim(optional_param('contenttype', '', PARAM_TEXT));
$perpage = optional_param('perpage', 50, PARAM_INT);
$linkaction = trim(optional_param('linkaction', '', PARAM_ALPHA));
$linktargets = optional_param_array('linktargets', [], PARAM_ALPHA);

$allowedlinktargets = ['pages', 'labels', 'sections'];
if (empty($linktargets)) {
    $linktargets = $allowedlinktargets;
}
$linktargets = array_values(array_unique(array_intersect($linktargets, $allowedlinktargets)));
if (empty($linktargets)) {
    $linktargets = $allowedlinktargets;
}

// Restrict page size to safe values.
$perpage = min(max($perpage, 10), 500);

if ($keeporiginal === api::DELETEORIGINAL) {
    $hidesuffix = '';
}

$urlparams = [
    'categoryid' => $categoryid,
    'courseid' => $courseid,
    'coursevisible' => $coursevisible,
    'contenttype' => $contenttype,
    'perpage' => $perpage,
];
$urlparams = array_filter($urlparams, function($value) {
    return $value !== '' && $value !== 0;
});
$url = new moodle_url('/admin/tool/migratehvp2026/index.php', $urlparams);

// This calls require_login and checks moodle/site:config.
admin_externalpage_setup('migratehvp2026');

$notices = [];

if (in_array($linkaction, ['preview', 'execute', 'exportcsv'], true)) {
    require_sesskey();

    try {
        if ($linkaction === 'exportcsv') {
            $csv = api::export_migrated_link_map_csv($courseid, $categoryid);
            $filename = 'migratehvp2026-link-map-' . date('Ymd-His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $csv;
            exit;
        }

        $executelinks = ($linkaction === 'execute');
        $summary = api::rewrite_migrated_links($linktargets, $executelinks, $courseid, $categoryid);

        $a = (object) [
            'maps' => (int)$summary['mapcount'],
            'records' => (int)$summary['totals']['records'],
            'replacements' => (int)$summary['totals']['replacements'],
            'updated' => (int)$summary['totals']['updated'],
            'courses' => (int)($summary['totals']['refreshedcourses'] ?? 0),
        ];

        if ($executelinks) {
            $notices[] = [get_string('linkrewrite_execute_result', 'tool_migratehvp2026', $a), notification::NOTIFY_SUCCESS];
        } else {
            $notices[] = [get_string('linkrewrite_preview_result', 'tool_migratehvp2026', $a), notification::NOTIFY_INFO];
        }
    } catch (moodle_exception $e) {
        $notices[] = [$e->getMessage(), notification::NOTIFY_ERROR];
    }
}

if (!empty($activityids)) {
    foreach ($activityids as $activityid) {
        try {
            $messages = api::migrate_hvp2h5p(
                $activityid,
                $keeporiginal,
                $copy2cb,
                $hidesuffix,
                $preserveavailability
            );
            if (empty($messages)) {
                // Use the default message when no message is raised by the migration method.
                $notices[] = [get_string('migrate_success', 'tool_migratehvp2026', $activityid), notification::NOTIFY_SUCCESS];
            } else {
                // Merge message with previous notices.
                $notices = array_merge($messages, $notices);
            }
        } catch (moodle_exception $e) {
            $errormsg = get_string('migrate_fail', 'tool_migratehvp2026', $activityid);
            $errormsg .= ': '.$e->getMessage();
            $notices[] = [$errormsg, notification::NOTIFY_ERROR];
        }
    }
} else {
    try {
        api::check_requirements($copy2cb);
    } catch (moodle_exception $e) {
        $notices[] = [$e->getMessage(), notification::NOTIFY_ERROR];
    }
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');

$PAGE->set_title(get_string('pluginname', 'tool_migratehvp2026'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('hvpactivities', 'tool_migratehvp2026'));

foreach ($notices as $notice) {
    echo $OUTPUT->notification($notice[0], $notice[1]);
}

$table = new hvpactivities_table();
$table->baseurl = $url;
$table->filtercategoryid = $categoryid;
$table->filtercourseid = $courseid;
$table->filtercoursevisible = $coursevisible;
$table->filtercontenttype = $contenttype;
$table->filterperpage = $perpage;
$table->keeporiginal = $keeporiginal;
$table->copy2cb = $copy2cb;
$table->hidesuffix = $hidesuffix;
$table->preserveavailability = $preserveavailability;
$table->linktargets = $linktargets;
$activitylist = new listnotmigrated($table);
echo $OUTPUT->render($activitylist);

echo $OUTPUT->footer();
