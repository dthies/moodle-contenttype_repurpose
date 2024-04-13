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
class qtype_shortanswer extends qtype_multichoice {
    /** @var $type Machine name for target type */
    public $library = 'H5P.GuessTheAnswer 1.4';

    /**
     * Rearrange the question data to expected H5P format
     *
     * @param stdClass $content
     * @return stdClass
     */
    public function process_question(stdClass $content): stdClass {
        if (isset($this->question->type) && $this->question->type === 'fillblanks') {
            return $this->create_fillblanks($content);
        }
        return $this->create_guesstheanswer($content);
    }

    /**
     * Rearrange the question data to H5P format for Fill in the Blanks content type
     *
     * @param stdClass $content
     * @return stdClass
     */
    private function create_fillblanks(stdClass $content): stdClass {
        $this->library = 'H5P.Blanks 1.12';
        $this->type = 'fillblanks';

        $answers = [];
        foreach ($this->question->options->answers as $answer) {
            if ($answer->fraction == 1.0) {
                $answers[] = $answer->answer;
            }
        }

        $content->text = $this->question->questiontext;
        $content->questions = ['*' . implode('/', $answers) . '*'];

        return $content;
    }

    /**
     * Rearrange the question data to H5P format for Guess the Answer content type
     *
     * @param stdClass $content
     * @return stdClass
     */
    private function create_guesstheanswer(stdClass $content): stdClass {

        $content->taskDescription = $this->question->questiontext;

        foreach ($this->question->options->answers as $answer) {
            if ($answer->fraction == 1.0) {
                $content->solutionText = $answer->answer;
            }
        }

        if (!empty($content->media->type)) {
            $content->media = $content->media->type;
        }
        return $content;
    }
}
