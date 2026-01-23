<?php

// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_minilesson\output;

use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * report renderer class for mod_minilesson
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_renderer extends \plugin_renderer_base
{
    /**
     * Render report menu
     *
     * @param object $moduleinstance
     * @param object $cm
     * @return string
     */
    public function render_reportmenu($moduleinstance, $cm)
    {
        $reports = [];
        // Grades report.
        $gradesreport = new \single_button(
            new \moodle_url(
                constants::M_URL . '/reports.php',
                ['report' => 'gradereport', 'id' => $cm->id, 'n' => $moduleinstance->id]
            ),
            get_string('gradereport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = [
            'button' => $this->render($gradesreport),
            'text' => get_string('gradereport_explanation', constants::M_COMPONENT),
        ];
        // Attempts report.
        $attemptsreport = new \single_button(
            new \moodle_url(
                constants::M_URL . '/reports.php',
                ['report' => 'attempts', 'id' => $cm->id, 'n' => $moduleinstance->id]
            ),
            get_string('attemptsreport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = [
            'button' => $this->render($attemptsreport),
            'text' => get_string('attemptsreport_explanation', constants::M_COMPONENT),
        ];

        // Incomplete attempts report.
        $incompleteattemptsreport = new \single_button(
            new \moodle_url(
                constants::M_URL . '/reports.php',
                ['report' => 'incompleteattempts', 'id' => $cm->id, 'n' => $moduleinstance->id]
            ),
            get_string('incompleteattemptsreport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = [
            'button' => $this->render($incompleteattemptsreport),
            'text' => get_string('incompleteattemptsreport_explanation', constants::M_COMPONENT),
        ];

        // Course attempts report.
        $courseattemptsreport = new \single_button(
            new \moodle_url(
                constants::M_URL . '/reports.php',
                ['report' => 'courseattempts', 'id' => $cm->id, 'n' => $moduleinstance->id]
            ),
            get_string('courseattemptsreport', constants::M_COMPONENT),
            'get'
        );
        $reports[] = [
            'button' => $this->render($courseattemptsreport),
            'text' => get_string('courseattemptsreport_explanation', constants::M_COMPONENT),
        ];

        $data = ['reports' => $reports];
        $ret = $this->render_from_template('mod_minilesson/reportsmenu', $data);

        return $ret;
    }

    /**
     * Render delete all attempts button
     *
     * @param object $cm
     * @return string
     */
    public function render_delete_allattempts($cm)
    {
        $deleteallbutton = new \single_button(
            new \moodle_url(constants::M_URL . '/manageattempts.php', ['id' => $cm->id, 'action' => 'confirmdeleteall']),
            get_string('deleteallattempts', constants::M_COMPONENT),
            'get'
        );
        $ret = \html_writer::div($this->render($deleteallbutton), constants::M_CLASS . '_actionbuttons');
        return $ret;
    }

    /**
     * Render report title HTML
     *
     * @param object $course
     * @param string $username
     * @return string
     */
    public function render_reporttitle_html($course, $username)
    {
        $ret = $this->output->heading(format_string($course->fullname), 2);
        $ret .= $this->output->heading(get_string('reporttitle', constants::M_COMPONENT, $username), 3);
        return $ret;
    }

    /**
     * Render empty section HTML
     *
     * @param string $sectiontitle
     * @return string
     */
    public function render_empty_section_html($sectiontitle)
    {
        global $CFG;
        return $this->output->heading(get_string('nodataavailable', constants::M_COMPONENT), 3);
    }

    /**
     * Render export buttons HTML
     *
     * @param object $cm
     * @param object $formdata
     * @param string $showreport
     * @return string
     */
    public function render_exportbuttons_html($cm, $formdata, $showreport)
    {
        // Convert formdata to array.
        $formdata = (array)$formdata;
        $formdata['id'] = $cm->id;
        $formdata['report'] = $showreport;
        $formdata['format'] = 'csv';
        $excel = new \single_button(
            new \moodle_url(constants::M_URL . '/reports.php', $formdata),
            get_string('exportexcel', constants::M_COMPONENT),
            'get'
        );

        return \html_writer::div($this->render($excel), constants::M_CLASS . '_actionbuttons');
    }

    /**
     * Render grading export buttons HTML
     *
     * @param object $cm
     * @param object $formdata
     * @param string $action
     * @return string
     */
    public function render_grading_exportbuttons_html($cm, $formdata, $action)
    {
        // Convert formdata to array.
        $formdata = (array)$formdata;
        $formdata['id'] = $cm->id;
        $formdata['action'] = $action;
        $formdata['format'] = 'csv';
        $excel = new \single_button(
            new \moodle_url(constants::M_URL . '/grading.php', $formdata),
            get_string('exportexcel', constants::M_COMPONENT),
            'get'
        );
        return \html_writer::div($this->render($excel), constants::M_CLASS . '_actionbuttons');
    }

    /**
     * Render section CSV
     *
     * @param string $sectiontitle
     * @param string $report
     * @param array $head
     * @param array $rows
     * @param array $fields
     */
    public function render_section_csv($sectiontitle, $report, $head, $rows, $fields)
    {
        // Use the sectiontitle as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($sectiontitle, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", utils::super_trim($name));
        $quote = '"';
        $delim = ",";
        $newline = "\r\n";

        header("Content-Disposition: attachment; filename=$name.csv");
        header("Content-Type: text/comma-separated-values");

        $heading = "";
        foreach ($head as $headfield) {
            $heading .= $quote . $headfield . $quote . $delim;
        }
        echo $heading . $newline;

        foreach ($rows as $row) {
            $datarow = "";
            foreach ($fields as $field) {
                $datarow .= $quote . $row->{$field} . $quote . $delim;
            }
            echo $datarow . $newline;
        }
        exit();
    }

    /**
     * Render section HTML
     *
     * @param string $sectiontitle
     * @param string $report
     * @param array $head
     * @param array $rows
     * @param array $fields
     * @return string
     */
    public function render_section_html($sectiontitle, $report, $head, $rows, $fields)
    {
        global $CFG;
        if (empty($rows)) {
            return $this->render_empty_section_html($sectiontitle);
        }

        $config = get_config(constants::M_COMPONENT);

        // Set up our table and head attributes.
        $tableattributes = ['class' => 'generaltable ' . constants::M_CLASS . '_table'];
        $headrowattributes = ['class' => constants::M_CLASS . '_headrow'];

        $htmltable = new \html_table();
        $tableid = \html_writer::random_id(constants::M_COMPONENT);
        $htmltable->id = $tableid;
        $htmltable->attributes = $tableattributes;

        $headcells = [];
        foreach ($head as $headcell) {
            $headcells[] = new \html_table_cell($headcell);
        }
        $htmltable->head = $head;

        foreach ($rows as $row) {
            $htr = new \html_table_row();
            // Set up description cell.
            $cells = [];
            foreach ($fields as $field) {
                $cell = new \html_table_cell($row->{$field});
                $cell->attributes = ['class' => constants::M_CLASS . '_cell_' . $report . '_' . $field];
                $htr->cells[] = $cell;
            }

            $htmltable->data[] = $htr;
        }
        $html = $this->output->heading($sectiontitle, 4);
        $html .= \html_writer::table($htmltable);

        // If datatables set up datatables.
        if ($config->reportstable == constants::M_USE_DATATABLES) {
            $tableprops = [];
            $tableprops['paging'] = true;
            $tableprops['pageLength'] = 10;
            $opts = [];
            $opts['tableid'] = $tableid;
            $opts['tableprops'] = $tableprops;
            $this->page->requires->js_call_amd(constants::M_COMPONENT . "/datatables", 'init', [$opts]);
        }

        return $html;
    }

    /**
     * Show reports footer
     *
     * @param object $moduleinstance
     * @param object $cm
     * @param object $formdata
     * @param string $showreport
     * @return string
     */
    public function show_reports_footer($moduleinstance, $cm, $formdata, $showreport)
    {
        // Print's a popup link to your custom page.
        $link = new \moodle_url(
            constants::M_URL . '/reports.php',
            [
                'report' => 'menu',
                'id' => $cm->id,
                'n' => $moduleinstance->id,
            ]
        );
        $ret = \html_writer::link($link, get_string('returntoreports', constants::M_COMPONENT));
        $ret .= $this->render_exportbuttons_html($cm, $formdata, $showreport);
        return $ret;
    }

    /**
     * Returns HTML to display a selector to choose how many entries to show per page
     * @param \moodle_url $url url of the current page, the 'perpage' parameter is added
     * @param object $paging an object containting sort/perpage/pageno fields. Created in reports.php and grading.php
     * @return string the HTML to output.
     */
    public function show_perpage_selector($url, $paging)
    {
        $options = ['5' => 5, '10' => 10, '20' => 20, '40' => 40, '80' => 80, '150' => 150];
        $selector = new \single_select($url, 'perpage', $options, $paging->perpage);
        $selector->set_label(get_string('attemptsperpage', constants::M_COMPONENT));
        return $this->render($selector);
    }

    /**
     * Returns HTML to display a single paging bar to provide access to other pages  (usually in a search)
     * @param int $totalcount The total number of entries available to be paged through
     * @param stdclass $paging an object containting sort/perpage/pageno fields. Created in reports.php and grading.php
     * @param string|moodle_url $baseurl url of the current page, the $pagevar parameter is added
     * @return string the HTML to output.
     */
    public function show_paging_bar($totalcount, $paging, $baseurl)
    {
        $pagevar = "pageno";
        // Add paging params to url (NOT pageno).
        $baseurl->params(['perpage' => $paging->perpage, 'sort' => $paging->sort]);
        return $this->output->paging_bar($totalcount, $paging->pageno, $paging->perpage, $baseurl, $pagevar);
    }

    /**
     * Show grading footer
     *
     * @param object $moduleinstance
     * @param object $cm
     * @param string $mode
     * @return string
     */
    public function show_grading_footer($moduleinstance, $cm, $mode)
    {
        // Takes you back to home.
        $link = new \moodle_url(constants::M_URL . '/grading.php', ['id' => $cm->id, 'n' => $moduleinstance->id]);
        $ret = \html_writer::link($link, get_string('returntogradinghome', constants::M_COMPONENT));
        return $ret;
    }

    /**
     * Show export buttons
     *
     * @param object $cm
     * @param object $formdata
     * @param string $showreport
     * @return string
     */
    public function show_export_buttons($cm, $formdata, $showreport)
    {
        switch ($showreport) {
            case 'grading':
                return $this->render_grading_exportbuttons_html($cm, $formdata, $showreport);
            default:
                return $this->render_exportbuttons_html($cm, $formdata, $showreport);
        }
    }
}
