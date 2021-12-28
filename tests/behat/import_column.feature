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
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name             | template    |
      | Test questions   | multichoice | Multi-choice-001 | two_of_four |
    Given I log in as "admin"
    And I navigate to "H5P > Manage H5P content types" in site administration
    And I upload "contentbank/contenttype/repurpose/tests/fixtures/column-252.h5p" file to "H5P content type" filemanager
    And I wait until the page is ready
    And I click on "Upload H5P content types" "button" in the "#fitem_id_uploadlibraries" "css_element"
    And I wait until the page is ready
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I add a "Short answer" question filling the form with:
      | Question name        | shortanswer-001                           |
      | Question text        | What is the national langauge in France?  |
      | General feedback     | The national langauge in France is French |
      | Default mark         | 1                                         |
      | Case sensitivity     | No, case is unimportant                   |
      | id_answer_0          | French                                    |
      | id_fraction_0        | 100%                                      |
      | id_feedback_0        | Well done. French is correct.             |
      | id_answer_1          | *                                         |
      | id_fraction_1        | None                                      |
      | id_feedback_1        | Your answer is incorrect.                 |

  @javascript @contenttype_repurpose_column
  Scenario: Import a column
    When I am on the "C1" "contenttype_repurpose > column" page
    And I set the field "Category" to "Test questions"
    And I click on "Save" "button"
    And I switch to "h5p-player" class iframe
    And I switch to "h5p-iframe" class iframe
    Then I should see "What is the national langauge in France"
    And I should see "Which are the odd numbers"
