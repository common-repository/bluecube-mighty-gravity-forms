<?php
/**
 * This is the JS code needed for Number fields whith the `changeNumRange` validation rule.
 * It updates the Number field error message on any interaction with the ruler input.
 */
?>
<script>
    jQuery(document).ready(function () {

        var <?php echo $desired_value_var_name; ?> = <?php echo (isset($rule[4]) && $rule[4] != '') ? "'$rule[4]'" : 'null'; ?>;

        var <?php echo $dep_field_suffix; ?>dependent_field = jQuery("<?php echo $dep_field_jquery_selector; ?>").first();
        var <?php echo $dep_field_suffix; ?>ruler_field = jQuery("<?php echo $ruler_field_jquery_selector; ?>"); // TODO: we might need to remove the first filter
        var <?php echo $dep_field_suffix; ?>instruction = <?php echo $dep_field_suffix; ?>dependent_field.parents('li.gfield').find('.instruction, .gfield_description').first();

        var <?php echo $dep_field_suffix; ?>init_min = <?php echo $fieldInitialRangeMin; ?>;
        var <?php echo $dep_field_suffix; ?>init_max = <?php echo $fieldInitialRangeMax; ?>;
        var <?php echo $dep_field_suffix; ?>required_min = <?php echo $fieldInitialRangeMin + $rule[2]; ?>;
        var <?php echo $dep_field_suffix; ?>required_max = <?php echo $fieldInitialRangeMax + $rule[3]; ?>;

        <?php echo $dep_field_suffix; ?>ruler_field.on('keyup change', function () {

            var curr_el = jQuery(this);
            var <?php echo $ruler_field_suffix; ?>curr_val = (curr_el.val() == '') ? null : curr_el.val();

            switch (curr_el.attr('type')) {
                case 'checkbox':
                    if (<?php echo $desired_value_var_name; ?> == null
                )
                {
                    // We only need one checkbox to be checked
                    <?php echo $ruler_field_suffix; ?>curr_val = (jQuery("<?php echo $ruler_field_jquery_selector; ?>:checked").length) ? 1 : null;
                }
                else
                {
                    <?php echo $ruler_field_suffix; ?>curr_val = (jQuery("<?php echo $ruler_field_jquery_selector; ?>:checked").filter(function (index) {
                        return jQuery(this).val() == <?php echo $desired_value_var_name; ?>;
                    }).length) ? <?php echo $desired_value_var_name; ?> : null;
                }
                    break;
            }

            var <?php echo $dep_field_suffix; ?>now_min = <?php echo $dep_field_suffix; ?>required_min;
            var <?php echo $dep_field_suffix; ?>now_max = <?php echo $dep_field_suffix; ?>required_max;

            if (
                (<?php echo $desired_value_var_name; ?> == null && <?php echo $ruler_field_suffix; ?>curr_val == null)
                ||
            (<?php echo $desired_value_var_name; ?> != null && <?php echo $ruler_field_suffix; ?>curr_val != <?php echo $desired_value_var_name; ?>)
            )
            {

                // We should revert the range amounts to the initial values
                var <?php echo $dep_field_suffix; ?>now_min = <?php echo $dep_field_suffix; ?>init_min;
                var <?php echo $dep_field_suffix; ?>now_max = <?php echo $dep_field_suffix; ?>init_max;
            }

            <?php echo $dep_field_suffix; ?>instruction.find('strong').first().text(<?php echo $dep_field_suffix; ?>now_min);
            <?php echo $dep_field_suffix; ?>instruction.find('strong').last().text(<?php echo $dep_field_suffix; ?>now_max);
        });
    });
</script>
