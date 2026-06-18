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
 * English strings for casestudy
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name
$string['modulename'] = 'Case Study';
$string['modulenameplural'] = 'Case Studies';
$string['modulename_help'] = 'The Case Study activity allows students to submit case studies with flexible field structures for assessment by markers.';
$string['casestudyname'] = 'Case Study name';
$string['casestudyname_help'] = 'The name of this Case Study activity.';
$string['pluginname'] = 'Case Study';
$string['pluginadministration'] = 'Case Study administration';

// Capabilities
$string['casestudy:addinstance'] = 'Add a new Case Study activity';
$string['casestudy:view'] = 'View Case Study activity';
$string['casestudy:submit'] = 'Submit case studies';
$string['casestudy:grade'] = 'Grade case studies';
$string['casestudy:viewallsubmissions'] = 'View all submissions';
$string['casestudy:managefields'] = 'Manage case study fields';
$string['managetemplates'] = 'Manage templates';
$string['casestudy:deleteanysubmission'] = 'Delete any submission';
$string['casestudy:manageoverrides'] = 'Manage overrides';
$string['casestudy:viewreports'] = 'View reports';
$string['casestudy:export'] = 'Export data';
$string['casestudy:regrade'] = 'Regrade completed submissions';

// Field management
$string['order'] = 'Order';
$string['fieldname'] = 'Field name';
$string['fieldtype'] = 'Field type';
$string['showlistview'] = 'Show in list view';
$string['hidelistview'] = 'Hide from list view';
$string['iscategory'] = 'Is category';
$string['notcategory'] = 'Not a category';
$string['notrequired'] = 'Not required';
$string['editfield'] = 'Edit field';
$string['deletefield'] = 'Delete field';
$string['confirmdelete'] = 'Are you sure you want to delete this field?';
$string['movefieldUp'] = 'Move field up';
$string['movefielddown'] = 'Move field down';
$string['category_help'] = 'Mark this field as a category for completion criteria purposes.';

// Field order web service
$string['fieldorderupdated'] = 'Field order updated successfully';
$string['fieldorderupdatefailed'] = 'Failed to update field order';
$string['invalidfieldid'] = 'Invalid field ID';
$string['invalidposition'] = 'Invalid position';

// Settings
$string['entries'] = 'Entries';
$string['maxsubmissions'] = 'Maximum entries per student';
$string['maxsubmissions_help'] = 'The maximum number of case studies entries student can submit in the module.';
$string['unlimited'] = 'Unlimited';
$string['submissionsremaining'] = 'Case study entries remaining';
$string['currententries'] = 'Current entries';
$string['submissioncount'] = 'Submissions';
$string['totalsubmissions'] = 'Total submissions: {$a}';
$string['resubmissionlimitreached'] = 'Resubmission limit reached ({$a} submissions)';
$string['submissionsof'] = '{$a->count} of {$a->max} submissions';

// Availability dates
$string['availability'] = 'Availability';
$string['allowsubmissionsfromdate'] = 'Allow submissions from';
$string['allowsubmissionsfromdate_help'] = 'If enabled, students will not be able to submit before this date. If disabled, students can submit immediately.';
$string['duedate'] = 'Due date';
$string['duedate_help'] = 'If enabled, students will not be able to submit after this date. If disabled, there is no submission deadline.';
$string['closebeforeopen'] = 'The due date cannot be earlier than the allow submissions from date.';
$string['notopened'] = 'This case study will be available for submissions from {$a}.';
$string['closed'] = 'This case study is no longer accepting submissions. The due date was {$a}.';
$string['maxsubmissionsreached'] = 'You have reached the maximum number of case study entries allowed ({$a} entries).';
$string['limitreached'] = 'Limit reached';
$string['startdate'] = 'Start date';

$string['notifications'] = 'Notifications';
$string['notifygraders'] = 'Notify graders about submissions';
$string['notifygraders_help'] = 'Send email notifications to graders when students submit case studies.';
$string['notifyemail'] = 'Email others';
$string['notifyemail_help'] = 'Comma-separated list of additional email addresses to notify about submissions.';
$string['notifystudentdefault'] = 'Default for "Notify student"';
$string['notifystudentdefault_help'] = 'Default setting for whether to notify students when giving feedback.';
$string['notifystudent'] = 'Notify student';
$string['notifystudent_help'] = 'Send a notification to the student about this feedback or grade.';

