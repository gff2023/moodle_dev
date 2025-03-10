<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_feecap_upgrade($oldversion) {
    if ($oldversion < 2025030900) {
        upgrade_plugin_savepoint(true, 2025030900, 'enrol', 'feecap');
    }
    return true;
}
