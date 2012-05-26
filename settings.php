<?php

/**
 * Adds this plugin to the admin menu.
 *
 * @subpackage mgae
 * @copyright  2012 Mark van Hoek
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $USER;

require_once($CFG->dirroot.'/user/profile/lib.php');

if ($hassiteconfig) { // needs this condition or there is error on login page
}

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtextarea('enrol_mgae/mainrule_fld', get_string('mainrule_fld', 'enrol_mgae'), '', ''));

// Profile field helper
    $fldlist = array();
    $usr_helper = $USER;

    profile_load_data($usr_helper);
    foreach ($usr_helper as $key => $val){
        $fld = preg_replace('/profile_field_/', 'profile_field_raw_', $key);
        if (is_array($val)) {
            if (isset($val['text'])) {
                $fldlist[] = "<span title=\"%$fld\">%$fld</span>";
            };
        } else {
            $fldlist[] = "<span title=\"%$fld\">%$fld</span>";
        };
    }; 

    // Custom profile field values
    foreach ($usr_helper->profile as $key => $val) {
        $fldlist[] = "<span title=\"%profile_field_$key\">%profile_field_$key</span>";
    };

    // Additional values for email
    $fldlist[] = "<span title=\"%email_username\">%email_username</span>";
    $fldlist[] = "<span title=\"%email_domain\">%email_domain</span>";

    sort($fldlist);
    $help_text = implode(', ', $fldlist);

    $settings->add(new admin_setting_heading('enrol_mgae_profile_help', get_string('profile_help', 'enrol_mgae'), $help_text));

    $settings->add(new admin_setting_configselect('enrol_mgae/delim', get_string('delim', 'enrol_mgae'), get_string('delim_help', 'enrol_mgae'), 'CR+LF', array('CR+LF'=>'CR+LF', 'CR'=>'CR', 'LF'=>'LF')));
    $settings->add(new admin_setting_configtext('enrol_mgae/secondrule_fld', get_string('secondrule_fld', 'enrol_mgae'),'', 'n/a'));
    $settings->add(new admin_setting_configtextarea('enrol_mgae/replace_arr', get_string('replace_arr', 'enrol_mgae'), '', ''));
    
    //$settings->add(new admin_setting_configtextarea('enrol_mgae/donttouchusers', get_string('donttouchusers', 'enrol_mgae'), '', ''));
    //$settings->add(new admin_setting_configcheckbox('enrol_mgae/enableunenrol', get_string('enableunenrol', 'enrol_mgae'), '', 0));
}
