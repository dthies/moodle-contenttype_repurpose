@contenttype @contenttype_repurpose @_file_upload
Feature: Import column into content bank as H5P
  As a teacher
  In order to use my questions in interactive content
  I need to import them as H5P column content types

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | T1        | Teacher1 | teacher1@moodle.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity   | name    | intro           | course | idnumber |
      | qbank      | Qbank 1 | Question bank 1 | C1     | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference     | questioncategory    | name                |
      | Activity module | qbank1        | Top                 | top                 |
      | Activity module | qbank1        | top                 | Default for Qbank 1 |
      | Activity module | qbank1        | Default for Qbank 1 | Subcategory         |
    And the following "questions" exist:
      | questioncategory    | qtype       | name             | template    |
      | Default for Qbank 1 | multichoice | Multi-choice-001 | two_of_four |
    And the following "questions" exist:
      | questioncategory    | qtype       | name | questiontext                            | answer 1 | grade |
      | Default for Qbank 1 | shortanswer | SA1  | What is the national langauge in France | French   | 100%  |
    Given I log in as "admin"
    And I navigate to "H5P > Manage H5P content types" in site administration
    And I upload "contentbank/contenttype/repurpose/tests/fixtures/column-252.h5p" file to "H5P content type" filemanager
    And I wait until the page is ready
    And I click on "Upload H5P content types" "button" in the "#fitem_id_uploadlibraries" "css_element"
    And I wait until the page is ready
    And I log out
    And I log in as "teacher1"

  @javascript @contenttype_repurpose_column
  Scenario: Import a column
    When I am on the "C1" "contenttype_repurpose > column" page
    And I set the field "Category" to "Default for Qbank 1"
    And I click on "Save" "button"
    And I switch to "h5p-player" class iframe
    And I switch to "h5p-iframe" class iframe
    Then I should see "What is the national langauge in France"
    And I should see "Which are the odd numbers"
