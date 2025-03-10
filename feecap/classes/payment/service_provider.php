<?php
namespace enrol_feecap\payment;

defined('MOODLE_INTERNAL') || die();

class service_provider implements \core_payment\local\callback\service_provider {

    public static function get_payable(string $component, string $paymentarea, int $instanceid): \core_payment\local\entities\payable {
        global $DB;

        $enrol = $DB->get_record('enrol', ['enrol' => 'feecap', 'id' => $instanceid], '*', MUST_EXIST);

        $cost = (float)$enrol->cost;
        if ($cost <= 0) {
            $cost = (float)get_config('enrol_feecap', 'cost');
        }
        if ($cost < 0) {
            $cost = 0;
        }

        return new \core_payment\local\entities\payable($cost, $enrol->currency, $enrol->customint1);
    }

    public static function get_success_url(string $component, int $paymentarea): \moodle_url {
        global $DB;
        $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'feecap', 'id' => $paymentarea], MUST_EXIST);
        return new \moodle_url('/course/view.php', ['id' => $courseid]);
    }

    public static function deliver_order(string $component, string $paymentarea, int $instanceid, int $userid): bool {
        global $DB;

        $instance = $DB->get_record('enrol', ['enrol' => 'feecap', 'id' => $instanceid], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('feecap');

        $roleid = $instance->roleid ?: $plugin->get_config('roleid');
        $timeend = $instance->enrolperiod ? (time() + $instance->enrolperiod) : 0;

        $plugin->enrol_user($instance, $userid, $roleid, time(), $timeend);
        return true;
    }
}