$string['submissionsettings'] = 'Submission settings';
$string['requiresubmit'] = 'Require students to click submit button';
$string['requiresubmit_help'] = 'Students must click a submit button to finalize their case study submission.';
$string['requireacceptance'] = 'Require submission statement';
$string['requireacceptance_help'] = 'Students must accept a submission statement before submitting.';
$string['maxattempts'] = 'Max resubmissions attempts';
$string['maxattempts_help'] = 'Maximum number of resubmission attempts allowed per case study entry.';
$string['resubmissionbased'] = 'Pre-fill resubmissions with previous data';
$string['resubmissionbased_help'] = 'When resubmitting, pre-fill the form with data from the previous submission.';

$string['hidegrader'] = 'Hide grader identity from students';
$string['hidegrader_help'] = 'Hide the name of the grader from students when viewing feedback.';
$string['graderinfo'] = 'Grader information';
$string['graderinfo_help'] = 'Information that will only be visible to graders when marking case studies.';

// Completion
$string['completionentries'] = 'Completion criteria';
$string['completionentries_help'] = 'Completion criteria will be configured in the field management interface.';
$string['completionsummary'] = 'Summary of activity completion status';
$string['totalsatisfactory'] = 'Total number of satisfactory cases';
$string['completionsatisfactorysubmissions'] = 'Require satisfactory submissions';
$string['completionsatisfactorysubmissions_help'] = 'Student must have the specified number of case study submissions marked as satisfactory to complete this activity.';
$string['completiondetail:satisfactorysubmissions'] = 'Receive {$a} satisfactory submission(s)';
$string['completioncategorysubmissions'] = 'Require satisfactory submissions per category';
$string['completioncategorysubmissions_help'] = 'Student must have the specified number of satisfactory submissions for a specific category field to complete this activity. Select the category field and specify how many satisfactory submissions are required for that category.';
$string['completiondetail:categorysubmissions'] = 'Receive {$a->count} satisfactory submission(s) for category "{$a->category}" with value "{$a->value}"';
$string['completiondetail:categorysubmissionsany'] = 'Receive {$a->count} satisfactory submission(s) with category "{$a->category}"';
$string['completiondetail:categoryrules'] = '{$a} category rule(s) configured';
$string['completionaggregationall'] = '(all required)';
$string['completionaggregationany'] = '(any one required)';
$string['completionaggregationallnote'] = 'Note: ALL of the above conditions must be met to complete this activity.';
$string['completionaggregationanynote'] = 'Note: ANY ONE of the above conditions must be met to complete this activity.';
$string['unknownfield'] = 'Unknown field';
$string['addcategoryrule'] = 'Add another category rule';
$string['categoryfield'] = 'Category field';
$string['categoryvalue'] = 'Category value';
$string['requiredcount'] = 'Required count';
$string['anyvalue'] = 'Any value';

// Interface
$string['management'] = 'Management';
$string['managefields'] = 'Manage fields';
$string['viewcasestudy'] = 'View case study';
$string['overrides'] = 'Overrides';
$string['reports'] = 'Reports';
$string['addcasestudy'] = 'Add Case Study';
$string['editcasestudy'] = 'Edit Case Study';
$string['submissionsaved'] = 'Submission saved successfully';
$string['draftsaved'] = 'Draft saved successfully';
$string['savedraft'] = 'Save as draft';
$string['submission_instructions'] = 'Complete the fields below to create your case study submission.';
$string['invalidsubmission'] = 'Invalid submission';
$string['mysubmissions'] = 'My submissions';
$string['graderinterface'] = 'Grader interface';
$string['viewsubmission'] = 'View submission';
$string['casestudyinfo'] = 'Case study information';
$string['casestudyuser'] = 'Case study - {$a}';
$string['timecreated'] = 'Created';
$string['student'] = 'Student';
$string['notrequired'] = 'Not required';
$string['notcategory'] = 'Not a category';
$string['saveandadd'] = 'Save and add another';
$string['finishandsubmit'] = 'Finish and submit';

// Status messages
$string['nofields'] = 'No fields have been configured yet. Click "Manage fields" to add fields to this activity.';
$string['notconfigured'] = 'This activity has not been configured yet.';
$string['nosubmissions'] = 'No case studies have been submitted yet.';
$string['nocasestudies'] = 'No Case Study activities found in this course.';
$string['viewonly'] = 'You can view this activity but cannot submit case studies.';

