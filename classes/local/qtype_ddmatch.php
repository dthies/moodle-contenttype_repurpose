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
use contenttype_repurpose\local\qtype_multichoice;

/**
 * Question export helper for Repurpose content type
 *
 * @package    contenttype_repurpose
 * @copyright  2020 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddmatch extends qtype_match {
    /**
     * Rearrange the question data to expected H5P format
     *
     * @param stdClass $content
     * @return stdClass
     */
    public function process_question(stdClass $content): stdClass {
        $content->taskDescription = strip_tags(
            $this->question->questiontext,
            '<b><i><em><strong>'
        );

        $context = \context::instance_by_id($this->question->contextid);
        $content->textField = '';
        foreach ($this->question->options->subquestions as $subquestion) {
            $content->textField .= ' *' . strip_tags(
                $this->question->questiontext,
                '<b><i><em><strong>'
            );
            $content->textField .= '* ' . strip_tags(
                format_text($subquestion->questiontext, $subquestion->questiontextformat, ['context' => $context]),
                '<b><i><em><strong>'
            ) . "\n\n";
        }

        $content->background = $content->media ?? null;
        return $content;
    }
}
