<?php
// This file is part of The Bootstrap 3 Moodle theme
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
 * GradeSend view grades on integration.
 *
 * @package    block_gradesend
 * @author     2019 Richard Oelmann
 * @copyright  2019 R. Oelmann

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Ref: http://docs.moodle.org/dev/Page_API.

defined('MOODLE_INTERNAL') || die();

class block_gradesend extends block_base {
    public function init() {
        // Get the block title. No other initialisation needed.
        $this->title = get_string('blocktitle', 'block_gradesend');
    }

    public function applicable_formats() {
        // Where is the block visible - course-view and site-index only.
        // Site-index is needed to be able to add as sticky block.
        return array('all' => false, 'course-view' => true, 'site-index' => true);
    }

    public function instance_allow_multiple() {
        // Hopefully obvious.
        return false;
    }

    public function has_config() {
        // Hopefully obvious.
        return false;
    }

    public function hide_header() {
        // Hopefully obvious.
        return false;
    }

    public function get_content() {
        // The bit that does all the work!
        // Apply global variables so they can be used in the function.
        global $COURSE, $PAGE, $CFG, $DB;
        require_once("$CFG->libdir/gradelib.php");
        require_once("$CFG->dirroot/local/extdb/classes/task/extdb.php");
        // requires_js('/blocks/gradesend/block_gradesend.js');

        // Fetch settings for external DB plugin.
        $externaldb = new \local_extdb\extdb();
        $name = $externaldb->get_name();

        $externaldbtype = $externaldb->get_config('dbtype');
        $externaldbhost = $externaldb->get_config('dbhost');
        $externaldbname = $externaldb->get_config('dbname');
        $externaldbencoding = $externaldb->get_config('dbencoding');
        $externaldbsetupsql = $externaldb->get_config('dbsetupsql');
        $externaldbsybasequoting = $externaldb->get_config('dbsybasequoting');
        $externaldbdebugdb = $externaldb->get_config('dbdebugdb');
        $externaldbuser = $externaldb->get_config('dbuser');
        $externaldbpassword = $externaldb->get_config('dbpass');
        $tablegrades = get_string('stuassesstable', 'block_gradesend');

        // Database connection and setup checks.
        // Check connection and label Db/Table in cron output for debugging if required.
        if (!$externaldbtype) {
            echo 'Database not defined.<br>';
            return 0;
        }
        // Check remote student grades table - usr_data_student_assessments.
        if (!$tablegrades) {
            echo 'Student Grades Table not defined.<br>';
            return 0;
        }

        // Report connection error if occurs.
        if (!$extdb = $externaldb->db_init(
            $externaldbtype,
            $externaldbhost,
            $externaldbuser,
            $externaldbpassword,
            $externaldbname)) {
            echo 'Error while communicating with external database <br>';
            return 1;
        }

        // Determine when to display block.
        // Get course and system context.
        $context = context_course::instance($COURSE->id);
        $systemcontext = context_system::instance();
        // Reset variables.
        $cm = $PAGE->cm;
        $confirm = $aid = $acd = $test = 0;
        $crs = $inst = $mod = $assmentid = $assmentcode = null;

        // If user cannot grade assignments in that course then false (send no content).
        if (!has_capability('mod/assign:grade', $context)) {
            return false;
        }
        // If page isn't an assignment page then false (send no content).
        // This either needs to be commented out when adding as a sticky block, or
        // preferably put a further conditional so will display if user = admin.
        $pageurl = $PAGE->url;
        if (!has_capability('moodle/site:config', $systemcontext)) {
            // If page url is not (assign/view AND action=grading) AND page url is not mod/quiz => return false.
            /* @Steve - The quiz restriction could be narrowed to only quiz report pages as with action=grading,
             * but not sure staff are aware of those. */
            if (!(strpos($pageurl, 'assign/view') > 1 && strpos($pageurl, 'action=grading') > 1) &&
                !(strpos($pageurl, 'mod/quiz') > 1) ) {
                    return false;
            }
        } else { // OR is not siteadmin and page is site home.
            if (!(strpos($pageurl, 'assign/view') > 1 && strpos($pageurl, 'action=grading') > 1) &&
                !(strpos($pageurl, 'mod/quiz') > 1) && strpos($pageurl, '?redirect=0') == 0) {
                    return false;
            }
        }
        // Hide if $cm has no idnumber - prevents display when assignment or quiz is not linked to SITS.
        if (empty($cm->idnumber)) {
            return false;
        }
        // Check if form sent.
        if (isset($_POST["confirm"]) && $_POST["confirm"] == 1 ) {
            $confirm = 1;
        }
        if (isset($_POST["assmentid"])) {
            $aid = 1;
        }
        if (isset($_POST["assmentcode"])) {
            $acd = 1;
        }
        $test = $confirm + $aid + $acd;

        // Only process further if form sent. Otherwise jump direct to displaying block.
        if ($test == 3) {
            // Collect all form variables.
            if (isset($_POST["course"])) {
                $crs = $_POST["course"];
            }
            if (isset($_POST["instance"])) {
                $inst = $_POST["instance"];
            }
            if (isset($_POST["module"])) {
                $mod = $_POST["module"];
            }
            if (isset($_POST["assmentid"])) {
                $assmentid = $_POST["assmentid"];
            }
            if (isset($_POST["assmentcode"])) {
                $assmentcode = $_POST["assmentcode"];
            }
            // Module name required to ensure differentiation between mod_assign and mod_quiz.
            // Note: other assessable activites may need adding in the future, if used.
            $modname = $DB->get_field('modules', 'name', array('id' => $mod)); // Module name.

            // Fetch system wide grade letters.
            $gradeletters = array();
            $gradeletters = $DB->get_records_menu('grade_letters',
                                    array('contextid' => $systemcontext->id), 'letter', 'letter, lowerboundary');

            // Fetch assignment scale.
            $fullscale = $scale = array(); // Clear any prior value.
            $graderaw = $grademax = null;
            $gradeitem = $DB->get_record('grade_items', array('iteminstance' => $inst, 'itemmodule' => $modname));
            $giid = $gradeitem->id; // Grade item instance.
            $gscaleid = $gradeitem->scaleid;
            if (!is_null($gscaleid && $gscaleid !== 0)) {
                $fullscale = $DB->get_record('scale', array('id' => $gscaleid));
                $scale = explode(',', $fullscale->scale);
            }
            $grademax = $gradeitem->grademax;
            $grademin = $gradeitem->grademin;

            $stuassess = $sa = array(); // Maintain copy as per Integrations Db for writing back.
            // Read assessment data from external table and create $stuassess array.
            $sql = $externaldb->db_get_sql($tablegrades, array('assessment_idcode' => $assmentcode), array(), true);
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($fields = $rs->FetchRow()) {
                        $fields = array_change_key_case($fields, CASE_LOWER);
                        $fields = $externaldb->db_decode($fields);
                        $stuassess[] = $fields;
                    }
                }
                $rs->Close();
            } else {
                // Report error if required.
                $extdb->Close();
                echo 'Error reading data from the external course table<br>';
                return 4;
            }

