<?php

namespace BlueCube;

if (! defined('ABSPATH')) die();

/**
 * Class Mighty_Gravity_Forms
 * Developed by: Hesam Bahrami (Genzo)
 *
 * @package BlueCube
 */
class Mighty_Gravity_Forms
{

	protected $form_access_already_checked = false;
	protected $msg_var_name = 'bc-mgf-msg';
	protected $messages = ['no-access-to-form-entries' => 'You do not have access to any form entries.', 'redirected-to-1st-form' => 'Please beware that you have been redirected to the first form that you have access to.', 'form-edit-denied' => 'You cannot edit this form.',];
	protected $form;
	protected $validation_result;
	protected $generic_error_msg = 'Input is invalid.';
	protected $validation_method_name_suffix = 'ValidationCheck';


	public function __construct()
	{
		add_action('admin_notices', [$this, 'showMessages']);

		add_action('gform_advanced_settings', [$this, 'addFormExtraSettingsInputs'], 10, 2);
		add_filter('gform_pre_form_settings_save', [$this, 'saveFormRoleSettings']);

		add_action('gform_field_advanced_settings', [$this, 'addFormInputExtraSettingsControls'], 10, 2);
		add_filter('gform_tooltips', [$this, 'addTooltips']);
		add_action('gform_editor_js', [$this, 'formInputExtraSettingsScript']);

		add_filter('gform_validation', [$this, 'validateForm']);

		add_action('init', [$this, 'checkFormEntryAccess']); // Entry access check
		add_action('init', [$this, 'checkFormEditAccess']); // Form edit access check

		// To enforce form access check on form entry export
		add_filter('gform_form_post_get_meta', [$this, 'enforceFormAccessCheckOnEntryExport']);

		add_filter('gform_shortcode_form', [$this, 'addFrontEndNumberRangeChange'], 10, 3);

		// Adding a shortcode to have advanced conditional logic in email notifications
		add_shortcode('gf_notification_conditional_block', [$this, 'notificationConditionalBlockShortcode']);
	}


	/**
	 * Shows all the extra settings for forms
	 *
	 * @param $priority
	 * @param $form_id
	 */
	public function addFormExtraSettingsInputs($priority, $form_id)
	{
		if ($priority != 100) return;

		echo '<h4 class="gf_settings_subgroup_title">Extra settings:</h4>';
		echo '<table class="gforms_form_settings" cellspacing="0" cellpadding="0">';
		$this->addFormKeySettingsInputs($form_id);
		$this->addFormRoleSettingsInputs($form_id);
		echo '</table>';
	}


	/**
	 * Allows us to assign a key name to forms
	 *
	 * @param $form_id
	 */
	public function addFormKeySettingsInputs($form_id)
	{
		$form = \RGFormsModel::get_form_meta($form_id);
		$key = (isset($form['key'])) ? $form['key'] : '';

		echo '<tr>';
		echo '<th><label for="form-key">Form key</label></th>';
		echo '<td><input type="text" name="form_key" id="form-key" value="' . $key . '"></td>';
		echo '</tr>';
	}


	/**
	 * Shows the roles checkboxes on the form settings page so we can restrict some of the form functionality to the selected roles
	 *
	 * @param $form_id
	 */
	public function addFormRoleSettingsInputs($form_id)
	{
		global $wp_roles;

		$form = \RGFormsModel::get_form_meta($form_id);

		echo '<tr>';
		echo '<th>Access settings</th>';
		echo '<td>';
		foreach ($wp_roles->roles as $role_key => $role_array) {
			if ($role_key == 'administrator') continue;
			$selected = (isset($form['roles']) && is_array($form['roles']) && in_array($role_key, $form['roles'])) ? 'checked="checked"' : '';
			echo '<label for="role-' . $role_key . '"><input type="checkbox" name="roles[]" id="role-' . $role_key . '" value="' . $role_key . '" ' . $selected . '> ' . $role_array['name'] . '</label><br>';
		}
		echo '</td>';
		echo '</tr>';
	}


	/**
	 * Saves forms role settings
	 *
	 * @param $updated_form
	 * @return mixed
	 */
	public function saveFormRoleSettings($updated_form)
	{
		$updated_form['roles'] = rgpost('roles');
		$updated_form['key'] = rgpost('form_key');

		return $updated_form;
	}


