=== Blue Cube Mighty Gravity Forms ===
Contributors: thebluecube
Donate link:
Tags: form, forms, form validation, gravity forms, add-on, extension
Requires at least: 4.6
Tested up to: 4.6.1
Stable tag: 1.1.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin adds some additional features to the Gravity Forms plugin: Advanced input validations, Input ID change, Form access control etc.

== Description ==

This plugin adds some additional features to the Gravity Forms plugin:

* Input ID change (if the form has not received any entries yet)
* Advanced input validations
* Form access control
* Advanced conditional logic shortcode to be used in email notifications

Documentation: https://thebluecube.com/wordpress-plugins/bluecube-mighty-gravity-forms/documentation/


== Installation ==

Download and unzip the plugin. Then upload the `"bluecube-mighty-gravityforms"` folder to the `/wp-content/plugins/` directory (or the relevant directory if your WordPress file structure is different), or you can simply install it using the WordPress plugin installer.

== Frequently asked questions ==


== Screenshots ==


== Changelog ==

= 1.1.5 =
* Simplifying the way we determine whether we should apply a custom validation rule or not. Now we make sure that the value is valid (determined by Gravity Forms) and is not going to raise a built-in `required` validation error.

= 1.1.4 =
* Fixing a bug which was caused by applying a custom validation to a required date field with the type of dropdown.

= 1.1.3 =
* Fixing a bug which sent unintentional headers.

= 1.1.2 =
* Changing the error message for the 'requiredWith' validation rule to a simpler one.

= 1.1.1 =
* Fixing a bug in the maybeMarkTheFormAsValid method

= 1.1 =
* Adding advanced conditional logic shortcode to be used in email notifications

= 1.0.1 =
* Passing an array of additional arguments to the custom validation rule function

= 1.0 =
* This is the first version of the plugin, adding some additional features to the Gravity Forms plugin

== Upgrade notice ==