// Submission statuses
$string['status_new'] = 'Started';
$string['status_draft'] = 'Draft';
$string['status_submitted'] = 'Submitted';
$string['status_in_review'] = 'In review';
$string['status_awaiting_resubmission'] = 'Awaiting resubmission';
$string['status_resubmitted'] = 'Resubmitted';
$string['status_resubmitted_inreview'] = 'Resubmitted - In review';
$string['status_satisfactory'] = 'Satisfactory';
$string['status_unsatisfactory'] = 'Unsatisfactory';

// General
$string['submitted'] = 'Submitted';
$string['submissions'] = 'Submissions';
$string['invalidemail'] = 'Invalid email address in notification list';
$string['invalidemailaddress'] = 'Invalid email address: {$a}';

// Fields management
$string['addfield'] = 'Add field';
$string['clicktoaddfield'] = 'Click to add a field';
$string['editfield'] = 'Edit field';
$string['updatefield'] = 'Update field';
$string['fieldshortname'] = 'Short name';
$string['fieldshortname_help'] = 'A short internal name for this field. It must be unique within fields and contain no spaces.';
$string['fieldname'] = 'Field name';
$string['fieldname_help'] = 'The name of this field as it will appear to students.';
$string['fielddescription'] = 'Field description';
$string['fielddescription_help'] = 'Optional description to help students understand what to enter in this field.';
$string['fieldrequired'] = 'Required field';
$string['fieldrequired_help'] = 'Whether students must complete this field before submitting.';
$string['fieldtype'] = 'Field type';
$string['iscategory'] = 'Category field';
$string['iscategory_help'] = 'Mark this field as a category for completion criteria purposes.';
$string['showlistview'] = 'Show in list view';
$string['showlistview_help'] = 'Display this field in the submissions list view.';
$string['hidelistview'] = 'Hide in list view';
$string['nofieldsyet'] = 'No fields have been created yet.';
$string['existingfields'] = 'Existing fields';
$string['order'] = 'Order';
$string['category'] = 'Category';
$string['listview'] = 'List view';
$string['moveup'] = 'Move up';
$string['movedown'] = 'Move down';
$string['clone'] = 'Clone field';
$string['confirmdelete'] = 'Are you sure you want to delete this field?';
$string['fielddeleted'] = 'Field deleted successfully';
$string['fieldcreated'] = 'Field created successfully';
$string['fieldupdated'] = 'Field updated successfully';
$string['fieldcloned'] = 'Field cloned successfully';
$string['errordeleting'] = 'Error deleting field';
$string['errorcreating'] = 'Error creating field';
$string['errorupdating'] = 'Error updating field';
$string['errorcloning'] = 'Error cloning field';
$string['invalidfield'] = 'Invalid field';
$string['invalidfieldtype'] = 'Invalid field type';

// Field types
$string['fieldtype_text'] = 'Text';
$string['fieldtype_textarea'] = 'Text area';
$string['fieldtype_richtext'] = 'Rich text (HTML editor)';
$string['fieldtype_dropdown'] = 'Dropdown list';
$string['fieldtype_radio'] = 'Radio buttons';
$string['fieldtype_checkbox'] = 'Checkboxes';
$string['fieldtype_file'] = 'File upload';
$string['fieldtype_sectionheading'] = 'Section heading';

// Field configuration
$string['dimensions'] = 'Dimensions (Width x Height)';
$string['dimensions_help'] = 'Set the width (columns) and height (rows) for the textarea. Leave empty for default values.';
$string['usecategory'] = 'Use as category';
$string['width'] = 'Width';
$string['height'] = 'Height';
$string['maxlength'] = 'Maximum length';
$string['maxlength_help'] = 'Maximum number of characters allowed in this field.';

// Rich text field configuration
$string['editorrows'] = 'Editor rows';
$string['editorrows_help'] = 'Number of rows to display in the HTML editor (default: 10).';
$string['editormaxbytes'] = 'Maximum file size (bytes)';
$string['editormaxbytes_help'] = 'Maximum size for files uploaded through the editor. 0 means use site default.';
$string['editormaxfiles'] = 'Maximum files';
$string['editormaxfiles_help'] = 'Maximum number of files that can be uploaded through the editor. -1 means unlimited.';