            // Loop through each student in $sa array.
            foreach ($stuassess as $sa) {
                $stuassessinternal = array(); // Processing copy to be able to add additional fields.

                // Create key.
                $idnumber = $sa['student_code'];
                while (strlen($idnumber) < 7) {
                    $idnumber = '0' . $idnumber;
                }
                if (strlen($idnumber) != 7 ) {
                    echo 'Not 7 char: ' . $idnumber;
                }
                $idnumber = 's' . $idnumber;

                $stuassessinternal['username'] = $idnumber; // Username - not strictly needed for code, but useful for debugging.
                
                // Assign_grades is showing -1 as grader and grade where a piece of work has been submitted but not marked.
                if ($stuassessinternal[$key]['gradenum'] == -1) {
                    $stuassessinternal[$key]['gradenum'] = null;
                }
                if ($stuassessinternal[$key]['finalgrade'] == -1) {
                    $stuassessinternal[$key]['finalgrade'] = null;
                }
                
                // Get the students Moodle user->id.
                if ($DB->get_field('user', 'id', array('username' => $stuassessinternal['username']))) {
                    $uid = $DB->get_field('user', 'id', array('username' => $stuassessinternal['username'])); // User id.
                } else {
                    $uid = null;
                }
                $stuassessinternal['uid'] = $uid; // Student user id for writing.
                $stuassessinternal['lc'] = $assmentcode; // Assessment linkcode for writing.

                // Get submission received date & time.
                if ($modname == "assign" && $DB->record_exists('assign_submission',
                    array('assignment' => $inst, 'userid' => $uid, 'status' => 'submitted'))) {
                        $received = $DB->get_field('assign_submission', 'timemodified',
                                            array('assignment' => $inst, 'userid' => $uid));
                } else if ($modname == "quiz" &&
                            $DB->record_exists('quiz_attempts',
                                    array('quiz' => $inst, 'userid' => $uid, 'state' => 'finished'))) {
                    $received = $DB->get_field('quiz_attempts', 'timefinish', array('quiz' => $inst, 'userid' => $uid));
                } else {
                    $received = null;
                }
                // Convert UNIX to date:Time.
                if (!empty($received)) {
                    $stuassessinternal['received_date'] = date('Y-m-d', $received);
                    $stuassessinternal['received_time'] = date('H:i:s', $received);
                    $stuassessinternal['received_flag'] = '1';
                } else {
                    $stuassessinternal['received_date'] = null;
                    $stuassessinternal['received_time'] = null;
                }

                // Fetch numeric grade.
                if ($modname == 'assign' &&
                    $DB->record_exists('assign_grades', array('assignment' => $inst, 'userid' => $uid)) &&
                    !is_null($DB->get_field('assign_grades', 'grade', array('assignment' => $inst, 'userid' => $uid)))) {
                        $grade = $DB->get_record('assign_grades', array('assignment' => $inst, 'userid' => $uid));
                        $numgrade = $grade->grade;
                        $fbtimestamp = $grade->timemodified;
                } else if ($modname == 'quiz' &&
                            $DB->record_exists('quiz_grades', array('quiz' => $inst, 'userid' => $uid)) &&
                            !is_null($DB->get_field('quiz_grades', 'grade', array('quiz' => $inst, 'userid' => $uid)))) {
                                $grade = $DB->get_record('quiz_grades', array('quiz' => $inst, 'userid' => $uid));
                                $numgrade = $grade->grade;
                                $fbtimestamp = $grade->timemodified;
                } else {
                    $numgrade = null;
                    $fbtimestamp = null;
                }
                // Assign_grades is showing -1 as grader and grade where a piece of work has been submitted but not marked.
                if ($numgrade == -1) {
                    $numgrade = null;
                }

                if (!empty($numgrade)) {
                    // Make gradenum as a percentage.
                    $stuassessinternal['gradenum'] = $numgrade / ($grademax - $grademin) * 100;
                    $stuassessinternal['gradeletter'] = null;

                    // If grade item has scale.
                    if (!empty($gscaleid)) {
                        // Trim scale to max 2 chrs, remove spaces.
                        $stuassessinternal['gradeletter'] = trim(substr($scale[$numgrade - 1], 0, 2));
                        // If a scale grade is set, remove numeric value.
                        $stuassessinternal['gradenum'] = null;
                    } else {
                        // Allocate letter grade. Loop to find highest letter where mark is above lower boundary.
                        foreach ($gradeletters as $l => $g) {
                            if ($stuassessinternal['gradeletter'] == null &&
                                $stuassessinternal['gradenum'] >= $gradeletters[$l]) {
                                    $stuassessinternal['gradeletter'] = $l;
                            }
                        }
                    }
                } else {
                    $stuassessinternal['gradenum'] = null;
                    $stuassessinternal['gradeletter'] = null;
                }

                // Get assessment flags. eg. SB.
                $asflag = null;
                unset($asflagresult);
                unset($af);
                $asflagresult = $af = array();
                if (strlen($inst) > 0 && strlen($uid) > 0) {
                    $afsql = "SELECT c.content FROM {comments} c
                            JOIN {assign_submission} sub ON sub.id = c.itemid
                            WHERE sub.assignment = ".$inst." AND sub.userid = ".$uid."
                            AND c.commentarea = 'submission_assessmentflags'";
                    $asflagresult = $DB->get_records_sql($afsql);
                    // Loop to just leave the last comment.
                    foreach ($asflagresult as $af) {
                        $asflag = $af->content;
                    }
                    // If flag = SB/N set grade number to 0.
                    if ($asflag == 'SB' || $asflag == 'N' || $asflag == 'F' || $asflag == 'X' || $asflag == 'L') {
                        $stuassessinternal['gradenum'] = 0;
                    }
                    // If flag = SB/N/F (manual), set gradeletter to be flag.
                    if ($asflag == 'SB' || $asflag == 'N' || $asflag == 'F' || $asflag == 'X' || $asflag == 'L') {
                        $stuassessinternal['gradeletter'] = $asflag;
                    }
                    // If flag has 0 added, remove it and just send the N/F.
                    if ($asflag == '0N') {
                        $stuassessinternal['gradenum'] = 0;
                        $stuassessinternal['gradeletter'] = 'N';
                    }
                    if ($asflag == '0F') {
                        $stuassessinternal['gradenum'] = 0;
                        $stuassessinternal['gradeletter'] = 'F';
                    }
                    // Other comments are ignored - they are not wiped from Moodle, but are not sent to Integrations.
                }

                // Get feedback given date and time from UNIX.
                if (!empty($fbtimestamp)) {
                    $stuassessinternal['student_fbset_date'] = date('Y-m-d', $fbtimestamp);
                    $stuassessinternal['student_fbset_time'] = date('H:i:s', $fbtimestamp);
                } else {
                    $stuassessinternal['student_fbset_date'] = null;
                    $stuassessinternal['student_fbset_time'] = null;
                }
                $stuassessinternal['assessment_changebymoodle'] = '1';

                // Write values to external database - but only if they exist and have changed.
                // Set base line of SQL string.
                $sql = "UPDATE " . $tablegrades . " SET ";
                $changeflag = 0;
                // Create additional SQL for each value.
                if ($stuassessinternal['received_date'] !== $sa['received_date']) {
                    $sql .= "received_date = '" . $stuassessinternal['received_date'] . "', ";
                    $sql .= "received_flag = 1, ";
                    $changeflag = 1;
                }
                if ($stuassessinternal['received_time'] !== $sa['received_time']) {
                    $sql .= "received_time = '" . $stuassessinternal['received_time'] . "', ";
                    $sql .= "received_flag = 1, ";
                    $changeflag = 1;
                }
                if ($stuassessinternal['gradenum'] !== $sa['actual_mark']) {
                    $sql .= "actual_mark = '" . $stuassessinternal['gradenum'] . "', ";
                    $changeflag = 1;
                }
                if ($stuassessinternal['gradeletter'] !== $sa['actual_grade']) {
                    $sql .= "actual_grade = '" . $stuassessinternal['gradeletter'] . "', ";
                    $changeflag = 1;
                }
                if ($stuassessinternal['student_fbset_date'] !== $sa['student_fbset_date']) {
                    $sql .= "student_fbset_date = '" . $stuassessinternal['student_fbset_date'] . "', ";
                    $changeflag = 1;
                }
                if ($stuassessinternal['fbgiven_time'] !== $sa['student_fbset_time']) {
                    $sql .= "student_fbset_time = '" . $stuassessinternal['fbgiven_time'] . "', ";
                    $changeflag = 1;
                }

                $sql .= "assessment_changebymoodle = " . $changeflag ." WHERE ";
                $sql .= "assessment_idcode = '" . $assmentcode . "' AND student_code = '" . $sa['student_code'] . "';";

                // Execute SQL only if something has been changed.
                if ($changeflag > 0) {
                    $extdb->Execute($sql);
                }

            } // End ForEach

