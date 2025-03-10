<?php

class enrol_feecap_plugin extends enrol_plugin {



    public function get_possible_currencies(): array {
        $codes = \core_payment\helper::get_supported_currencies();

        $currencies = [];
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }

        uasort($currencies, function($a, $b) {
            return strcmp($a, $b);
        });

        return $currencies;
    }

    public function get_info_icons(array $instances) {
        $found = false;
        foreach ($instances as $instance) {
            if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
                continue;
            }
            if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
                continue;
            }
            $found = true;
            break;
        }
        if ($found) {
            return array(new pix_icon('icon', get_string('pluginname', 'enrol_feecap'), 'enrol_feecap'));
        }
        return array();
    }

    public function roles_protected() {
        return false;
    }

    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    public function allow_manage(stdClass $instance) {
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (empty(\core_payment\helper::get_supported_currencies())) {
            return false;
        }
	if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/feecap:config', $context)) {
            return false;
        }
	
        return true;
    }

    public function use_standard_editing_ui() {
        return true;
    }

    public function add_instance($course, ?array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }

    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    public function can_enrol(stdClass $instance, $user = null) {
        global $DB;

        if (!parent::can_enrol($instance, $user)) {
            return false;
        }

        $maxlimit = $instance->customint2 ?? 0;
        if ($maxlimit == 0) {
            return true;
        }

        $enrolled = $DB->count_records('user_enrolments', [
            'enrolid' => $instance->id,
            'status' => ENROL_USER_ACTIVE
        ]);

        return $enrolled < $maxlimit;
    }

    public function enrol_page_hook(stdClass $instance) {
        return $this->show_payment_info($instance);
    }

    public function get_description_text($instance) {
        return $this->show_payment_info($instance);
    }

    private function show_payment_info(stdClass $instance) {
        global $USER, $OUTPUT, $DB;

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id' => $instance->courseid));
        $context = context_course::instance($course->id);

        $cost = (float)$instance->cost > 0 ? (float)$instance->cost : (float)$this->get_config('cost');

        if (abs($cost) < 0.01) {
            echo '<p>'.get_string('nocost', 'enrol_feecap').'</p>';
        
	/** HERE IS WHERE I LEFT OFF 
	*/
	} else {
            if (!$this->can_enrol($instance)) {
                \core\notification::error(get_string('maxenrolledreached', 'enrol_feecap'));
                redirect(new \moodle_url('/'));
            } else {
                $successurl = new \moodle_url('/course/view.php', ['id' => $instance->courseid]);
                $data = [
                    'isguestuser' => isguestuser() || !isloggedin(),
                    'cost' => \core_payment\helper::get_cost_as_string($cost, $instance->currency),
                    'instanceid' => $instance->id,
                    'description' => get_string('purchasedescription', 'enrol_feecap', format_string($course->fullname, true, ['context' => $context])),
                    'successurl' => $successurl->out(false),
                    'component' => 'enrol_feecap',
                ];
                echo $OUTPUT->render_from_template('enrol_feecap/payment_region', $data);
            }
        }

        return $OUTPUT->box(ob_get_clean());
    }

    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = [
                'courseid' => $data->courseid,
                'enrol' => $this->get_name(),
                'roleid' => $data->roleid,
                'cost' => $data->cost,
                'currency' => $data->currency,
            ];
        }
        if ($merge && $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    protected function get_status_options() {
        return [ENROL_INSTANCE_ENABLED => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no')];
    }

    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        return $roles;
    }

    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_feecap'), $options);
        $mform->setDefault('status', $this->get_config('status'));

        $accounts = \core_payment\helper::get_payment_accounts_menu($context);
        if ($accounts) {
            $accounts = (count($accounts) > 1 ? ['' => ''] : []) + $accounts;
            $mform->addElement('select', 'customint1', get_string('paymentaccount', 'payment'), $accounts);
        } else {
            $mform->addElement('static', 'customint1_text', get_string('paymentaccount', 'payment'), 
                html_writer::span(get_string('noaccountsavilable', 'payment'), 'alert alert-danger'));
            $mform->addElement('hidden', 'customint1');
            $mform->setType('customint1', PARAM_INT);
        }
        $mform->addHelpButton('customint1', 'paymentaccount', 'enrol_feecap');

        $mform->addElement('text', 'cost', get_string('cost', 'enrol_feecap'), ['size' => 4]);
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', format_float($this->get_config('cost'), 2, true));

        $supportedcurrencies = $this->get_possible_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_feecap'), $supportedcurrencies);
        $mform->setDefault('currency', $this->get_config('currency'));

        $roles = $this->get_roleid_options($instance, $context);
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_feecap'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $options = array('optional' => true, 'defaultunit' => 86400);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_feecap'), $options);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_feecap');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_feecap'), $options);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_feecap');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_feecap'), $options);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_feecap');

    	$mform->addElement('text', 'customint2', get_string('maxenrolled', 'enrol_feecap'));
    	$mform->setType('customint2', PARAM_INT);
    	$mform->setDefault('customint2', $this->get_config('maxenrolled'));
    	$mform->addHelpButton('customint2', 'maxenrolled', 'enrol_feecap');

        if (enrol_accessing_via_instance($instance)) {
            $warningtext = get_string('instanceeditselfwarningtext', 'core_enrol');
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), $warningtext);
        }
    }

    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];

        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_feecap');
        }

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_feecap');
        }

        if (!empty($data['customint2']) && $data['customint2'] < 0) {
            $errors['customint2'] = get_string('invaliddata', 'error');
        }

        $validstatus = array_keys($this->get_status_options());
        $validcurrency = array_keys($this->get_possible_currencies());
        $validroles = array_keys($this->get_roleid_options($instance, $context));
        $tovalidate = [
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'currency' => $validcurrency,
            'roleid' => $validroles,
            'enrolperiod' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT,
            'customint2' => PARAM_INT,
        ];

        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        if ($data['status'] == ENROL_INSTANCE_ENABLED &&
            (!$data['customint1'] || !array_key_exists($data['customint1'], \core_payment\helper::get_payment_accounts_menu($context)))) {
            $errors['status'] = 'Enrolments can not be enabled without specifying the payment account';
        }

        return $errors;
    }

    public function sync(progress_trace $trace) {
        $this->process_expirations($trace);
        return 0;
    }

    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/feecap:config', $context);
    }

    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/feecap:config', $context);
    }

}
