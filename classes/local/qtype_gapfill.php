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

namespace contenttype_repurpose\local;

use stdClass;
use context_user;

/**
 * Question import helper for Gapfill question
 *
 * @package    contenttype_repurpose
 * @copyright  2025 onward Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_gapfill extends qtype_multichoice {
    /** @var $type Machine name for target type */
    public $library = 'H5P.DragText 1.10';

    /**
     * Rearrange the question data to expected H5P format
     *
     * @param stdClass $content
     * @return stdClass
     */
    public function process_question(stdClass $content): stdClass {

        $content->taskDescription = '';
        $content->textField = preg_replace('/\\[(.+?)\\]/', '*$1*', strip_tags($this->question->questiontext));
        preg_match_all('/\\[(.+?)\\]/', strip_tags($this->question->questiontext), $matches);
        if ($distractors = array_diff(array_column($this->question->options->answers, 'answer'), $matches[1])) {
            $content->distractors = '*' . implode('*,*', $distractors) . '*';
        }

        return $content;
    }
}
