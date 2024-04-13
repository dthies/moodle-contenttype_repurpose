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
 * Question Import for H5P Quiz content type
 *
 * @package    contenttype_repurpose
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace contenttype_repurpose\local;

use stdClass;
use context_user;

/**
 * Question export helper for H5P Quiz content type
 *
 * @copyright  2020 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_truefalse extends qtype_multichoice {
    /** @var $type Maching name for target type */
    public $library = 'H5P.TrueFalse 1.6';

    /**
     * Rearrange the question data to expected H5P format
     *
     * @param stdClass $content
     * @return stdClass
     */
    public function process_question(stdClass $content): stdClass {

        $content->question = $this->question->questiontext;
        $content->behaviour = new stdClass();

        foreach ($this->question->options->answers as $answer) {
            if ($answer->fraction == 1) {
                if ($answer->answer == "True") {
                    $content->correct = "true";
                } else {
                    $content->correct = "false";
                }
                $content->behaviour->feedbackOnCorrect   = strip_tags($answer->feedback);
            } else {
                $content->behaviour->feedbackOnWrong  = strip_tags($answer->feedback);
            }
        }

        return $content;
    }
}
