<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/group/externallib.php');
require_once("$CFG->dirroot/enrol/mgae/locallib.php");

/**
 *
 * @package    enrol
 * @subpackage mgae
 * @copyright  2012 Mark van Hoek
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_mgae_plugin extends enrol_plugin {

    public function get_course_ids_having_groups() {
        global $DB;
        $query = 'SELECT DISTINCT courseid FROM mdl_groups';

        $results = array_values($DB->get_records_sql($query)); 
        $returnThis = array();
        foreach($results as $result) {
            $returnThis[] = $result->courseid;
        }
        
        //mtrace(__FUNCTION__.'::About to return $returnThis='.print_r($returnThis, true));
        return $returnThis;

    }
    
    public function get_main_rules() {
        $groupRuleArr_tpl = get_config('enrol_mgae', 'mainrule_fld');
        if (!empty($groupRuleArr_tpl)) {
            $delim = $this->get_delimiter();
            return explode($delim, $groupRuleArr_tpl);
        } else {
            return false; //Empty mainrule
        };
    }
    
    // Parse the main rules via the replacement logic and put each group rule into an array item
    public function get_replace_arr() {
        $delim = $this->get_delimiter();
        $repl_arr_tpl = get_config('enrol_mgae', 'replace_arr');
        $repl_arr = array();
        if (!empty($repl_arr_tpl)) {
            $repl_arr_pre = explode($delim, $repl_arr_tpl);
            foreach ($repl_arr_pre as $rap) {
                list($key, $val) = explode("|", $rap);
                $repl_arr[$key] = $val;
            };
        };
        return $repl_arr;
    }    
    
    // Get advanced user data
    public function get_user_data_advanced($user) {
        profile_load_data($user);
        profile_load_custom_fields($user);
        //mtrace('After profile_load_data: $user='.print_r($user, true));

        $secondrule_fld = get_config('enrol_mgae', 'secondrule_fld');
        $cust_arr = array();
        foreach ($user as $key => $val){
            if (is_array($val)) {
                $text = (isset($val['text'])) ? $val['text'] : '';
            } else {
                $text = $val;
            };

            // Raw custom profile fields
            $fld_key = preg_replace('/profile_field_/', 'profile_field_raw_', $key);
            $cust_arr["%$fld_key"] = ($text == '') ? format_string($secondrule_fld) : format_string($text);
        }; 

        // Custom profile field values
        foreach ($user->profile as $key => $val) {
            $cust_arr["%profile_field_$key"] = ($val == '') ? format_string($secondrule_fld) : format_string($val);
        };
        
        // Additional values for email
        list($email_username,$email_domain) = explode("@", $cust_arr['%email']);
        $cust_arr['%email_username'] = $email_username;
        $cust_arr['%email_domain'] = $email_domain;

        return $cust_arr;
    }
    
    public function get_course_students($courseId) {
        global $DB;
        $studentRole = get_archetype_roles('student');
        $studentRole = reset($studentRole);
        
        $context = get_context_instance(CONTEXT_COURSE, $courseId, MUST_EXIST);

        $query = 'select u.* from mdl_role_assignments as ra JOIN mdl_user as u ON ra.userid=u.id where contextid=' . $context->id . ' and roleid='.$studentRole->id;
        return $DB->get_records_sql($query); 
    }

    public function is_course_enrollable($courseId) {
//        global $DB;
//        
//        $course = $DB->get_record('course', array('id'=>$courseId));
//        mtrace('$course='.print_r($course, true));        
//        if (($course->enrolstartdate != 0 && $course->enrolstartdate > time()) ||
//        ($course->enrolenddate != 0 && $course->enrolenddate < time())) {
//            return false;
//        }
        return true;
    }
    
    public function get_course_groups($courseId) {
        return core_group_external::get_course_groups($courseId);
    }
    
    public function get_delimiter() {
        $delimiter = get_config('enrol_mgae', 'delim');
        return strtr($delimiter, array('CR+LF' => chr(13) . chr(10), 'CR' => chr(13), 'LF' => chr(10)));
    }
    
   /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function instance_deleteable($instance) {
        global $DB;

        if (!enrol_is_enabled('mgae')) {
            return true;
        }
        // allow delete only when no synced users here
        return !$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id));
    }
    
    public function can_add_new_instances($courseid) {
        global $DB;

        $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/mgae:config', $context)) {
            return false;
        }

        if ($DB->record_exists('enrol', array('courseid'=>$courseid, 'enrol'=>'mgae'))) {
            return false;
        }        
        
        return true;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        if (!$this->can_add_new_instances($courseid)) {
            return NULL;
        }

        // multiple instances not supported - filtered above in can_add_new_instances
        return new moodle_url('/enrol/mgae/addinstance.php', array('id'=>$courseid));
    }
    
    /**
     * Called for all enabled enrol plugins that returned true from is_cron_required().
     * @return void
     */
    public function cron() {
        global $CFG;

        if (!enrol_is_enabled('mgae')) {
            mtrace('The mgae enrolment method is disabled');
            return;
        }

        return enrol_mgae_sync();
    }

    /**
     * Course update hook.  Called after updating/inserting course.
     *
     * @param bool $inserted true if course just inserted
     * @param object $course
     * @param object $data form data
     * @return void
     */
    public function course_updated($inserted, $course, $data) {
        return $this->cron();
    }

    
    function enrol_mgae_do_sync($courseId) {
        return enrol_mgae_sync($courseId);
    }
    
