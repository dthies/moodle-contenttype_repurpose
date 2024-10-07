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
 * Provides the class that defines the form for the repurpose authoring tool.
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_repurpose\form;

use contenttype_h5p\content;
use contenttype_h5p\contenttype;
use core_contentbank\form\edit_content;
use core_h5p\editor as h5peditor;
use core_h5p\factory;
use core_h5p\helper;
use context_user;
use context;
use stdClass;
use moodle_exception;
use moodle_form;
use question_bank;
use license_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/licenselib.php');

/**
 * Defines the form for editing an repurpose content.
 *
 * This file is the integration between a content type editor and the content
 * bank creation form.
 *
 * @copyright 2020 onward Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor extends \contenttype_h5p\form\editor {
    /** @var $contents Array of content types available to use as bassis */
    public $contents = null;

    /** @var $h5peditor H5P editor binding */
    protected $h5peditor = null;

    /** @var $helper H5P helper binding */
    protected $helper = null;

    /**
     * Defines the form fields.
     */
    protected function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform = $this->_form;

        // This methos adds the save and cancel buttons.
        $this->add_action_buttons();

        // Id of the content to edit (null if it's creation).
        $id = $this->_customdata['id'] ?? null;
        $contextid = $this->_customdata['contextid'];

        $this->h5peditor = new h5peditor();

        $library = $this->_customdata['library'] ?? optional_param('library', 'singlechoiceset', PARAM_TEXT);
        if ($id && $record = $DB->get_record('contentbank_content', ['id' => $id])) {
            $content = new \contenttype_repurpose\content($record);
            $configdata = json_decode($content->get_configdata());
            $configdata->id = $id;
            $library = $configdata->library;
        }

        $mform->addElement('html', $OUTPUT->render_from_template('contenttype_repurpose/tutorial', [
            'library' => $library,
            'type' => get_string('import' . $library, 'contenttype_repurpose'),
        ]));

        // Content name.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'name', 'contenttype_repurpose');

        // Add license selector.
        $licenses = array_column(license_manager::get_active_licenses(), 'fullname', 'shortname');
        $mform->addElement('select', 'license', get_string('license'), $licenses, '');
        $mform->setDefault('license', $CFG->sitedefaultlicense);
        $mform->addHelpButton('license', 'license', 'contenttype_repurpose');

        $context = context::instance_by_id($contextid, MUST_EXIST);

        // Add context type specific form fields.
        $mform->addElement('hidden', 'library', $library);
        $mform->setType('library', PARAM_TEXT);
        $helperclass = $this->get_helperclass($library);
        $this->helper = new $helperclass($context);

        $this->helper->add_form_fields($mform);

        $repeatoptions = $repeatoptions ?? [];

        if (!empty($repeatelements)) {
            $this->repeat_elements(
                $repeatelements,
                3,
                $repeatoptions,
                'option_repeats',
                'option_add_fields',
                3,
                null,
                true
            );
        }

        $this->add_action_buttons();
    }

    /**
     * Modify or create a content from the form data.
     *
     * @param stdClass $data Form data to create or modify an h5p content.
     *
     * @return int The id of the edited or created content.
     */
    public function save_content(stdClass $data): int {
        global $DB, $USER;

        $context = context::instance_by_id($data->contextid, MUST_EXIST);

        $this->helper->process_files($this);

        $h5pparams = $this->helper->get_content($data);

        $tempdir = make_request_directory();
        $filename = $tempdir . '/content.zip';
        $packer = get_file_packer('application/zip');

        $factory = new factory();
        $h5pfs = $factory->get_framework();
        if (!empty($data->contenttype)) {
            $file = $this->contents[$data->contenttype]->get_file();
            $h5p = \core_h5p\api::get_content_from_pathnamehash($file->get_pathnamehash());
        }
        if (!empty($h5p)) {
            \core_h5p\local\library\autoloader::register();

            $h5pcontent = $h5pfs->loadContent($h5p->id);
            $params = json_decode($h5pcontent['params']);

            $file->copy_content_to($filename);
            if ($packer->extract_to_pathname($filename, $tempdir)) {
                $contentfiles = array_map(function ($pathname) use ($tempdir) {
                    return (strpos('/content/', $pathname) == 0) ? $tempdir . '/' . $pathname : null;
                }, array_column($packer->list_files($filename), 'pathname', 'pathname'));
                unset($contentfiles['/content/content.js']);
                if (empty($this->helper->files)) {
                    $this->helper->files = array_filter($contentfiles);
                } else {
                    $this->helper->files += array_filter($contentfiles);
                }
            }

            $h5pparams = $this->helper->merge($h5pparams, $params);
        }

        // If name missing use default.
        if (empty($data->name)) {
            if (isset($data->question)) {
                $data->name = $DB->get_field('question', 'name', ['id' => $data->question]);
            } else if (isset($data->category)) {
                $data->name = $DB->get_field('question_categories', 'name', [
                    'id' => explode(',', $data->category)[0],
                ]);
            }
        }

        $h5pparams->params = $h5pparams->params ?? null;
        $h5pparams->metadata = $h5pparams->metadata ?? new stdClass();
        $h5pparams->metadata->title = $data->name;
        $h5pparams->metadata->license = self::get_h5p_license($data->license);
        $content = (object) [
            'library' => $this->helper->library,
            'license' => self::get_h5p_license($data->license),
            'h5plibrary' => $this->helper->library,
            'h5pparams' => json_encode($h5pparams),
            'contextid' => $context->id,
            'plugin' => 'h5p',
            'h5paction' => 'create',
        ];

        $h5pcontentid = $this->h5peditor->save_content($content);

        // Need the H5P file id to create the new content bank record.
        $h5pcontent = $h5pfs->loadContent($h5pcontentid);
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash($h5pcontent['pathnamehash']);

        // Save any files used for the content.
        if (!empty($this->helper->files)) {
            $file->copy_content_to($filename);
            if ($packer->extract_to_pathname($filename, $tempdir)) {
                $h5p = json_decode(file_get_contents($tempdir . '/h5p.json'));
                $h5p->metadata = $h5p->metadata ?? new stdClass();
                $h5p->metadata->license = self::get_h5p_license($data->license);

                file_put_contents($tempdir . '/h5p.json', json_encode($h5p));
                $files = $this->helper->files + array_map(function ($pathname) use ($tempdir) {
                    return $tempdir . '/' . $pathname;
                }, array_column($packer->list_files($filename), 'pathname', 'pathname'));

                $itemid = file_get_unused_draft_itemid();
                $filename = $file->get_filename();
                $file->delete();
                $file = $packer->archive_to_storage(
                    $files,
                    context_user::instance($USER->id)->id,
                    'user',
                    'draft',
                    $itemid,
                    '/',
                    $filename
                );
            }
        }
        $file->set_license($data->license);

        if (get_config('contenttype_repurpose', 'saveash5p')) {
            // Creating new content.
            // The initial name of the content is the title of the H5P content.
            $cbrecord = new stdClass();
            $cbrecord->name = json_decode($content->h5pparams)->metadata->title;
            $context = \context::instance_by_id($data->contextid, MUST_EXIST);

            // Create entry in content bank.
            $contenttype = new contenttype($context);
            $newcontent = $contenttype->create_content($cbrecord);
            $newcontent->import_file($file);

            // Delete the export file.
            $file->delete();

            return $newcontent->get_id();
        }

        if (empty($data->id)) {
            // Create a new content.
            $context = context::instance_by_id($data->contextid, MUST_EXIST);
            $contenttype = new \contenttype_repurpose\contenttype($context);
            $record = new stdClass();
            $content = $contenttype->create_content($record);
        } else {
            // Update current content.
            $record = $DB->get_record('contentbank_content', ['id' => $data->id]);
            $content = new \contenttype_repurpose\content($record);
        }

        // Update content.
        $data->h5pparams = $h5pparams;
        if (!empty($this->helper->mediafiles)) {
            $data->mediafiles = json_encode($this->helper->mediafiles);
        }
        $content->set_name($data->name);
        $content->set_configdata(json_encode($data));
        $content->save_public($file);
        $file->delete();
        $content->update_content();

        if (!empty($this->helper->files)) {
            if (
                !empty($data->id)
                && $files = $fs->get_area_files($data->contextid, 'contenttype_repurpose', 'content', $content->get_id())
            ) {
                $path = substr($file->get_filepath() . $file->get_filename(), 1);
                foreach ($files as $file) {
                    if (!$file->is_directory() && !key_exists($path, $this->helper->files)) {
                        $file->delete();
                    }
                }
                unset($path);
            }
            foreach ($this->helper->files as $path => $file) {
                $matches = [];
                preg_match('/(.*\\/)(.*)/', $path, $matches);
                $filerecord = [
                    'contextid' => $data->contextid,
                    'component' => 'contenttype_repurpose',
                    'filearea' => 'content',
                    'itemid' => $content->get_id(),
                    'filepath' => '/' . $matches[1],
                    'filename' => $matches[2],
                    'timecreated' => time(),
                ];
                if (is_array($file)) {
                    $fs->create_file_from_string($filerecord, reset($file));
                } else if (is_string($file)) {
                    $fs->create_file_from_string($filerecord, $file);
                } else {
                    $fs->create_file_from_storedfile($filerecord, $file);
                }
            }
        }

        return $content->get_id();
    }

    /**
     * Allow helper to define form with supplied data.
     * @return void
     */
    public function definition_after_data() {
        $mform = $this->_form;
        $this->helper->definition_after_data($mform);

        parent::definition_after_data();
    }

    /**
     * Generate a character string to be used as subcontent id
     * @return string
     */
    public function create_subcontentid(): string {
        $string = array_map(function () {
            return substr('0123456789abcdef', rand(0, 15), 1);
        }, array_fill(0, 31, 0));
        $string = implode($string);
        return preg_replace('/(........)(....)(...)(....)/', '$1-$2-4$3-$4-', $string);
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        return parent::validation($data, $files) + $this->helper->validation($data, $files);
    }

    /**
     * Add a element to select and element to which to add questions.
     *
     * @param string $machinename the machine nae to use to filter content types
     */
    protected function add_content_selector(string $machinename) {
        global $DB, $PAGE;
        $mform = $this->_form;
        $search = '';
        $options = ['' => ''];
        $contentbank = new \core_contentbank\contentbank();
        // Return all content bank content that matches the search criteria and can be viewed/accessed by the user.
        $contents = $contentbank->search_contents($search);
        foreach ($contents as $content) {
            if (
                ($content->get_content_type() == 'contenttype_h5p') &&
                ($content->get_contextid() == $this->helper->context->id)
            ) {
                $file = $content->get_file();
                if (!empty($file)) {
                    $h5p = \core_h5p\api::get_content_from_pathnamehash($file->get_pathnamehash());
                    if (!empty($h5p)) {
                        \core_h5p\local\library\autoloader::register();
                        if (
                            $DB->get_record('h5p_libraries', [
                            'id' => $h5p->mainlibraryid,
                            'machinename' => $machinename,
                            ])
                        ) {
                            $options[$content->get_id()] = $content->get_name();
                            $this->contents[$content->get_id()] = $content;
                        }
                    }
                }
            }
        }
        $mform->addElement('select', 'contenttype', 'contenttype', $options, ['data-action' => 'update']);
    }

    /**
     * Return standard H5P license matching Moodle license
     *
     * @param string $shortname The Moodle license short name
     * @return string H5P license name
     */
    public static function get_h5p_license(string $shortname): string {
        $shortnames = [
            'C' => 'allrightsreserved',
            'CC BY' => 'cc',
            'CC BY-NC' => 'cc-nc',
            'CC BY-NC-ND' => 'cc-nc-nd',
            'CC BY-NC-SA' => 'cc-nc-sa',
            'CC BY-ND' => 'cc-nd',
            'CC BY-SA' => 'cc-sa',
            'PD' => 'public',
            'U' => 'unknown',
        ];
        if (in_array($shortname, $shortnames)) {
            return array_flip($shortnames)[$shortname];
        }
        return 'U';
    }
    /**
     * Get classname to use to convert content.
     *
     * @param string $library name for desired content
     * @return string Fully qualified class name
     */
    public function get_helperclass(string $library): string {
        if (class_exists('\\contenttype_repurposeplus\\local\\' . $library)) {
            return '\\contenttype_repurposeplus\\local\\' . $library;
        }
        return '\\contenttype_repurpose\\local\\' . $library;
    }
}