	/**
	 * Checks if current user should have access to the entries of a specific form
	 */
	public function checkFormEntryAccess()
	{
		if (! isset($_GET['page']) || $_GET['page'] != 'gf_entries') return; // This is not the desired admin page

		if ($this->currentUserHasRole('administrator')) return; // Admins would have full access

		$form_id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? $_GET['id'] : 1;
		if (! $this->currentUserHasAccessToForm($form_id)) {
			$this->redirectToNextFormOrDashboard($form_id);
		}
	}


	/**
	 * Checks if current user should have access to the form edit page
	 */
	public function checkFormEditAccess()
	{
		if (! isset($_GET['page']) || $_GET['page'] != 'gf_edit_forms') return; // This is not the desired admin page

		if ($this->currentUserHasRole('administrator')) return; // Admins would have full access

		$form_id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? $_GET['id'] : null;
		if ($form_id && ! $this->currentUserHasAccessToForm($form_id)) {
			$this->redirect(admin_url() . 'admin.php?page=gf_edit_forms', 'form-edit-denied');
		}
	}


	/**
	 * Redirects to a page with an optional error message.
	 *
	 * @param null $uri
	 * @param null $error_msg
	 */
	protected function redirect($uri = null, $error_msg = null)
	{
		if (is_null($uri)) $uri = admin_url();

		if (! is_null($error_msg)) {
			$que_or_and = (strpos($uri, '?') === false) ? '?' : '&';
			// In order to show a proper message
			$uri .= "{$que_or_and}{$this->msg_var_name}={$error_msg}";
		}

		wp_redirect($uri);
		exit;
	}


	/**
	 * Redirects either to the next form or the dashboard with an error message
	 *
	 * @param $form_id
	 */
	protected function redirectToNextFormOrDashboard($form_id)
	{

		$next_form = $this->findNextActiveForm($form_id);
		if (! $next_form) $this->redirect(null, 'no-access-to-form-entries');
		// To redirect to the next form page
		$this->redirect(admin_url() . "admin.php?page=gf_entries&id={$next_form['id']}", 'redirected-to-1st-form');
	}


	/**
	 * Shows admin notices
	 */
	public function showMessages()
	{
		if (isset($_GET[$this->msg_var_name]) && ! empty($_GET[$this->msg_var_name])) {
			echo '<div class="error notice"><p>' . $this->messages[$_GET[$this->msg_var_name]] . '</p></div>';
		}
	}


	/**
	 * To check the existence of a form
	 *
	 * @param $form_id
	 * @return array|bool|null
	 */
	public function formExists($form_id)
	{
		$form = \RGFormsModel::get_form_meta($form_id);

		return is_null($form) ? false : $form;
	}


	/**
	 * To find the next active form
	 *
	 * @param $current_form_id
	 * @return array|bool|null
	 */
	protected function findNextActiveForm($current_form_id)
	{
		while ($form = $this->formExists(++$current_form_id)) {
			if (isset($form['is_active']) && $form['is_active'] && isset($form['is_trash']) && ! $form['is_trash']) {
				return $form;
			}
		}

		return null;
	}


	/**
	 * Enforces the form access check when the entries are being exported
	 *
	 * @param $form
	 */
	public function enforceFormAccessCheckOnEntryExport($form)
	{
		if ($this->form_access_already_checked) return $form; // To prevent a loop

		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'gf_download_export') {
			$this->form_access_already_checked = true;

			return $this->currentUserHasAccessToForm($form['id']) ? $form : die('Access denied!');
		}