// Rich text field errors
$string['error_invalid_rows'] = 'Number of rows must be at least 1';
$string['error_invalid_maxbytes'] = 'Maximum file size must be 0 or greater';
$string['error_invalid_maxfiles'] = 'Maximum files must be -1 or greater';

// Field configuration
$string['fieldconfiguration'] = 'Field configuration';

// Field errors
$string['error_field_name_exists'] = 'A field with this name already exists';
$string['error_options_required'] = 'Options are required for this field type';
$string['error_empty_option'] = 'Option cannot be empty';
$string['error_invalid_filesize'] = 'Invalid file size';
$string['error_invalid_minfiles'] = 'Invalid minimum files';
$string['error_invalid_maxfiles'] = 'Invalid maximum files';
$string['error_minfiles_greater_maxfiles'] = 'Minimum files cannot be greater than maximum files';
$string['error_text_too_long'] = 'Text is too long (maximum {$a} characters)';
$string['error_field_type_not_found'] = 'Field type "{$a}" not found';

// Events
$string['eventcoursemoduleviewed'] = 'Case Study activity viewed';
$string['eventcoursemoduleinstancelistviewed'] = 'Case Study activity list viewed';
$string['eventsubmissioncreated'] = 'Case Study submission created';
$string['eventsubmissionupdated'] = 'Case Study submission updated';
$string['eventsubmissiongraded'] = 'Case Study submission graded';
$string['eventoverridecreated'] = 'Case Study override created';
$string['eventoverrideupdated'] = 'Case Study override updated';
$string['eventoverridedeleted'] = 'Case Study override deleted';
$string['grade'] = 'Grade';

// Submission form.
$string['yourcasestudies'] = 'Your Case Studies';
$string['casestudies'] = 'Case Studies';
$string['viewcasestudy'] = 'View Case Study';
$string['confirmdelete'] = 'Are you sure you want to delete this field?';
$string['confirmdeletecasestudy'] = 'Are you sure you want to delete this case study entry? All submissions in this entry (including resubmissions) will be permanently deleted. This action cannot be undone.';
$string['options'] = 'Options (one per line)';
$string['options_help'] = 'Enter each option on a new line.';
$string['novalue'] = 'No value';

$string['filecount_range'] = 'Maximum and Minimum files';
$string['filecount_range_help'] = 'Set the minimum and maximum number of files that can be uploaded for this field. Set to 0 for no limit.';
$string['minfiles'] = 'Minimum files';
$string['maxfiles'] = 'Maximum files';
$string['maxfilesize'] = 'Maximum file size (bytes)';
$string['maxfilesize_help'] = 'Maximum size of each uploaded file in bytes. Set to 0 for no limit.';
$string['accepted_filetypes'] = 'Accepted file types';
$string['accepted_filetypes_help'] = 'Comma-separated list of accepted file types (e.g. .pdf, .docx). Leave empty to accept all file types.';

// Section heading field
$string['sectionheading_note'] = 'Section headings display as headers in forms and submissions. They do not collect input and cannot be marked as required or shown in list views.';

$string['clicktoopen']  = 'Click to view the file enlarged';
$string['preview'] = 'Preview';
$string['casestudyname'] = 'Case Study name';
$string['newvaluefor'] = 'New value for {$a}';

// Grading
$string['gradesubmission'] = 'Grade submission';
$string['markercomments'] = 'Marker comments';
$string['nograde'] = 'No grade';
$string['satisfactory'] = 'Satisfactory';
$string['unsatisfactory'] = 'Unsatisfactory';
$string['requestresubmission'] = 'Request resubmission';
$string['navigation'] = 'Navigation';
$string['previoussubmission'] = 'Previous';
$string['nextsubmission'] = 'Next';
$string['savefeedback'] = 'Save feedback';
$string['saverequestresubmission'] = 'Save & request resubmission';
$string['marksatisfactory'] = 'Mark satisfactory';
$string['markunsatisfactory'] = 'Mark unsatisfactory';
$string['feedbacksaved'] = 'Feedback saved';
$string['resubmissionrequested'] = 'Resubmission requested';
$string['markedsatisfactory'] = 'Marked as satisfactory';
$string['markedunsatisfactory'] = 'Marked as unsatisfactory';
$string['selectuser'] = 'Select user';
$string['usernavigation'] = '{$a->current} of {$a->total}';
$string['xofy'] = '{$a->x} of {$a->y}';
$string['changeuser'] = 'Change user';

