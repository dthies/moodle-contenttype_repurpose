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

namespace contenttype_repurpose\local;

use contenttype_h5p\content;
use contenttype_h5p\contenttype;
use context;
use core_contentbank\form\edit_content;
use core_h5p\editor_ajax;
use core_h5p\local\library\autoloader;
use core_h5p\editor as h5peditor;
use core_h5p\factory;
use core_h5p\helper;
use context_user;
use stdClass;
use moodle_exception;
use moodle_form;
use moodle_url;
use question_bank;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/questionlib.php');

/**
 * Defines the form for editing an repurpose content.
 *
 * This file is the integration between a content type editor and the content
 * bank creation form.
 *
 * @copyright 2020 onward Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dialogcards {
    /** @var $cmid Question band module id */
    public ?int $cmid = null;

    /** @var $context Current course context for content bank */
    public $context = null;

    /** @var $files files to include with content */
    public $files = null;

    /** @var $type Machine name for target type */
    public $library = 'H5P.Dialogcards';

    /** @var $mediafiles Image files to be added */
    public $mediafiles = null;

    /**
     * Constructor
     *
     * @param context $context Course context
     * @param ?int $cmid Question bank module id
     */
    public function __construct(context $context, ?int $cmid = 0) {
        $this->context = $context;
        $this->cmid = $cmid;
        $contenttype = $this->get_contenttype();
        if (!empty($contenttype)) {
            $this->library = "{$contenttype->machine_name} {$contenttype->major_version}.{$contenttype->minor_version}";
        } else {
            $this->library = '';
        }
    }

    /**
     * Defines the additional form fields.
     *
     * @param moodle_form $mform form to modify
     */
    public function add_form_fields($mform) {
        global $OUTPUT;

        // Add HTML editor using the data information.
        $label = get_string('description');
        $mform->addElement('text', 'description', $label);
        $mform->setType('description', PARAM_TEXT);

        $context = \core\context\module::instance($mform->getElementValue('cmid'));
        $contexts = new \core_question\local\bank\question_edit_contexts($context);
        $mform->addElement(
            'questioncategory',
            'category',
            get_string('category', 'question'),
            ['contexts' => $contexts->having_cap('moodle/question:useall'), 'top' => true]
        );

        $mform->addElement('checkbox', 'recurse', get_string('recurse', 'mod_quiz'));
    }

    /**
     * Modify or create an repurpose content from the form data.
     *
     * @param stdClass $data Form data to create or modify an repurpose content.
     *
     * @return int The id of the edited or created content.
     */
    public function get_content(stdClass $data): ?stdClass {
        global $DB;

        $category = $DB->get_record('question_categories', ['id' => preg_replace('/,.*/', '', $data->category)]);

        $questions = get_questions_category($category, !empty($data->recurse));

        if (empty($data->recurse)) {
            foreach ($questions as $index => $question) {
                if ($question->category != $category->id) {
                    unset($questions[$index]);
                }
            }
        }

        $params = $this->create_params($questions);
        if (!empty($data->description)) {
            $params->description = $data->description;
        }
        return $params;
    }

    /**
     * Process data from an array of questions
     *
     * @param array $questions questions to process
     * @return stdClass The content file object
     */
    public function create_params(array $questions): stdClass {
        $content = json_decode('{
            "mode": "normal",
            "description": "",
            "dialogs": [
            ],
            "behaviour": {
                "enableRetry": true,
                "disableBackwardsNavigation": false,
                "scaleTextNotCard": false,
                "randomCards": false,
                "maxProficiency": 5,
                "quickProgression": false
            },
            "title": "<p>heading</p>\n"
        }');

        $fs = get_file_storage();
        $context = \core\context\module::instance($this->cmid);
        foreach ($questions as $question) {
            if ($question->qtype == 'shortanswer' && empty($question->parent)) {
                $answers = array_column($question->options->answers, 'fraction', 'answer');
                arsort($answers);

                $dialog = (object) [
                    'text' => strip_tags($question->questiontext, '<b><i><em><strong>'),
                    'answer' => array_keys($answers)[0],
                    'subContentId' => $this->create_subcontentid(),
                ];

                if ($files = $fs->get_area_files($context->id, 'question', 'questiontext', $question->id)) {
                    if (empty($this->files)) {
                        $this->files = [];
                    }

                    foreach ($files as $f) {
                        if ($f->is_valid_image()) {
                            $imageinfo = $f->get_imageinfo();
                            $filename = $this->getname('image', $f->get_filename());
                            $this->files['content/images/' . $filename] = $f;
                            $dialog->image = (object) [
                                'path' => 'images/' .  $filename,
                                'mimetype' => $imageinfo['mimetype'],
                                'height' => $imageinfo['height'],
                                'width' => $imageinfo['width'],
                            ];
                            break;
                        }
                    }
                }
                $content->dialogs[] = $dialog;
            }
        }

        return $content;
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
     * Get new name for the current file.
     *
     * @param string $field Prefix for name
     * @param string $originalname Name to use to determine extension
     * @return string New name
     */
    public function getname(string $field, string $originalname): string {

        $name = uniqid($field . '-');

        $matches = [];
        preg_match('/([a-z0-9]{1,})$/i', $originalname, $matches);
        if (isset($matches[0])) {
            $name .= '.' . $matches[0];
        }

        return $name;
    }

    /**
     * Change definitiion after supplied data.
     *
     * @param moodle_form $mform form with data to be modified
     * @return void
     */
    public function definition_after_data($mform) {
        global $DB;

        if (
            ($id = $mform->getElementValue('id'))
            && $record = $DB->get_record('contentbank_content', ['id' => $id])
        ) {
            $content = new \contenttype_repurpose\content($record);
            $configdata = json_decode($content->get_configdata());
            if (
                !empty($configdata)
            ) {
                $this->set_data($mform, $configdata);
            }
        }
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
        global $DB;

        $errors = [];
        $category = $DB->get_record('question_categories', [
            'id' => preg_replace('/,.*/', '', $data['category']),
        ]);

        $questions = get_questions_category($category, !empty($data->recurse));
        if (
            empty(array_filter($questions, function ($question) use ($category, $data) {
                return empty($question->parent)
                && ($question->qtype === 'shortanswer')
                && (!empty($data['recurse']) || $question->category == $category->id);
            }))
        ) {
            $errors['category'] = get_string('noquestionsselected', 'contenttype_repurpose');
        }

        return $errors;
    }

    /**
     * Get the availiable contenttype library for current machine name
     *
     * @return stdClass contenttype library info
     */
    public function get_contenttype(): ?stdClass {
        // Get the H5P content types available.
        autoloader::register();
        $editorajax = new editor_ajax();
        $h5pcontenttypes = $editorajax->getLatestLibraryVersions();

        foreach ($h5pcontenttypes as $h5pcontenttype) {
            if ($h5pcontenttype->machine_name == preg_replace('/ .*/', '', $this->library)) {
                return $h5pcontenttype;
            }
        }

        return null;
    }

    /**
     * Process files attachded to form.
     *
     * @param moodle_form $mform form that is submitted
     * @return stdClass
     */
    public function process_files($mform): void {
    }

    /**
     * Process files attachded to form.
     *
     * @param moodle_form $mform form that is submitted
     * @param stdClss $configdata current saved config data
     * @return stdClass
     */
    public function set_data($mform, $configdata): void {
        $fields = [
            'name',
            'license',
            'description',
            'category',
            'mediafiles',
            'question',
            'recurse',
        ];
        foreach ($fields as $field) {
            if (!empty($configdata->$field)) {
                $mform->setDefault($field, $configdata->$field);
            }
        }
    }

    /**
     * Return helper to write question
     *
     * @param question $question question
     * @return stdClass
     */
    public function get_writer($question) {
        $context = \core\context\module::instance($this->cmid);
        $question->contextid = $context->id;
        if (empty($question->parent)) {
            foreach (['repurposeplus', 'repurpose'] as $plugin) {
                $writerclass = "contenttype_$plugin\\local\\qtype_" . $question->qtype;

                if (class_exists($writerclass) && empty($question->parent)) {
                    return new $writerclass($question);
                }
            }
        }
        return null;
    }

    /**
     * Turns question into an object structure for h5p content
     *
     * @param stdClass $question the question data.
     * @return stdClass data to add to content file object
     */
    public function write_question(stdClass $question): ?stdClass {
        if (!$writer = $this->get_writer($question)) {
            return null;
        }

        $content = new stdClass();
        $content = (object) [
            'params' => $writer->process($content),
            'subContentId' => $writer->create_subcontentid(),
            'library' => $writer->library,
            'metadata' => (object) [
                'license' => 'U',
                'authors' => [],
                'changes' => [],
                'title' => $question->name,
                'extraTitle' => $question->name,
            ],
        ];

        if (!empty($writer->files)) {
            if (empty($this->files)) {
                $this->files = [];
            }
            $this->files = $this->files + $writer->files;
        }

        return $content;
    }
}
