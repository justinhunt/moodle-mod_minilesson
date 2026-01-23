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

namespace mod_minilesson\output;

use mod_minilesson\aigen;
use mod_minilesson\aigen_contextform;
use mod_minilesson\constants;
use mod_minilesson\template_tag_manager;
use moodle_url;
use renderable;
use renderer_base;
use single_button;
use templatable;

/**
 * Class aigentemplates
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigentemplates implements renderable, templatable
{
    /**
     * @var object $cm course module object
     */
    protected $cm;

    /**
     * @var array $filters tag filters array
     */
    protected $filters;

    /**
     * Constructor.
     *
     * @param object $cm course module object
     * @param array $filters tag filters array
     */
    public function __construct($cm, $filters)
    {
        $this->cm = $cm;
        $this->filters = $filters;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output)
    {
        $tags = self::get_alltags();
        $tags = array_intersect($this->filters, $tags);

        // Fetch templates.
        $lessontemplates = aigen::fetch_lesson_templates($tags);

        $buttondata = [];
        foreach ($lessontemplates as $templateid => $lessontemplate) {
            $templatecount = count($lessontemplate['template']->items);
            // If we have lang strings we use them here.
            if (array_key_exists($lessontemplate['config']->uniqueid, aigen::DEFAULTTEMPLATES)) {
                $templateshortname = aigen::DEFAULTTEMPLATES[$lessontemplate['config']->uniqueid];
                $templatetitle = get_string("aigentemplatename:" . $templateshortname, constants::M_COMPONENT);
                $templatedescription = get_string("aigentemplatedescription:" . $templateshortname, constants::M_COMPONENT);
            } else {
                $templatetitle = $lessontemplate['config']->lessonTitle;
                $templatedescription = $lessontemplate['config']->lessonDescription;
            }

            $thebutton = new single_button(
                new moodle_url(
                    constants::M_URL . '/aigen.php',
                    [
                        'id' => $this->cm->id,
                        'action' => aigen_contextform::AIGEN_SUBMIT,
                        'templateid' => $templateid,
                    ]
                ),
                get_string('aigen', constants::M_COMPONENT)
            );
            $buttondata[] = [
                'templateid' => $templateid,
                'title' => $templatetitle,
                'description' => $templatedescription,
                'itemcount' => $templatecount,
                'thebutton' => $thebutton->export_for_template($output),
            ];
        }

        return [
            'buttons' => $buttondata,
        ];
    }

    /**
     * Get all available tags.
     *
     * @param bool $withlabels Whether to include labels with tags.
     * @return array List of all tags, optionally with labels.
     */
    public static function get_alltags($withlabels = false)
    {
        // Predefined tags.
        if ($withlabels) {
            $predefinedtags = [];
            $tagsonly = template_tag_manager::get_predefined_tags();
            foreach ($tagsonly as $tag) {
                $taglabel = $tag;
                $predefinedtags[] = ['tag' => $tag, 'label' => $taglabel];
            }
        } else {
            $predefinedtags = template_tag_manager::get_predefined_tags();
        }

        // Single or multi item tags.
        if ($withlabels) {
            $singleormultitags = [];
            $tagsonly = template_tag_manager::get_singleormulti_tags();
            foreach ($tagsonly as $tag) {
                $taglabel = $tag;
                $singleormultitags[] = ['tag' => $tag, 'label' => $taglabel];
            }
        } else {
            $singleormultitags = template_tag_manager::get_singleormulti_tags();
        }

        // Item type tags.
        if ($withlabels) {
            $tagsonly = template_tag_manager::get_itemtype_tags();
            $itemtypetags = [];
            foreach ($tagsonly as $tag) {
                $taglabel = get_string($tag, constants::M_COMPONENT);
                $itemtypetags[] = ['tag' => $tag, 'label' => $taglabel];
            }
        } else {
            $itemtypetags = template_tag_manager::get_itemtype_tags();
        }

        $tags = array_merge($predefinedtags, $singleormultitags, $itemtypetags);
        return $tags;
    }
}