// Summaries page.
$string['summaries'] = 'Case Study Summaries';
$string['casestudysummaries'] = 'Case Study Summaries';
$string['summariesview'] = 'Summaries View';
$string['nostudents'] = 'No students enrolled in this activity';
$string['viewsummaries'] = 'View Summaries';
$string['allsubmissions'] = 'All submissions';
$string['viewsubmissions'] = 'View submissions';

// Submission history.
$string['submissionhistory'] = 'Submission history';
$string['submissionhistorydesc'] = 'A Submission history accessible for students and Markers is required for each Case Study submission. The history should also include the Marker feedback received for each attempt.';
$string['attempt'] = 'Attempt';
$string['current'] = 'Current';
$string['submitted'] = 'Submitted';
$string['markerfeedback'] = 'Marker feedback';
$string['grader'] = 'Grader';
$string['timegraded'] = 'Time graded';
$string['nofeedbackyet'] = 'No feedback yet';
$string['acceptance'] = 'This case study submission is my own work, except where I have properly acknowledged the work of others.';
$string['cannoteditsubmitted'] = 'You cannot edit a submission that has already been submitted.';

$string['student'] = 'Student';
$string['timesubmitted'] = 'Time submitted';
$string['timemodified'] = 'Time modified';
$string['submission'] = 'Submission';
$string['noresponse'] = 'No response provided';
$string['gradeitem:submissions'] = 'Grade case study submissions';
$string['submissiondeleted'] = 'Case study submission deleted successfully';
$string['cannotdeletesubmission'] = 'You cannot delete this submission. Only draft submissions can be deleted by the owner, or any submission can be deleted by users with manage permissions.';
$string['cannotreattemptsubmission'] = 'You have reached the maximum number of resubmission attempts allowed for this case study entry.';
$string['cannotregrade'] = 'You cannot regrade a submission that has already been marked as satisfactory or unsatisfactory.';
$string['recreate'] = 'Recreate submission';
$string['reattempt'] = 'Re-attempt';
$string['titlesubmissionreattempt'] = 'Re-attempt submission';
$string['submissionreattempted'] = 'Submission re-attempted successfully';
$string['submissionsubmitted'] = 'Casestudy submitted successfully';
$string['selectsubmission'] = 'Select to grade';
$string['requiredminfiles'] = 'Required minimum {$a} files';

// Template management
$string['casestudy:managetemplates'] = 'Manage submission templates';
$string['singletemplate'] = 'Single view template';
$string['formtemplate'] = 'Submission form template';
$string['csstemplate'] = 'Custom CSS';
$string['headersingletemplate'] = 'Single Submission View Template';
$string['headerformtemplate'] = 'Submission Form Template';
$string['headercsstemplate'] = 'Custom CSS for Submissions';
$string['templatesaved'] = 'Template saved successfully';
$string['templatereset'] = 'Template reset to default';
$string['templateresetall'] = 'All templates reset to default';
$string['resettodefault'] = 'Reset to default';
$string['resetalltemplates'] = 'Reset all templates';
$string['availabletags'] = 'Available Tags';
$string['dragtoinsert'] = 'Drag a tag to insert it into the template';
$string['nofieldsyet'] = 'No fields have been added yet. Please add fields before managing templates.';
$string['addfields'] = 'Add Fields';

// Template tags
$string['tagcategory_user'] = 'User Information';
$string['tagcategory_submission'] = 'Submission Information';
$string['tagcategory_grade'] = 'Grade Information';
$string['tagcategory_actions'] = 'Action Buttons';
$string['tagcategory_fields'] = 'Field Content';
$string['tagcategory_fieldattr'] = 'Field Attributes';
$string['tag_userpicture'] = 'User profile picture';
$string['tag_user'] = 'User full name';
$string['tag_userid'] = 'User ID';
$string['tag_timesubmitted'] = 'Time submitted';
$string['tag_timecreated'] = 'Time created';
$string['tag_timemodified'] = 'Time modified';
$string['tag_status'] = 'Submission status';
$string['tag_attempt'] = 'Attempt number';
$string['tag_grade'] = 'Grade badge';
$string['tag_feedback'] = 'Feedback text';
$string['tag_grader'] = 'Grader name';
$string['tag_gradetime'] = 'Time graded';
$string['tag_edit'] = 'Edit button';
$string['tag_delete'] = 'Delete button';
$string['tag_view'] = 'View button';
$string['tag_title'] = 'Title attribute for field (for accessibility)';
$string['tag_id'] = 'ID attribute for field';
$string['grader'] = 'Grader';
$string['notsubmitted'] = 'Not submitted';

