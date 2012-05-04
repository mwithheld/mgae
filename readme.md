# Autoenrol cohort authentication plugin for moodle 2.x

This authentication plugin automatically enrol users into cohorts.
Cohort name depends on user profile field.
Cohorts are created in CONTEXT_SYSTEM.

## Introduction

## Installation

 * Download the archive and extract the files, or clone the repository from GitHub
 * Copy the 'mcae' folder into your_moodle/auth
 * Visit Site administration - Notifications page and follow the instructions

## Upgrade

 * Replace the your_moodle/auth/mcae folder with new one
 * Visit Site administration - Notifications page and follow the instructions

## Configuration

** Template for cohort name **
** Replace empty field **
** Replacement array **
** Unenrol **
** Ignore users **

EXAMPLE:
You have a custom profile fields "status" (student, teacher or admin) and "course". 

You wnat to enrol many users into cohorts like "course - status" than enrol cohorts into courses.

At configuration page set:
Main template to %profile_field_course - %profile_field_status (1 template per line, before profile fields type %)
Empty field text (When field is empty this value used) to none

Result:
When 1st course student logins, he enrol to cohort named "1 - student"
When 1st course teacher logins, he enrol to cohort named "1 - teacher"
When admin logins, he enrol to cohort named "none - admin" (Course not set, status - admin)

To rename "none - admin" cohort to "Administration" you must set a replacement array field at the configuration page (1 replacement per line, old_value|new_value)
In our case: none - admin|Administrator

Result:
When admin logins, he enrol to cohort named "Administrator"

-------
This plugin only create cohorts and enrol users to it.
To find the list of profile fields go to User - > User bulk operation and download 1-2 users.

=== Version 0.3 changes ===
1. Unenrol user from cohort after profile change.
How to use this function:
 a) Go to Plugins - Authentication - Autoenrol cohort and enable unenrol function,
 b) Go to yourmoodle/auth/mcae/convert.php and convert cohorts to "auth_mcae". Convert only cohorts that are created by the "auth_mcae" module!

At yourmoodle/auth/mcae/convert.php page you may view, delete or convert cohorts into "manual" or "auth_mcae" mode.
---
2. Add "%email_username" and "%email_domain" variables to use in main rule.

3. "Ignore users" list.
EXAMPLE: admin,test,manager,teacher1,teacher2