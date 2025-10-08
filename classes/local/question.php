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
use core_contentbank\form\edit_content;
use core_h5p\editor as h5peditor;
use core_h5p\factory;
use core_h5p\helper;
use context_user;
use context;
use stdClass;
use moodle_exception;
use moodle_form;
use moodle_url;
use question_bank;
use contenttype_repurpose\local\column;
use contenttype_repurpose\local\dialogcards;

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
class question extends dialogcards {
    /** @var $type Machine name for target type */
    public $library = '';

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

        $category = $DB->get_record('question_categories', ['id' => preg_replace('/,.*/', '', $data->category)]);
        $questions = get_questions_category($category, true);

        foreach ($questions as $question) {
            if (!empty($data->question) && $question->id == $data->question) {
                $content = $this->write_question($question);
                $this->library = $content->library;
                return $content;
            }
        }
        return null;
    }

    /**
     * Process data from an array of questions
     *
     * @param array $questions questions to process
     * @return stdClass The content file object
     */
    public function create_params(array $questions): stdClass {
        $content = json_decode('{
            "content": []
        }');

        foreach ($questions as $question) {
            $content->content[] = (object) [
                'content' => $this->write_question($question),
                'useSeparator' => 'auto',
            ];
        }

        return $content;
    }

    /**
     * Defines the additional form fields.
     *
     * @param moodle_form $mform form to modify
     */
    public function add_form_fields($mform) {
        global $DB, $PAGE;

        $PAGE->requires->js_call_amd(
            'contenttype_repurpose/formupdate', 'init',
            [$this->context->id, 'question', $mform->getElementValue('cmid')]
        );

        parent::add_form_fields($mform);
        $mform->removeElement('description');

        // Add question selector.
        $questions = [];
        $context = \core\context\module::instance($mform->getElementValue('cmid'));
        $category = $DB->get_record('question_categories', ['contextid' => $context->id, 'parent' => 0], '*', IGNORE_MULTIPLE);
        foreach (get_questions_category($category, true) as $question) {
            if (
                question_has_capability_on($question, 'use') &&
                $this->get_writer($question) &&
                $category->id == $question->category
            ) {
                $questions[$question->id] = $question->name;
            }
        }
        $mform->addElement('select', 'question', get_string('question'), $questions);
        $mform->setType('question', PARAM_INT);
        $mform->addRule('question', null, 'required', null, 'server');
        $mform->addElement('static', 'previewquestion', '', 'no question');
        $mform->addHelpButton('question', 'question', 'contenttype_repurpose');
        $mform->addHelpButton('previewquestion', 'previewquestion', 'contenttype_repurpose');
    }

    /**
     * Change definitiion after supplied data.
     *
     * @param moodle_form $mform form with data to be modified
     * @return void
     */
    public function definition_after_data($mform) {
        global $CFG, $DB, $OUTPUT;

        $question = $mform->getElement('question');
        if ($question && $mform->getElementValue('category')) {
            [$category, $contextid] = explode(',', reset($mform->getElementValue('category')));
            $category = reset($mform->getElementValue('category'));
            $category = $DB->get_record('question_categories', ['id' => preg_replace('/,.*/', '', $category)]);
            $questions = [];
            foreach (get_questions_category($category, true) as $question) {
                if (
                    question_has_capability_on($question, 'use') &&
                    $this->get_writer($question) &&
                    (!empty($mform->getElementValue('recurse')) || $category->id == $question->category)
                ) {
                    $questions[$question->id] = $question->name;
                }
            }
            $mform->removeElement('question');
            $question = $mform->createElement('select', 'question', get_string('question'), $questions);
            $mform->insertElementBefore($question, 'category');
            $mform->insertElementBefore($mform->removeElement('category'), 'question');
            $mform->insertElementBefore($mform->removeElement('recurse'), 'question');
            $mform->addRule('question', null, 'required', null, 'server');

            if (!empty($mform->getElementValue('question')) || $questions) {
                $value = array_merge($mform->getElementValue('question') ?? [], array_keys($questions));
                if (file_exists($CFG->dirroot . '/question/preview.php')) {
                    $url = new moodle_url('/question/preview.php', [
                        'id' => reset($value),
                    ]);
                } else {
                    $url = new moodle_url('/question/bank/previewquestion/preview.php', [
                        'id' => reset($value),
                    ]);
                }
                $mform->removeElement('previewquestion');
                $mform->insertElementBefore(
                    $mform->createElement('static', 'previewquestion', '', $OUTPUT->render_from_template(
                        'contenttype_repurpose/previewquestion',
                        ['url' => $url->out()]
                    )),
                    'question'
                );
                $mform->insertElementBefore(
                    $mform->removeElement('question'),
                    'previewquestion'
                );
            }
            $mform->addHelpButton('question', 'question', 'contenttype_repurpose');
            $mform->addHelpButton('previewquestion', 'previewquestion', 'contenttype_repurpose');
        } else {
            parent::definition_after_data($mform);
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
        $errors = [];
        if (empty($data['submitbutton'])) {
            $errors['submitbutton'] = 'no submit';
        }
        return $errors;
    }
}
