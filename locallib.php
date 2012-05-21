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
    
    //If $courseId is not defined, get a list of all courses 
    //with groups and process each one
    if(empty($courseId)) {
        //mtrace('Enrol mgae called with no courseId');
        $courses = $plugin->get_course_ids_having_groups();
        foreach($courses as $courseId) {
            enrol_mgae_sync($courseId);
        }
        //don't fall out of the loop and process the first/last item again
        return true;
    }
    //mtrace('Enrol mgae called for courseId='.print_r($courseId, true));

    //data integrity check
    if(!is_numeric($courseId)) {
        //mtrace("Enrol mgae skipping courseId=$courseId must be numeric");
        return false;
    }
    
    //Check some basics about this course to see if we should bother doing this
    if(!$plugin->is_course_enrollable($courseId)) {
        //mtrace("Enrol mgae skipping courseId=$courseId due to is_course_enrollable()");
        return true;
    }
    
    $groupsInCourse = $plugin->get_course_groups($courseId);
    if(empty($groupsInCourse)) {
        //mtrace("Enrol mgae skipping courseId=$courseId due to course has no groups");
        return true;
    }
   
    if(!$groupRuleArr = $plugin->get_main_rules()) {
        //mtrace("Enrol mgae skipping courseId=$courseId due to empty(\$groupRuleArr_tpl)");
        return true;
    }
    //mtrace(__LINE__.'::$groupRuleArr='.print_r($groupRuleArr, true));
    
    foreach ($groupRuleArr as &$groupRule) {
        //if a group with a matching name does not exist in this course, skip it
        $groupsInCourse = groups_get_all_groups($courseId);
        if(empty($groupsInCourse)) continue;
        //mtrace(__LINE__.'::$groupname='.$groupname);    

        //get the course users with or without groups, 
        //since we don't know the target group name(s) yet 
        //(they're in the user profiles)
        $students = $plugin->get_course_students($courseId);
        //mtrace(count($students).' students found');
        
        foreach($students as &$user) {
            $cust_arr = $plugin->get_user_data_advanced($user);
    
            //get the current group name from the user profile field specified in the main rule
            $groupName = strtr($groupRule, $cust_arr);
            //mtrace("Enrol mgae says \$groupname=$groupname for \$groupRule=$groupRule; \$cust_arr=".print_r($cust_arr, true));
            $repl_arr = $plugin->get_replace_arr();
            $groupName = (!empty($repl_arr)) ? strtr($groupName, $repl_arr) : $groupName;
            //mtrace("Enrol mgae says second version of \$groupname=$groupname for \$groupRule=$groupRule; \$cust_arr=".print_r($cust_arr, true));

            if ($groupName == '') {
                //mtrace("Enrol mgae skipping courseId=$courseId userid={$user->id} due to empty group name");
                continue; // We don't want an empty group name
            };

            //mtrace(__LINE__.'::$groupName='.$groupName);    

            //check the group actually exists in the course
            //mtrace(__LINE__.'::$groupsInCourse='.print_r($groupsInCourse, true));    
            $targetGroup = false;
            foreach($groupsInCourse as &$group) {
                if($group->name==$groupName) {
                    $targetGroup = $group;
                    break;
                }                
            }
            unset($group);
            if(!$targetGroup) {
                //mtrace("Enrol mgae skipping courseId=$courseId userid={$user->id} due to no matching group with name $groupName");                
                continue;
            }

            //if the user is already enrolled in the matching group, skip it
            if(groups_is_member($targetGroup->id, $user->id)) {
                //mtrace("Enrol mgae skipping courseId=$courseId userid={$user->id} due to user already enrolled in group $groupName");
                continue;
            }
            
            //enrol the user in the group
            //mtrace("Enrol mgae About to add_group_member($targetGroup->id, $user->id)");
            $plugin->add_group_member($targetGroup->id, $user->id);
            mtrace("    Enrol mgae enrolled userid=$user->id in group=$targetGroup->id");
        }
        unset($user);
    }
    unset($groupRule);
}