            $extdb->Close();
            unset($_POST);
            /* Comment out for development work until the write phase is written, or for debugging,
             * or you wont see any output to check what's happening. */
            header("Location: " . $_SERVER['PHP_SELF'].'?id='.$cm->id);
        }

        // Set up class to hold content.
        $this->content = new stdClass;
        // Make sure content->text is empty to start.
        $this->content->text = null;
        // Add content->header.
        $this->content->header = get_string('blocktitle', 'block_gradesend');

        // Create content of block.
        $this->content->text .= '<div class="gradesend">';
            $this->content->text .= '<div class="row">';
                $this->content->text .= '<div class="col-12">';
                    $this->content->text .= '<p>'.get_string('instructions', 'block_gradesend').'</p>';
                    $this->content->text .= '<p>'.'<a href="https://moodle.glos.ac.uk/moodle/pluginfile.php/1125930/mod_resource/content/2/Grade%20definitions%20-%20Assessment%20elements.pdf" target="_blank">Assessment Flags and Grade Definitions</a>' .'</p>';
                // $this->content->text .= '</div>';
                // $this->content->text .= '<div class="col-6">';
                    $this->content->text .= '<form action = "" method="post">';
                        $this->content->text .= '<input type="hidden" name="confirm" value="1" id="send-grades-flag">';
                        // $this->content->text .= get_string('confirm', 'block_gradesend').'<br>';
                        $this->content->text .= '<input type="hidden" name="assmentid" value="'.$cm->id.'"/>';
                        $this->content->text .= '<input type="hidden" name="assmentcode" value="'.$cm->idnumber.'"/>';
                        $this->content->text .= '<input type="hidden" name="course" value="'.$cm->course.'"/>';
                        $this->content->text .= '<input type="hidden" name="instance" value="'.$cm->instance.'"/>';
                        $this->content->text .= '<input type="hidden" name="module" value="'.$cm->module.'"/>';
                        $this->content->text .= '<button type="submit" class="btn btn-primary" id="send-grades-button">';
                        // $this->content->text .= '<button type="submit" class="btn btn-primary" disabled="disabled" id="send-grades-button">';
                        $this->content->text .= get_string('send', 'block_gradesend').'</button>';
                    $this->content->text .= '</form>';
/*                     $this->content->text .= '<br><br>';
                    $viewurl = $CFG->wwwroot.'/blocks/gradesend/pages/displayintegrations.php';
                    $this->content->text .= '<form action = "'.$viewurl.'" method="post">';
                        $this->content->text .= '<input type="hidden" name="assmentcode" value="'.$cm->idnumber.'"/>';
                        $this->content->text .= '<button type="submit" class="btn btn-primary">';
                        $this->content->text .= get_string('view', 'block_gradesend').'</button>';
                    $this->content->text .= '</form>';
 */
                $this->content->text .= '</div>';
            $this->content->text .= '</div>';
        $this->content->text .= '</div>';

        // $this->content->footer .= '<script src="' . $CFG->wwwroot . '/blocks/gradesend/block_gradesend.js"></script>';

        $this->content->footer = null;
        return $this->content;
    }
}
