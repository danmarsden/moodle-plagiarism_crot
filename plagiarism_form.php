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

        $mform->addElement('text', 'grammarsize', get_string('grammar_size', 'plagiarism_crot'));
        $mform->setDefault('grammarsize', '30');
        $mform->addRule('grammarsize', null, 'numeric', null, 'client');
        $mform->addElement('text', 'windowsize', get_string('window_size', 'plagiarism_crot'));
        $mform->setDefault('windowsize', '60');
        $mform->addRule('windowsize', null, 'numeric', null, 'client');
        $mform->addElement('text', 'colours', get_string('colours', 'plagiarism_crot'));
        $mform->setDefault('colours', '#FF0000,#0000FF, #A0A000, #00A0A0');
        $mform->addElement('text', 'clusterdist', get_string('cluster_distance', 'plagiarism_crot'));
        $mform->setDefault('clusterdist', '55');
        $mform->addRule('clusterdist', null, 'numeric', null, 'client');
        $mform->addElement('text', 'clustersize', get_string('cluster_size', 'plagiarism_crot'));
        $mform->setDefault('clustersize', '10');
        $mform->addRule('clustersize', null, 'numeric', null, 'client');
        $mform->addElement('text', 'threshold', get_string('default_threshold', 'plagiarism_crot'));
        $mform->setDefault('threshold', '0');
        $mform->addRule('threshold', null, 'numeric', null, 'client');
        
        $mform->addElement('html', get_string('global_search_settings', 'plagiarism_crot'));
        $mform->addElement('text', 'global_threshold', get_string('global_search_threshold', 'plagiarism_crot'));
        $mform->setDefault('global_threshold', '90');
        $mform->addRule('global_threshold', null, 'numeric', null, 'client');
        $mform->addElement('text', 'live_key', get_string('MS_live_key', 'plagiarism_crot'));
        $mform->addRule('live_key', null, 'required', null, 'client');
        $mform->addElement('text', 'global_search_query_size', get_string('global_search_query_size', 'plagiarism_crot'));
        $mform->setDefault('global_search_query_size', '7');
        $mform->addRule('global_search_query_size', null, 'numeric', null, 'client');
        $mform->addElement('text', 'percentage_of_search_queries', get_string('percentage_of_search_queries', 'plagiarism_crot'));
        $mform->setDefault('percentage_of_search_queries', '40');
        $mform->addRule('percentage_of_search_queries', null, 'numeric', null, 'client');
        $mform->addElement('text', 'number_of_web_documents', get_string('number_of_web_documents', 'plagiarism_crot'));
        $mform->setDefault('number_of_web_documents', '10');
        $mform->addRule('number_of_web_documents', null, 'numeric', null, 'client');
        $mform->addElement('text', 'culture_info', get_string('culture_info', 'plagiarism_crot'));
        $mform->setDefault('culture_info', 'en-us');
        $mform->addElement('html', get_string('tools', 'plagiarism_crot'));
        $mform->addElement('checkbox', 'delall', get_string('clean_tables', 'plagiarism_crot'));
        $mform->addElement('checkbox', 'testglobal', get_string('test_global_serach', 'plagiarism_crot'));
        

        $this->add_action_buttons(true);
    }
}

