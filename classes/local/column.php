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
use core_contentbank\form\edit_content;
use core_h5p\editor as h5peditor;
use core_h5p\factory;
use core_h5p\helper;
use contenttype_repurpose\local\dialogcards;
use context_user;
use context;
use stdClass;
use moodle_exception;
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
class column extends dialogcards {

    /** @var $type Machine name for target type */
    public $library = 'H5P.Column 1.12';

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
            $question->type = 'fillblanks';
            $content->content[] = (object) array(
                'content' => $this->write_question($question),
                'useSeparator' => 'auto',
            );
        }

        return $content;
    }

    /**
     * Turns question into an object structure for h5p content
     *
     * @param stdClass $question the question data.
     * @return stdClass data to add to content file object
     */
    public function write_question(stdClass $question): ?stdClass {
        global $CFG, $OUTPUT;

        $writerclass = 'contenttype_repurpose\\local\\qtype_' . $question->qtype;

        $question->contextid = $this->context->id;
        if (!class_exists($writerclass) || !empty($question->parent)) {
            return null;
        }
        $writer = new $writerclass($question);

        $content = new stdClass();
        $content = (object) array(
            'params' => $writer->process($content),
            'subContentId' => $writer->create_subcontentid(),
            'library' => $writer->library,
            'metadata' => (object) array(
                'license' => 'U',
                'authors' => [],
                'changes' => [],
                'title' => $question->name,
                'extraTitle' => $question->name,
            ),
        );

        if (!empty($writer->files)) {
            if (empty($this->files)) {
                $this->files = array();
            }
            $this->files = $this->files + $writer->files;
        }

        return $content;
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
                && class_exists('\\contenttype_repurpose\\local\\qtype_' . $question->qtype)
                && (key_exists('recurse', $data) || $question->category == $category->id);
        }))) {
            $errors['category'] = get_string('noquestionsselected', 'contenttype_repurpose');
        }

        return $errors;
    }

    /**
     * Defines the additional form fields.
     *
     * @param moodle_form $mform form to modify
     */
    public function add_form_fields($mform) {
        parent::add_form_fields($mform);

        $mform->removeElement('description');
    }
}