		return $form;
	}


	/**
	 * Checks to see if current user has the proper role to access a form
	 *
	 * @param $form_id
	 * @return bool
	 */
	public function currentUserHasAccessToForm($form_id)
	{
		$form = \RGFormsModel::get_form($form_id);
		$form_meta = \RGFormsModel::get_form_meta($form_id);

		if (! $form || ! $this->formHasAssignedRoles($form_meta)) return false;

		foreach ($form_meta['roles'] as $role) {
			if ($this->currentUserHasRole($role)) return true;
		}

		return false;
	}


	/**
	 * Checks if a form has assigned roles
	 *
	 * @param $form
	 * @return bool
	 */
	protected function formHasAssignedRoles($form)
	{
		return isset($form['roles']) && is_array($form['roles']);
	}


	/**
	 * Conditional tag to check whether the currently logged-in user has a specific role.
	 *
	 * @param  string $role
	 * @return bool
	 */
	protected function currentUserHasRole($role)
	{
		return is_user_logged_in() ? $this->userHasRole(get_current_user_id(), $role) : false;
	}


	/**
	 * Conditional tag to check whether a user has a specific role.
	 *
	 * @param  int    $user_id
	 * @param  string $role
	 * @return bool
	 */
	protected function userHasRole($user_id, $role)
	{
		$user = new \WP_User($user_id);

		return in_array($role, (array)$user->roles);
	}


	/**
	 * To add all extra settings controls
	 *
	 * @param $priority
	 * @param $form_id
	 */
	public function addFormInputExtraSettingsControls($priority, $form_id)
	{
		if ($priority != 0) return;

		$this->addFormInputIdChangeBtn($form_id);
		$this->addFormInputKey($form_id);
		$this->addFormInputValidationRules($form_id);
	}


	/**
	 * Adds the tooltips
	 *
	 * @param $tooltips
	 * @return mixed
	 */
	public function addTooltips($tooltips)
	{
		$tooltips['form_field_id'] = "You can change the field ID if this form hasn't got any entries yet.";

		return $tooltips;
	}


	/**
	 * To add input ID setting
	 *
	 * @param $form_id
	 */
	protected function addFormInputIdChangeBtn($form_id)
	{
		$has_entries = (\GFFormsModel::get_lead_count($form_id, '') > 0) ? true : false;
		$disabled = $has_entries ? 'disabled="disabled"' : '';
		?>
		<li class="id_setting">
			<label for="field_id" class="section_label">
				<?php _e('ID', 'gravityforms'); ?>
				<?php gform_tooltip('form_field_id') ?>
			</label>
			<input type="text" id="field_id" class="field_id fieldwidth-2 mt-position-right" <?php echo $disabled; ?> />
			<input type="hidden" class="current-id"/>
		</li>
		<?php
	}


	/**
	 * To add input key setting
	 *
	 * @param $form_id
	 */
	protected function addFormInputKey($form_id)
	{
		?>
		<li class="key_setting">
			<label for="field_key" class="section_label">
				<?php _e('Key', 'gravityforms'); ?>
				<?php // gform_tooltip( 'form_field_key' )
				?>
			</label>
			<input type="text" id="field_key" class="field_key fieldwidth-2 mt-position-right"/>
		</li>
		<?php
	}


	/**
	 * To add input validation rules setting
	 *
	 * @param $form_id
	 */
	protected function addFormInputValidationRules($form_id)
	{
		?>
		<li class="validation_rules_setting">
			<label for="field_validation_rules" class="section_label">
				<?php _e('Validation Rules', 'gravityforms'); ?>
				<?php // gform_tooltip( 'form_field_validation_rules' )
				?>
			</label>
			<input type="text" id="field_validation_rules"
				   class="field_validation_rules fieldwidth-2 mt-position-right"/>
		</li>
		<?php
	}


	/**
	 * Adding the required JS code for field extra settings
	 */
	public function formInputExtraSettingsScript()
	{
		?>
		<script type='text/javascript'>

			/**
			 * @version      1.0
			 * @author    David Smith <david@gravitywiz.com>
			 * @license   GPL-2.0+
			 * @link      http://gravitywiz.com/changing-your-gravity-forms-field-ids/
			 * @video     http://www.screencast.com/t/STm1eLZEsR9q
			 * @copyright 2015 Gravity Wiz
			 *
			 * @param currentId
			 * @param newId
			 * @returns {boolean}
			 */
			gwChangeFieldId = function (currentId, newId) {
				for (var i = 0; i < form.fields.length; i++) {
					if (form.fields[i].id == currentId) {
						form.fields[i].id = newId;
						jQuery('#field_' + currentId).attr('id', 'field_' + newId);
						if (form.fields[i].inputs) {
							for (var j = 0; j < form.fields[i].inputs.length; j++) {
								form.fields[i].inputs[j].id = form.fields[i].inputs[j].id.replace(currentId + '.', newId + '.');
							}
						}
						return true;
					}
				}
				return false;
			};

			gwFieldIdExists = function (id) {
				for (var i = 0; i < form.fields.length; i++) {
					if (form.fields[i].id == id) {
						return true;
					}
				}
				return false;
			};

			// Field ID
			// Setting the value to be saved
			jQuery('html').on('keyup', "#field_id", function () {
				current_id = jQuery(this).siblings('.current-id').first().val();
				new_id = jQuery(this).val();
				if (gwFieldIdExists(new_id)) {
					alert('This ID is already assigned to another field. Please choose another ID.');
				} else {
					id_change_result = gwChangeFieldId(current_id, new_id);
					if (id_change_result) jQuery(".current-id").val(new_id);
				}
			});

			// Field Key
			// Setting the value to be saved
			jQuery('html').on('keyup', "#field_key", function () {
				SetFieldProperty('key', jQuery(this).val());
			});

			// Field Validation Rules
			// Setting the value to be saved
			jQuery('html').on('keyup', "#field_validation_rules", function () {
				SetFieldProperty('validationRules', jQuery(this).val());
			});

			// Binding to the load field settings event to initialize the input
			jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
				jQuery("#field_id, .current-id").val(field["id"]);
				jQuery("#field_key").val(field["key"]);
				jQuery("#field_validation_rules").val(field["validationRules"]);
			});
		</script>
		<?php
	}


	/**
	 * A convenient way to find a specific field among all the form fields
	 *
	 * @param $field_property
	 * @param $field_value
	 * @return mixed
	 */
	protected function getTheFormField($field_property, $field_value)
	{
		foreach ($this->form['fields'] as $field) {
			if (isset($field->{$field_property}) && $field->{$field_property} == $field_value) {
				return $field;
			}
		}

		return null;
	}


	/**
	 * Validates all the form fields
	 *
	 * @param $validation_result
	 * @return mixed
	 */
	public function validateForm($validation_result)
	{

		// Get the form object from the validation result
		$this->validation_result = $validation_result;
		$this->form = $validation_result['form'];

		foreach ($this->form['fields'] as $field) {
			$this->validateTheField($field);
		}

		return $this->validation_result;
	}


	/**
	 * Creates an array of field validation rules
	 *
	 * @param $field
	 * @return array
	 */
	protected function getFieldValidationRules($field)
	{
		$validation_rules = explode('|', $field->validationRules);

		return array_map(function ($item) {
			return explode('::', $item);
		}, $validation_rules);
	}


	/**
	 * @param $field
	 * @return mixed
	 */
	protected function validateTheField($field)
	{

		if (empty($field->validationRules)) return;

		$validation_rules_array = $this->getFieldValidationRules($field);

		foreach ($validation_rules_array as $validation_rule) {
			$method_name = $validation_rule[0] . $this->validation_method_name_suffix;
			if (method_exists($this, $method_name)) {
				$this->$method_name($validation_rule, $field);
			}
		}
	}


	/**
	 * Defines whether a field value is empty
	 *
	 * @param $field
	 * @return bool
	 */
	protected function fieldValueIsEmpty($field)
	{
		$value = $this->getFieldValue($field);

		return ((is_array($value) && empty($value)) || ($value == '')) ? true : false; // We do not want to consider values like 0 or 0.0 as empty
	}


	/**
	 * Defines whether a field has a specific value
	 *
	 * @param $field
	 * @param $expected_value
	 * @return bool
	 */
	protected function fieldHasTheValue($field, $expected_value)
	{
		$value = $this->getFieldValue($field);
		if (is_array($value)) {
			$has_the_value = (in_array($expected_value, $value)) ? true : false;
		} else {
			$has_the_value = ($value == $expected_value) ? true : false;
		}

		return $has_the_value;
	}


	/**
	 * Takes care of field invalidation
	 *
	 * @param        $field
	 * @param string $msg
	 */
	protected function invalidateTheField($field, $msg = 'Input validation failed')
	{
		// The field validation, so first we'll need to fail the validation for the entire form
		$this->validation_result['is_valid'] = false;
		// Next we'll mark the specific field that failed and add a custom validation message
		$field->failed_validation = true;
		$field->validation_message = $msg;
	}


	/**
	 * Marks an input as valid and also the whole form if that input is the only invalid one on the form
	 *
	 * @param        $field
	 */
	protected function markTheFieldAsValid($field)
	{
		$this->maybeMarkTheFormAsValid($field);
		$field->failed_validation = false;
	}


	/**
	 * We need this to prevent marking the whole form as valid if we need to mark a single input of the form as valid while it is not the only invalid input of the form
	 *
	 * @param $field
	 */
	protected function maybeMarkTheFormAsValid($field)
	{
		$invalid_inputs = array_filter($this->validation_result['form']['fields'], function ($input) {
			return $input->validation_message != '';
		});

		if (count($invalid_inputs) == 1) {
			$invalid_input = array_shift($invalid_inputs);
			if ($invalid_input->id == $field->id) $this->validation_result['is_valid'] = true;
		}
	}

	/**
	 * Gets field values from $_POST
	 *
	 * @param $field
	 * @return array
	 */
	protected function getFieldValue($field)
	{
		if ($field->type == 'checkbox') {
			$checked_choices = [];
			for ($i = 1; $i <= count($field->choices); ++$i) {
				$field_key = 'input_' . $field->id . '_' . $i;
				if (isset($_POST[$field_key])) $checked_choices[$field_key] = $_POST[$field_key];
			}

			return $checked_choices;
		} else {
			return rgpost("input_{$field['id']}");
		}
	}


	/**
	 * Enforces the requiredWith validation rule
	 *
	 * @param $validation_rule
	 * @param $field
	 */
	protected function requiredWithValidationCheck($validation_rule, $field)
	{
		$ruler_field_key = $validation_rule[1];
		$ruler_field = $this->getTheFormField('key', $ruler_field_key);
		if (is_null($ruler_field)) return;

		$ruler_field_expected_value = (isset($validation_rule[2]) && $validation_rule[2] != '') ? $validation_rule[2] : null;

		$error_msg = 'This field is required.';
		if (is_null($ruler_field_expected_value)) {
			// This means we only need to check the existence of the ruler field (which we are already sure about if you have a look at the previous lines)

			if ($this->fieldValueIsEmpty($field) && ! $this->fieldValueIsEmpty($ruler_field)) {
				// The field value is empty so we should invalidate the form
				$this->invalidateTheField($field, $error_msg);
			}

		} else {
			// A specific value of the ruler field is expected so we invalidate if the ruler field has that value
			if ($this->fieldHasTheValue($ruler_field, $ruler_field_expected_value) && $this->fieldValueIsEmpty($field)) {
				// The field value is empty while the ruler field has the expected value
				$this->invalidateTheField($field, $error_msg);
			}
		}
	}

	/**
	 * All the conditions that we check to see whether we should apply the
	 * custom validation rules or not.
	 *
	 * @param $field
	 * @return bool
	 */
	protected function shouldNotBeValidatedWithCustomRules($field)
	{
		return ($field->is_value_submission_empty($field->formId));
	}

	/**
	 * Enforces the custom validation rule
	 *
	 * @param $validation_rule
	 * @param $field
	 */
	protected function customValidationCheck($validation_rule, $field)
	{

		if ($this->shouldNotBeValidatedWithCustomRules($field)) return;

		$function_name = $validation_rule[1];
		if (! function_exists($function_name)) return;

		// Preparing extra arguments to pass to the custom function
		$args = [];
		$key = 1;
		while (array_key_exists(++$key, $validation_rule)) {
			$args[] = $validation_rule[$key];
		}

		// Pass the $args variable to the custom function if it's not empty
		$result = call_user_func($function_name, $field, $this->form, $args);
		if (is_bool($result) && $result) return; // True has been returned which means the input is valid

		$msg = (is_bool($result)) ? $this->generic_error_msg : $result;
		$this->invalidateTheField($field, $msg);
	}


	/**
	 * Enforces the changeNumRange validation rule
	 *
	 * @param $validation_rule
	 * @param $field
	 */
	protected function changeNumRangeValidationCheck($validation_rule, $field)
	{

		$ruler_field_key = $validation_rule[1];
		$ruler_field = $this->getTheFormField('key', $ruler_field_key);
		if (is_null($ruler_field)) return;

		$ruler_field_expected_value = (isset($validation_rule[4]) && $validation_rule[4] != '') ? $validation_rule[4] : null;

		$range_should_be_changed = false;

		if (is_null($ruler_field_expected_value)) {
			// This means we only need to check the existence of the ruler field (which we are already sure about if you have a look at the previous lines)
			if (! $this->fieldValueIsEmpty($ruler_field)) {
				$range_should_be_changed = true;
			}

		} else {
			// A specific value of the ruler field is expected so we change the valid range if the ruler field has that value
			if ($this->fieldHasTheValue($ruler_field, $ruler_field_expected_value)) {
				$range_should_be_changed = true;
			}
		}

		if ($range_should_be_changed) {

			$field->rangeHasChanged = true;
			if ($field->rangeMin != '') $field->rangeMin += $validation_rule[2];
			if ($field->rangeMax != '') $field->rangeMax += $validation_rule[3];

			$value_is_valid = $this->validate_range($field, rgpost("input_{$field->id}"));

			if (! $value_is_valid) {
				$this->invalidateTheField($field, $field->get_range_message());
			} else {
				$this->markTheFieldAsValid($field);
			}
		}
	}


	/**
	 * Validates the range of the number according to the field settings (a copy of the gravity forms original method).
	 *
	 * @param       $field
	 * @param array $value A decimal_dot formatted string
	 * @return false|true True on valid or false on invalid
	 */
	private function validate_range($field, $value)
	{

		if (! \GFCommon::is_numeric($value, 'decimal_dot')) {
			return false;
		}

		if ((is_numeric($field->rangeMin) && $value < $field->rangeMin) || (is_numeric($field->rangeMax) && $value > $field->rangeMax)) {
			return false;
		}

		return true;
	}


	public function getThisValidationRuleOfTheField($validation_rule, $field)
	{
		if (strpos($field->validationRules, $validation_rule) === false) return false;

		$validation_rules = $this->getFieldValidationRules($field);

		return array_filter($validation_rules, function ($rule) use ($validation_rule) {
			return $rule[0] == $validation_rule;
		});
	}


	protected function getFieldJquerySelector($field)
	{
		switch ($field->type) {
			case 'checkbox':
				return "[name^='input_{$field->id}.']";
				break;
			default:
				return "[name='input_{$field->id}']";
				break;
		}
	}


	public function addFrontEndNumberRangeChange($shortcode_string, $attributes, $content)
	{
		// We definitely need the form ID
		if (! isset($attributes['id'])) return $shortcode_string;

		$form_id = $attributes['id'];
		$this->form = \RGFormsModel::get_form_meta($form_id);

		foreach ($this->form['fields'] as $field) {

			if ($field->type == 'number' && $rules = $this->getThisValidationRuleOfTheField('changeNumRange', $field)) {

				foreach ($rules as $rule) {
					if (! $ruler_field = $this->getTheFormField('key', $rule[1])) continue;

					$form_suffix = "f{$form_id}_";
					$dep_field_suffix = $form_suffix . "i{$field->id}_";
					$ruler_field_suffix = $form_suffix . "i{$ruler_field->id}_";
					$desired_value_var_name = $ruler_field_suffix . 'desired_val_for_i' . $field->id;

					$dep_field_jquery_selector = $this->getFieldJquerySelector($field);
					// jQuery selector of the ruler field varies depending on the field type
					$ruler_field_jquery_selector = $this->getFieldJquerySelector($ruler_field);

					// Now we need to set the initial range real values (set on the form editor)
					// because they might have been changed on the fly as a result of numRangeChangeValidationCheck

					$fieldInitialRangeMin = $field->rangeMin;
					$fieldInitialRangeMax = $field->rangeMax;
					if ($field->rangeHasChanged) {
						$fieldInitialRangeMin = $field->rangeMin - $rule[2];
						$fieldInitialRangeMax = $field->rangeMax - $rule[3];
					}

					$js_code = $this->loadTemplateContent('number-range-change-front-end-code', compact(['rule', 'desired_value_var_name', 'dep_field_suffix', 'dep_field_jquery_selector', 'ruler_field_jquery_selector', 'ruler_field_suffix', 'dep_field_suffix', 'fieldInitialRangeMin', 'fieldInitialRangeMax',]));
					$shortcode_string .= $js_code;
				}
			}
		}

		return $shortcode_string;
	}

	/**
	 * Loads a template file into a variable without dumping anything.
	 *
	 * @param       $template_name
	 * @param array $data
	 * @return string
	 */
	protected function loadTemplateContent($template_name, $data = [])
	{
		ob_start();
		extract($data);
		$template_path = MIGHTY_GRAVITY_FORMS_PLUGIN_PATH . "template/{$template_name}.php";
		if (file_exists($template_path)) {
			include $template_path;
		}
		$template_content = ob_get_contents();
		ob_end_clean();

		return $template_content;
	}

	/**
	 * A function to process the `gf_notification_conditional_block` shortcode.
	 * This shortcode helps adding a conditional block of content to email notifications.
	 * The shortcode should have an attribute called `function`.
	 * The `function` attribute is the name of the callback that would determine whether
	 * the block should be shown on the email or not.
	 * Please note that all the attributes of the shortcode are passed to the callback
	 * as an associative array.
	 *
	 * @param            $atts
	 * @param  string    $content
	 * @return string
	 */
	public function notificationConditionalBlockShortcode($atts, $content = '')
	{
		// If no content is defined, we do not proceed with the process
		if ($content == '' || ! isset($atts['function']) || ! function_exists($atts['function'])) return '';

		return (call_user_func($atts['function'], $atts)) ? $content : '';
	}
}
