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

declare(strict_types=1);

namespace mod_attendance\local\entities;

use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\duration;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\entities\base;
use core_user\fields;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\local\entities\user;
use lang_string;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Attendance entity class implementation attendance
 *
 * This entity defines all the attendance columns and filters to be used in any report.
 *
 * @package     mod_attendance
 * @copyright   2022 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
                'user' => 'attu',
                'context' => 'attctx',
                'course' => 'attc',
                'attendance' => 'att',
                'attendance_sessions' => 'attsess',
                'attendance_log' => 'attlog',
                'attendance_statuses' => 'attstat',
                'numsessionstaken' => 'numsessionstaken',
                'pointssessionscompleted' => 'pointssessionscompleted',
                'maxpossible' => 'maxpossible',
               ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('attendancereport', 'mod_attendance');
    }

    /**
     * Initialise the entity, add all user fields and all 'visible' user profile fields
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        // TODO: differentiate between filters and conditions (specifically the 'date' type: MDL-72662).
        $conditions = $this->get_all_filters();
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * These are all the columns available to use in any report that uses this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {

        $columns = [];

        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');
        $numsessionstakenalias = $this->get_table_alias('numsessionstaken');
        $pointssessionscompletedalias = $this->get_table_alias('pointssessionscompleted');
        $maxpossiblealias = $this->get_table_alias('maxpossible');

        $join = $this->attendancejoin();

        // Attendance name column.
        $columns[] = (new column(
            'name',
            new lang_string('name', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancealias}.name");

        // Now handle session columns.

        // Description column.
        $columns[] = (new column(
            'sessiondescription',
            new lang_string('sessiondescription', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.description");

        // Session date column.
        $columns[] = (new column(
            'sessiondate',
            new lang_string('reportsessiondate', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.sessdate")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });

        // Session duration column.
        $columns[] = (new column(
            'duration',
            new lang_string('reportsessionduration', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.duration");

        // Session last taken column.
        $columns[] = (new column(
            'lasttaken',
            new lang_string('reportsessionlasttaken', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.lasttaken")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });
        // Now add Log columns.

        // Time taken column.
        $columns[] = (new column(
            'timetaken',
            new lang_string('usersessiontaken', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancelogalias}.timetaken")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });

        // Now add Status columns.

        // Status column.
        $columns[] = (new column(
            'status',
            new lang_string('userstatus', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancestatusalias}.acronym");

        // Grade column.
        $columns[] = (new column(
            'grade',
            new lang_string('usersessiongrade', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancestatusalias}.grade");

        // Remarks column.
        $columns[] = (new column(
            'remarks',
            new lang_string('usersessionremarks', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancelogalias}.remarks");

        // Taken sessions column.
        $columns[] = (new column(
            'numsessionstaken',
            new lang_string('numsessionstaken', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->add_join("JOIN (
                SELECT a.course, atlo.studentid, COUNT(DISTINCT atse.id) AS numsessionstaken
                FROM {attendance_sessions} atse
                JOIN {attendance} a ON a.id = atse.attendanceid
                JOIN {course} c ON c.id = a.course
                JOIN {attendance_log} atlo ON (atlo.sessionid = atse.id)
                JOIN {attendance_statuses} atst ON (atst.id = atlo.statusid AND atst.deleted = 0 AND atst.visible = 1)
                GROUP BY a.id, a.course, atlo.studentid
            ) {$numsessionstakenalias}
            ON {$numsessionstakenalias}.course = {$attendancealias}.course
            AND {$numsessionstakenalias}.studentid = {$attendancelogalias}.studentid")
            ->set_is_sortable(true)
            ->add_field("{$numsessionstakenalias}.numsessionstaken");

        // Total points across sessions column.
        $columns[] = (new column(
            'pointssessionscompleted',
            new lang_string('pointssessionscompleted', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->add_join("JOIN (
                SELECT a.course, atlo.studentid, SUM(atst.grade) AS points
                FROM {attendance_sessions} atse
                JOIN {attendance} a ON a.id = atse.attendanceid
                JOIN {course} c ON c.id = a.course
                JOIN {attendance_log} atlo ON (atlo.sessionid = atse.id)
                JOIN {attendance_statuses} atst ON (atst.id = atlo.statusid AND atst.deleted = 0 AND atst.visible = 1)
                GROUP BY a.id, a.course, atlo.studentid
            ) {$pointssessionscompletedalias}
            ON {$pointssessionscompletedalias}.course = {$attendancealias}.course
            AND {$pointssessionscompletedalias}.studentid = {$attendancelogalias}.studentid")
            ->set_is_sortable(true)
            ->add_field("{$pointssessionscompletedalias}.points");

        // Maximum possible points column.
        $columns[] = (new column(
            'maxpossible',
            new lang_string('maxpossible', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->add_join("JOIN (
                SELECT a.course, atlo.studentid, SUM(stm.maxgrade) AS maxpoints
                FROM {attendance_sessions} atse
                JOIN {attendance} a ON a.id = atse.attendanceid
                JOIN {course} c ON c.id = a.course
                JOIN {attendance_log} atlo ON atlo.sessionid = atse.id
                JOIN (
                    SELECT attendanceid, setnumber, MAX(grade) AS maxgrade
                    FROM {attendance_statuses}
                    WHERE deleted = 0
                    AND visible = 1
                    GROUP BY attendanceid, setnumber
                ) stm ON stm.setnumber = atse.statusset AND stm.attendanceid = atse.attendanceid
                GROUP BY a.id, a.course, atlo.studentid
            ) {$maxpossiblealias}
            ON {$maxpossiblealias}.course = {$attendancealias}.course
            AND {$maxpossiblealias}.studentid = {$attendancelogalias}.studentid")
            ->set_is_sortable(true)
            ->add_field("{$maxpossiblealias}.maxpoints");

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {

        $filters = [];
        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');

        $join = $this->attendancejoin();

        // Session name filter.
        $filters[] = (new filter(
            text::class,
            'nameselector',
            new lang_string('name', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancealias}.name"
        ))
            ->add_join($join);

        // Description filter.
        $filters[] = (new filter(
            text::class,
            'sessiondescription',
            new lang_string('sessiondescription', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.description"
        ))
            ->add_join($join);

        // Session date filter.
        $filters[] = (new filter(
            date::class,
            'sessiondate',
            new lang_string('reportsessiondate', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.sessdate"
        ))
            ->add_join($join);

        // Duration filter.
        $filters[] = (new filter(
            duration::class,
            'duration',
            new lang_string('reportsessionduration', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.duration"
        ))
            ->add_join($join);

        // Last taken filter.
        $filters[] = (new filter(
            date::class,
            'lasttaken',
            new lang_string('reportsessionlasttaken', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.lasttaken"
        ))
            ->add_join($join);

        // Status filter.
        $filters[] = (new filter(
            text::class,
            'status',
            new lang_string('userstatus', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancestatusalias}.acronym"
        ))
            ->add_join($join);

        // Time taken filter.
        $filters[] = (new filter(
            date::class,
            'timetaken',
            new lang_string('usersessiontaken', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancelogalias}.timetaken"
        ))
            ->add_join($join);

        // Remarks filter.
        $filters[] = (new filter(
            text::class,
            'remarks',
            new lang_string('usersessionremarks', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancelogalias}.remarks"
        ))
            ->add_join($join);

        return $filters;
    }
    /**
     * Helper function to get main join.
     *
     * @return string
     */
    public function attendancejoin() {
        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');

        return "JOIN {attendance_statuses} {$attendancestatusalias}
                    ON {$attendancestatusalias}.id = {$attendancelogalias}.statusid
                JOIN {attendance_sessions} {$attendancesessionalias}
                    ON {$attendancesessionalias}.id = {$attendancelogalias}.sessionid
                JOIN {attendance} {$attendancealias}
                    ON {$attendancealias}.id = {$attendancesessionalias}.attendanceid";
    }
}
