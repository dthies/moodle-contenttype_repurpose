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
 * @license    http://www.gnu.org/copyleft/gpl.repurpose GNU GPL v3 or later
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
 * @license   http://www.gnu.org/copyleft/gpl.repurpose GNU GPL v3 or later
 */
class dialogcards {

    /** @var $context Current course context for content bank */
    public $context = null;

    /** @var $files files to include with content */
    public $files = null;

    /** @var $type Machine name for target type */
    public $library = 'H5P.Dialogcards';

    /**
     * Constructor
     *
     * @param context $context Course context
     */
    public function __construct(context $context) {
        $this->context = $context;
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

        $url = new moodle_url('/question/edit.php', array(
            'courseid' => ($this->context->contextlevel != CONTEXT_COURSE) ? SITEID : $this->context->instanceid,
        ));
        $mform->addElement(
            'static',
            'questionbank', '',
            $OUTPUT->render_from_template('contenttype_repurpose/questionbanklink', array(
                'url' => $url->out(),
            ))
        );
        $contexts = new \question_edit_contexts($this->context);
        $mform->addElement('questioncategory', 'category', get_string('category', 'question'),
                array('contexts' => $contexts->having_cap('moodle/question:useall'), 'top' => true));

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

        $context = context::instance_by_id($data->contextid, MUST_EXIST);

        $category = $DB->get_record('question_categories', array('id' => preg_replace('/,.*/', '', $data->category)));

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
        foreach ($questions as $question) {
            if ($question->qtype == 'shortanswer' && empty($question->parent)) {
                $answers = array_column($question->options->answers, 'fraction', 'answer');
                arsort($answers);

                $dialog = (object) array(
                    'text' => strip_tags($question->questiontext, '<b><i><em><strong>'),
                    'answer' => array_keys($answers)[0],
                    'subContentId' => $this->create_subcontentid(),
                );

                if ($files = $fs->get_area_files($this->context->id, 'question', 'questiontext', $question->id)) {
                    if (empty($this->files)) {
                        $this->files = array();
                    }

                    foreach ($files as $f) {
                        if ($f->is_valid_image()) {
                            $imageinfo = $f->get_imageinfo();
                            $filename = $this->getname('image', $f->get_filename());
                            $this->files['content/images/' . $filename] = $f;
                            $dialog->image = (object) array(
                                'path' => 'images/' .  $filename,
                                'mimetype' => $imageinfo['mimetype'],
                                'height' => $imageinfo['height'],
                                'width' => $imageinfo['width'],
                            );
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
        $string = array_map(function() {
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

        $matches = array();
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

        $errors = array();
        $category = $DB->get_record('question_categories', array(
            'id' => preg_replace('/,.*/', '', $data['category']),
        ));

        $questions = get_questions_category($category, !empty($data->recurse));
        if (empty(array_filter($questions, function($question) use ($category, $data) {
            return empty($question->parent)
                && ($question->qtype === 'shortanswer')
                && (!empty($data['recurse']) || $question->category == $category->id);
        }))) {
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
}
