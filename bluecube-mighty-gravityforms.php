<?php

/**
 * Plugin Name: Blue Cube Mighty Gravity Forms
 * Plugin URI: https://thebluecube.com/wordpress-plugins/bluecube-mighty-gravity-forms/
 * Description: This plugin adds some advanced features to Gravity Forms e.g. custom validation rules etc.
 * Author: Blue Cube Communications Ltd
 * Version: 1.1.5
 * Author URI: https://thebluecube.com
 * License: GPLv2 or later
 */


if (! defined('ABSPATH')) die();

define('MIGHTY_GRAVITY_FORMS_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once __DIR__ . '/class/Mighty_Gravity_Forms.php';

$bc_mighty_gravityforms = new BlueCube\Mighty_Gravity_Forms();