$string['completionsatisfactorydesc'] = 'Activity is completed when students do the following:';
$string['completionsatisfactoryall'] = 'All conditions are met';
$string['completionsatisfactoryany'] = 'Any condition is met';
$string['casestudy:viewsubmissions'] = 'View case study submissions';
$string['casestudy:managesubmissions'] = 'Manage case study submissions';
$string['nogroup'] = 'No group';

// Overrides
$string['overrides'] = 'Overrides';
$string['override'] = 'Override';
$string['addoverride'] = 'Add override';
$string['editoverride'] = 'Edit override';
$string['deleteoverride'] = 'Delete override';
$string['useroverrides'] = 'User overrides';
$string['overrideuser'] = 'Student';
$string['overridesfor'] = 'Overrides for {$a}';
$string['overridesettings'] = 'Override settings';
$string['overridedeleted'] = 'Override deleted successfully';
$string['overridesaved'] = 'Override saved successfully';
$string['confirmdeleteoverride'] = 'Are you sure you want to delete this override?';
$string['nooverrides'] = 'No overrides have been created yet';
$string['usersnone'] = 'There are no students who can submit case studies';
$string['useroverride'] = 'User override';
$string['duplicateoverride'] = 'Duplicate override';
$string['useroverrideexists'] = 'An override already exists for this student';
$string['overrideenddate'] = 'Override end date';
$string['overrideenddate_help'] = 'Enable and set a custom end date (extension) for this student';
$string['overridemaxattempts'] = 'Override number of re-submissions';
$string['overridemaxattempts_help'] = 'Enable and set a custom number of resubmission attempts per case study for this student (1-10)';
$string['enableenddate'] = 'Enable end date override';
$string['enablemaxattempts'] = 'Enable resubmission attempts override';
$string['saveoverride'] = 'Save override';
$string['saveoverrideandstay'] = 'Save and enter another override';
$string['casestudycloses'] = 'Case study closes';
$string['noclose'] = 'No close date';
$string['totalattempts'] = 'Total attempts allowed per case study';
$string['atleastoneoption'] = 'Please enable at least one override option';
$string['submissionlevelreached'] = 'You have reached the maximum number of submissions allowed or the submission deadline has passed';

// Notification messages.
$string['messageprovider:submission'] = 'Notification to markers when a learner submits';
$string['messageprovider:submissionconfirmation'] = 'Confirmation email when learner submits';
$string['messageprovider:gradenotification'] = 'Notification to learner when submission is graded';
$string['messageprovider:weeklyreport'] = 'Weekly submission report to markers';

// Submission notification (to markers).
$string['submissionnotificationsubject'] = '{$a->student} has submitted: {$a->casestudy}';
$string['submissionnotificationtext'] = 'Hi,

{$a->student} has submitted a case study for "{$a->casestudy}" in {$a->course}.

You can view and grade the submission here:
{$a->url}';
$string['submissionnotificationhtml'] = '<p>Hi,</p>
<p><strong>{$a->student}</strong> has submitted a case study for <strong>"{$a->casestudy}"</strong> in {$a->course}.</p>
<p><a href="{$a->url}">View and grade the submission</a></p>';
$string['submissionnotificationsmall'] = '{$a->student} submitted: {$a->casestudy}';

// Submission confirmation (to learner).
$string['submissionconfirmationsubject'] = 'Submission confirmed: {$a->casestudy}';
$string['submissionconfirmationtext'] = 'Hi {$a->student},

Your case study submission for "{$a->casestudy}" in {$a->course} has been received.

Submitted: {$a->timesubmitted}

You can view your submission here:
{$a->url}';
$string['submissionconfirmationhtml'] = '<p>Hi {$a->student},</p>
<p>Your case study submission for <strong>"{$a->casestudy}"</strong> in {$a->course} has been received.</p>
<p><strong>Submitted:</strong> {$a->timesubmitted}</p>
<p><a href="{$a->url}">View your submission</a></p>';
$string['submissionconfirmationsmall'] = 'Submission confirmed: {$a->casestudy}';

