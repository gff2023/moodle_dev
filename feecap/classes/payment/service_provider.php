<?php
namespace enrol_feecap\payment;


class service_provider implements \core_payment\local\callback\service_provider {

    public static function get_payable(string $component, string $paymentarea, int $instanceid): \core_payment\local\entities\payable {
        global $DB;

        $instance = $DB->get_record('enrol', ['enrol' => 'feecap', 'id' => $instanceid], '*', MUST_EXIST);

        return new \core_payment\local\entities\payable($instance->cost, $instance->currency, $instance->customint1);
    }

    public static function get_success_url(string $paymentarea, int $instanceid): \moodle_url {
        global $DB;
        $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'feecap', 'id' => $instanceid], MUST_EXIST);
        return new \moodle_url('/course/view.php', ['id' => $courseid]);
    }

    public static function deliver_order(string $paymentarea, int $instanceid, int $paymentid, int $userid): bool {
        global $DB;

        $instance = $DB->get_record('enrol', ['enrol' => 'feecap', 'id' => $instanceid], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('feecap');

        if ($instance->enrolperiod) {
            $timestart = time();
            $timeend   = $timestart + $instance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend   = 0;
        }

        $plugin->enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend);
        return true;
    }
}