//    /**
//     * Post enrolment hook
//     *
//     * @param object $user user object, later used for $USER
//     * @param string $username (with system magic quotes)
//     * @param string $password plain text password (with system magic quotes)
//     */
//    function enrol_mgae_sync(stdClass $instance) { //&$user, $username, $password) {
//	global $DB;
//
//        if ($DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))) {
//            return ob_get_clean();
//        }
//
//        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
//            return ob_get_clean();
//        }
//
//        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
//            return ob_get_clean();
//        }
//
//        $course = $DB->get_record('course', array('id'=>$instance->courseid));
//        $context = get_context_instance(CONTEXT_COURSE, $course->id);
//
//        $uid = $user->id;
//        // Ignore users from don't_touch list
//        $ignore = explode(",",$this->config->donttouchusers);
//
//        if (!empty($ignore) AND array_search($username, $ignore) !== false) {
//            $SESSION->mcautoenrolled = TRUE;
//            return true;
//        };
//
//        // Ignore guests
//        if ($uid < 2) {
//            $SESSION->mcautoenrolled = TRUE;
//            return true;
//        };
//        
//// ********************** Get COHORTS data
//        $clause = array('contextid'=>$context->id);
//        if ($this->config->enableunenrol == 1) {
//            $clause['component'] = 'enrol_mgae';
//        };
//
//        $cohorts = $DB->get_records('cohort', $clause);
//
//        $cohorts_list = array();
//        foreach($cohorts as $cohort) {
//            $cid = $cohort->id;
//	    $cname = format_string($cohort->name);
//            $cohorts_list[$cid] = $cname;
//        }
//    
//        // Get advanced user data
//        profile_load_data($user);
//        $cust_arr = array();
//        foreach ($user as $key => $val){
//            if (is_array($val)) {
//                $text = (isset($val['text'])) ? $val['text'] : '';
//            } else {
//                $text = $val;
//            };
//
//            // Raw custom profile fields
//            $fld_key = preg_replace('/profile_field_/', 'profile_field_raw_', $key);
//            $cust_arr["%$fld_key"] = ($text == '') ? format_string($this->config->secondrule_fld) : format_string($text);
//        }; 
//
//        // Custom profile field values
//        foreach ($user->profile as $key => $val) {
//            $cust_arr["%profile_field_$key"] = ($val == '') ? format_string($this->config->secondrule_fld) : format_string($val);
//        };
//
//        // Additional values for email
//        list($email_username,$email_domain) = explode("@", $cust_arr['%email']);
//        $cust_arr['%email_username'] = $email_username;
//        $cust_arr['%email_domain'] = $email_domain;
//
//        // Delimiter
//        $delimiter = $this->config->delim;
//        $delim = strtr($delimiter, array('CR+LF' => chr(13).chr(10), 'CR' => chr(13), 'LF' => chr(10)));
//
//        // Calculate a cohort names for user
//        $repl_arr_tpl = $this->config->replace_arr;
//
//        $repl_arr = array();
//        if (!empty($repl_arr_tpl)) {
//            $repl_arr_pre = explode($delim, $repl_arr_tpl);
//            foreach ($repl_arr_pre as $rap) {
//                list($key, $val) = explode("|", $rap);
//                $repl_arr[$key] = $val;
//            };
//        };
//
//        // Generate cohorts array
//        $cohorts_arr_tpl = $this->config->mainrule_fld;
//
//        $cohorts_arr = array();
//        if (!empty($cohorts_arr_tpl)) {
//            $cohorts_arr = explode($delim, $cohorts_arr_tpl);
//        } else {
//            $SESSION->mcautoenrolled = TRUE;
//            return; //Empty mainrule
//        };
//        
//        $processed = array();
//
//        foreach ($cohorts_arr as $cohort) {
//            $cohortname = strtr($cohort, $cust_arr);
//            $cohortname = (!empty($repl_arr)) ? strtr($cohortname, $repl_arr) : $cohortname;
//
//            if ($cohortname == '') {
//                continue; // We don't want an empty cohort name
//            };
//
//            $cid = array_search($cohortname, $cohorts_list);
//            if ($cid !== false) {
//
//                if (!$DB->record_exists('cohort_members', array('cohortid'=>$cid, 'userid'=>$user->id))) {
//                    cohort_add_member($cid, $user->id);
//                    add_to_log(SITEID, 'user', 'Added to cohort ID ' . $cid, "view.php?id=$user->id&course=".SITEID, $user->id, 0, $user->id);
//                } else {
//                    add_to_log(SITEID, 'user', 'Already exists in cohort ID ' . $cid, "view.php?id=$user->id&course=".SITEID, $user->id, 0, $user->id);
//                };
//            } else {
//                // Cohort not exist so create a new one
//                add_to_log(SITEID, 'user', 'Cohort not exist ID so screate a new one' , "view.php?id=$user->id&course=".SITEID, $user->id, 0, $user->id);
//                $newcohort = new stdClass();
//                $newcohort->name = $cohortname;
//                $newcohort->description = "created ". date("d-m-Y");
//                $newcohort->contextid = $context->id;
//                if ($this->config->enableunenrol == 1) {
//                    $newcohort->component = "enrol_mgae";
//                };
//                $cid = cohort_add_cohort($newcohort);
//                cohort_add_member($cid, $user->id);
//
//                add_to_log(SITEID, 'user', 'Added to cohort ID ' . $cid, "view.php?id=$user->id&course=".SITEID, $user->id, 0, $user->id);
//            };
//            $processed[] = $cid;
//        };
//        $SESSION->mcautoenrolled = TRUE;
//        
//        //Unenrol user
//        if ($this->config->enableunenrol == 1) {
//        //List of cohorts where this user enrolled
//            $sql = "SELECT c.id AS cid FROM {cohort} c JOIN {cohort_members} cm ON cm.cohortid = c.id WHERE c.component = 'enrol_mgae' AND cm.userid = $uid";
//            $enrolledcohorts = $DB->get_records_sql($sql);
//
//            foreach ($enrolledcohorts as $ec) {
//                if(array_search($ec->cid, $processed) === false) {
//                    cohort_remove_member($ec->cid, $uid);
//                    add_to_log(SITEID, 'user', 'Removed from cohort ID ' . $ec->cid, "view.php?id=$user->id&course=".SITEID, $user->id, 0, $user->id);
//                };
//            };
//        };
//
//    }
//
//}



/**
 * Indicates API features that the enrol plugin supports.
 *
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function enrol_mgae_supports($feature) {
    switch($feature) {
        case ENROL_RESTORE_TYPE: return ENROL_RESTORE_EXACT;

        default: return null;
    }
}
}