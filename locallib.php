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
 * Local stuff for category enrolment plugin.
 *
 * @package    enrol
 * @subpackage mgae
 * @copyright  2012 Mark van Hoek
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/user/profile/lib.php');

function enrol_mgae_sync($courseId=NULL) {
    global $DB;
    $plugin = enrol_get_plugin('mgae');
    
    //TODO: If $courseId is not defined, get a list of all courses 
    //with groups and process each one
    if(empty($courseId)) {
        mtrace('Enrol mgae called with no courseId');
        $courses = $plugin->get_course_ids_having_groups();
        foreach($courses as $courseId) {
            enrol_mgae_sync($courseId);
        }
        return true;
    }
    mtrace('Enrol mgae called for courseId='.print_r($courseId, true));

    //Check some basics about this course to see if we should bother doing this
    if(!$plugin->is_course_enrollable($courseId)) {
        mtrace("Enrol mgae skipping courseId=$courseId due to is_course_enrollable()");
        return true;
    }
    
    // Delimiter
    $delim = $plugin->get_delimiter();

    // Get the config'd groups to sync.  
    // Parse the main rules via the replacement logic and put each group rule into an array item
    $repl_arr_tpl = get_config('enrol_mgae', 'replace_arr');
    $repl_arr = array();
    if (!empty($repl_arr_tpl)) {
        $repl_arr_pre = explode($delim, $repl_arr_tpl);
        foreach ($repl_arr_pre as $rap) {
            list($key, $val) = explode("|", $rap);
            $repl_arr[$key] = $val;
        };
    };

    $groupRuleArr_tpl = get_config('enrol_mgae', 'mainrule_fld');
    //mtrace('get_config says: '.print_r(get_config('enrol_mgae', 'mainrule_fld'), true));
    $groupRuleArr = array();
    if (!empty($groupRuleArr_tpl)) {
        $groupRuleArr = explode($delim, $groupRuleArr_tpl);
    } else {
        //$SESSION->mgautoenrolled = TRUE;
        mtrace("Enrol mgae skipping courseId=$courseId due to empty(\$groupRuleArr_tpl)");
        return true; //Empty mainrule
    };

    //mtrace(__LINE__.'::$groupRuleArr='.print_r($groupRuleArr, true));
    
    // Get advanced user data
    //get_record($table, array $conditions, $fields='*', $strictness=IGNORE_MISSING) {
    $user = $DB->get_record('user', array('username'=>'guest'), '*', IGNORE_MULTIPLE);
    
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

    
    foreach ($groupRuleArr as $groupRule) {
        //if a group with a matching name does not exist in this course, skip it
        $groupsInCourse = groups_get_all_groups($courseId);
        if(empty($groupsInCourse)) continue;
        //mtrace(__LINE__.'::$groupname='.$groupname);    

        //get the course users
        $plugin->get_course_students($courseId);
        
        //check if any users have a profile field with a name matching the main rule
        //
        //get the current group name from the user profile
        $groupname = strtr($groupRule, $cust_arr);
        mtrace("Enrol mgae says \$groupname=$groupname for \$groupRule=$groupRule; \$cust_arr=".print_r($cust_arr, true));
        $groupname = (!empty($repl_arr)) ? strtr($groupname, $repl_arr) : $groupname;
        mtrace("Enrol mgae says second version of \$groupname=$groupname for \$groupRule=$groupRule; \$cust_arr=".print_r($cust_arr, true));

        if ($groupname == '') {
            mtrace("Enrol mgae skipping courseId=$courseId due to empty group name");
            continue; // We don't want an empty group name
        };

        mtrace(__LINE__.'::$groupname='.$groupname);    

        
        
        //if the user is already enrolled in the matching group, skip it
        
        //check the group actually exists
//        $cid = array_search($cohortname, $cohorts_list);
//        if ($cid !== false) {
//
//        }
    }
}