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
 * Adds new instance of the plugin to specified course.
 *
 * @package    enrol
 * @subpackage mgae
 * @copyright  2012 Mark van Hoek
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/mgae/locallib.php");
require(dirname(__FILE__) . '/globals.php');

$id = required_param('id', PARAM_INT); // course id

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/mgae:config', $context);

$enrol = enrol_get_plugin($pluginShortName);
if (!$enrol->get_newinstance_link($course->id)) {
    redirect(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
}

$student = get_archetype_roles('student');
$student = reset($student);

$enrol->add_instance($course, array('roleid'=>$student->id));
$enrol->enrol_mgae_do_sync($course->id);
redirect(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