// Grade notification (to learner).
$string['gradenotificationsubject'] = 'Your case study has been graded: {$a->casestudy} - {$a->status}';
$string['gradenotificationtext'] = 'Hi {$a->student},

Your case study submission for "{$a->casestudy}" in {$a->course} has been reviewed.

Status: {$a->status}
Graded by: {$a->grader}

Feedback:
{$a->feedback}

View your submission:
{$a->url}';
$string['gradenotificationhtml'] = '<p>Hi {$a->student},</p>
<p>Your case study submission for <strong>"{$a->casestudy}"</strong> in {$a->course} has been reviewed.</p>
<p><strong>Status:</strong> {$a->status}<br>
<strong>Graded by:</strong> {$a->grader}</p>
{$a->feedback}
<p><a href="{$a->url}">View your submission</a></p>';
$string['gradenotificationsmall'] = '{$a->casestudy}: {$a->status}';

// Learner progress report.
$string['messageprovider:learnerreport'] = 'Learner progress report';
$string['learnerreportsubject'] = 'Progress report: {$a->casestudy} ({$a->course})';
$string['learnerreporthello'] = 'Hi {$a},';
$string['learnerreportintro'] = 'Here is your current progress report for "{$a->casestudy}" in {$a->course}.';
$string['learnerreportcomplete'] = 'Congratulations! You have met all completion requirements.';
$string['learnerreportincomplete'] = 'You have not yet met all completion requirements. Keep working!';
$string['learnerreportviewlink'] = 'View your case studies:';
$string['learnerreportsmall'] = 'Progress report for {$a->casestudy}';
$string['completionstatus'] = 'Completion Status';
$string['criterion'] = 'Criterion';
$string['progress'] = 'Progress';

// Scheduled tasks.
$string['taskweeklyreport'] = 'Send weekly submission reports';
$string['tasklearnerreport'] = 'Send learner progress reports';

// Weekly report (to markers).
$string['weeklyreportsubject'] = 'Weekly submission report ({$a->datefrom} - {$a->dateto}): {$a->casestudy}';
$string['weeklyreporttext'] = 'Hi {$a->marker},

Here is your weekly submission report for "{$a->casestudy}" in {$a->course}.

Report period: {$a->datefrom} - {$a->dateto}

{$a->count} submission(s) received during this period.';
$string['weeklyreporthtml'] = '<p>Hi {$a->marker},</p>
<p>Here is your weekly submission report for <strong>"{$a->casestudy}"</strong> in {$a->course}.</p>
<p><strong>Report period:</strong> {$a->datefrom} - {$a->dateto}</p>
<p><strong>{$a->count} submission(s)</strong> received during this period.</p>';
$string['weeklyreportsmall'] = '{$a->count} new submission(s) for {$a->casestudy} ({$a->datefrom} - {$a->dateto})';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['view'] = 'View';
$string['viewallsubmissions'] = 'View all submissions';
$string['submissionerror'] = 'Submission failed. Please try again.';
$string['finishandsubmit'] = 'Submit and finish';
$string['confirmclose'] = 'Please review your case study carefully. After submission, it cannot be edited.';
$string['submission_confirmation'] = 'Are you sure you want to submit?';
$string['submission_confirmation_unanswered'] = 'There are {{.}} unanswered required fields. Are you sure you want to submit?';

// Form template (additional strings)
$string['formtemplate_help'] = 'Customize the layout of the submission form using HTML and field placeholders.';

// Form template tags
$string['tagcategory_other'] = 'Other Tags';
$string['tag_otherfields'] = 'Fields not explicitly placed in template';
$string['tag_acceptance'] = 'Acceptance checkbox (if enabled)';
$string['tag_fieldname'] = 'field label';
$string['tag_fielddescription'] = 'field description';

// Field types in form template
$string['fileuploadrequiresform'] = 'File uploads are handled using the standard form. This field will be rendered normally.';
$string['no_options_configured'] = 'No options have been configured for this field.';

// Form template field tags
$string['tag_form_field'] = '{$a} - Complete form field with label, input and description';
$string['tag_form_label'] = '{$a} - Field label only';
$string['tag_form_description'] = '{$a} - Field description only';
$string['tag_form_input'] = '{$a} - Input element only (without label/description)';
$string['tag_form_required'] = '{$a} - Required indicator (asterisk if field is required)';
$string['tag_form_id'] = '{$a} - Field HTML ID attribute';
