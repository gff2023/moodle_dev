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
 * Settings for enrol_feecap plugin.
 *
 * @package    enrol_feecap
 * @copyright  2025 Jeff Groff
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $plugin = enrol_get_plugin('feecap');

    // Default cost.
    $settings->add(new admin_setting_configtext(
        'enrol_feecap/cost',
        get_string('cost', 'enrol_feecap'),
        get_string('configcost', 'enrol_feecap'),
        0,
        PARAM_FLOAT,
        10
    ));

    // Default currency.
    $currencies = $plugin->get_possible_currencies();
    $settings->add(new admin_setting_configselect(
        'enrol_feecap/currency',
        get_string('currency', 'enrol_feecap'),
        get_string('configcurrency', 'enrol_feecap'),
        'USD',
        $currencies
    ));

    // Default role.
    $roles = get_default_enrol_roles(context_system::instance());
    $settings->add(new admin_setting_configselect(
        'enrol_feecap/roleid',
        get_string('defaultrole', 'role'),
        get_string('defaultrole_desc', 'enrol'),
        key(array_slice($roles, 0, 1, true)), // First role (e.g., Student).
        $roles
    ));

    // Default status.
    $options = [ENROL_INSTANCE_ENABLED => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no')];
    $settings->add(new admin_setting_configselect(
        'enrol_feecap/status',
        get_string('status', 'enrol_feecap'),
        get_string('status_desc', 'enrol_feecap'),
        ENROL_INSTANCE_DISABLED,
        $options
    ));

    // Default max enrollment.
    $settings->add(new admin_setting_configtext(
        'enrol_feecap/maxenrolled',
        get_string('maxenrolled', 'enrol_feecap'),
        get_string('configmaxenrolled', 'enrol_feecap'),
        0,
        PARAM_INT
    ));
}
