<?php

require_once($CFG->dirroot.'/lib/formslib.php');

class plagiarism_setup_form extends moodleform {

/// Define the form
    function definition () {
        global $CFG;

        $mform =& $this->_form;
        $choices = array('No','Yes');
        $mform->addElement('html', get_string('crotexplain', 'plagiarism_crot'));
        $mform->addElement('checkbox', 'crot_use', get_string('usecrot', 'plagiarism_crot'));

        $mform->addElement('textarea', 'crot_student_disclosure', get_string('studentdisclosure','plagiarism_crot'),'wrap="virtual" rows="6" cols="50"');
        $mform->addHelpButton('crot_student_disclosure', 'studentdisclosure', 'plagiarism_crot');
        $mform->setDefault('crot_student_disclosure', get_string('studentdisclosuredefault','plagiarism_crot'));

        $this->add_action_buttons(true);
    }
}

