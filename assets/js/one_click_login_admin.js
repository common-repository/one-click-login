jQuery( document ).ready(function() {
    if ( bpw_ocl.wordpress_version_past_5 ) {
        var tabItem = '.cf-container__tabs-item';
        var radioAndGroupButtons = '.cf-complex__inserter-button, .cf-radio__input';
    }
    else {
        var tabItem = '.carbon-tabs-nav li';
        var radioAndGroupButtons = '.carbon-button a.button, .bpw_ocl_domain_group .carbon-radio-list label';
    }

    // Toggle first tab.
    if ( bpw_ocl.wordpress_version_past_5 ) {
        jQuery( '.cf-container__tabs-item' ).eq(0).trigger( 'click' );
    }
    else {
        jQuery( '#carbon_fields_container_one_click_login ' + tabItem ).eq(0).find('a').get(0).click();
    }

    changeButtonText();
    disablePremiumInputs();
    addTitleForPatternFields();
    updateQueryParameterSpan();
    addClarificationsToUserList();

    jQuery( '#carbon_fields_container_one_click_login' ).on( 'click', radioAndGroupButtons, function() {
        // Execute the code after a short delay so Carbon Fields have time to create the elements.
        setTimeout( function() {
            disablePremiumInputs();
            addTitleForPatternFields();
            changeButtonText();
        }, 50);
    });

    jQuery( '#carbon_fields_container_one_click_login' ).on( 'click', '.one_click_login_settings_tab', function( event ) {
        event.preventDefault();

        if ( bpw_ocl.wordpress_version_past_5 ) {
            jQuery( '.cf-container__tabs-item' ).eq(0).trigger( 'click' );
        }
        else {
            jQuery( '#carbon_fields_container_one_click_login ' + tabItem ).eq(0).find('a').get(0).click();
        }

        return false;
    });

    jQuery( '#carbon_fields_container_one_click_login' ).on( 'click', '.one_click_login_ignored_users_tab', function( event ) {
        event.preventDefault();

        if ( bpw_ocl.wordpress_version_past_5 ) {
            jQuery( '.cf-container__tabs-item' ).eq(1).trigger( 'click' );
        }
        else {
            jQuery( '#carbon_fields_container_one_click_login ' + tabItem ).eq(1).find('a').get(0).click();
        }

        return false;
    });

    jQuery( '#carbon_fields_container_one_click_login' ).on( 'click', '.one_click_login_instructions_tab', function( event ) {
        event.preventDefault();

        if ( bpw_ocl.wordpress_version_past_5 ) {
            jQuery( '.cf-container__tabs-item' ).eq(2).trigger( 'click' );
        }
        else {
            jQuery( '#carbon_fields_container_one_click_login ' + tabItem ).eq(2).find('a').get(0).click();
        }

        return false;
    });

    jQuery( '#carbon_fields_container_one_click_login' ).on( 'keyup', '.bpw_ocl_visibility_query_string_parameter input', function( event ) {
        // Remove non-alphanumeric characters.
        jQuery(this).val(jQuery(this).val().replace(/[^a-zA-Z0-9\-_]+/g, ''));

        updateQueryParameterSpan();
        
    });

    // Disable "Save Changes" button when on the Instructions or Import / Export tab.
    jQuery( '#carbon_fields_container_one_click_login' ).on( 'click', tabItem, function( event ) {
        if ( jQuery( this ).index() === 0 || jQuery( this ).index() === 1 ) {
            jQuery( '#carbon_fields_container_one_click_login').parents( '#post-body' ).find( '#publish' ).prop( 'disabled', false );
        }
        else {
            jQuery( '#carbon_fields_container_one_click_login').parents( '#post-body' ).find( '#publish' ).prop( 'disabled', 'disabled' );
        }
    });

    jQuery( '#carbon_fields_container_one_click_login' ).on( 'click', '.bpw_ocl_import_settings', function( event ) {
        event.preventDefault();

        var importData = jQuery('.bpw_ocl_import_settings_container textarea').val();
        if ( importData.length ) {
            jQuery('.bpw_ocl_import_settings').prop( 'disabled', 'disabled' );
            
            jQuery.ajax({
                url: bpw_ocl.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    'action': 'bpw_ocl_ajax_import',
                    'import_data': importData,
                    'nonce': bpw_ocl.nonce
                },
                success: function (response) {
                    if ( response.redirectUrl ) {
                        window.location.href = response.redirectUrl;
                    }
                    else {
                        jQuery('.bpw_ocl_import_settings').prop( 'disabled', false );
                    }
                }
            });
        }

        return false;
    });

    // If there's error or success messages on the url, set the form action to the default one so the url will be fixed after saving.
    if ( bpw_ocl.carbon_fields_form_action ) {
        jQuery( '.container-carbon_fields_container_one_click_login' ).parents( 'form' ).attr( 'action', bpw_ocl.carbon_fields_form_action );
    }
});

function updateQueryParameterSpan() {
    jQuery( '.visibility-query-parameter' ).html( jQuery( '.bpw_ocl_visibility_query_string_parameter input' ).val() );
}

function disablePremiumInputs() {
    if ( ! bpw_ocl.premium ) {
        // Disable fields whose parent has .bpw_ocl_disable_for_non_premium if the this is not the premium version.
        jQuery( '#carbon_fields_container_one_click_login .bpw_ocl_disable_for_non_premium input:text' ).each( function() {
            jQuery( this ).prop( 'readonly', 'readonly' );
        });

        jQuery( '#carbon_fields_container_one_click_login .bpw_ocl_disable_for_non_premium select' ).each( function() {
            jQuery( this ).prop( 'disabled', 'disabled' );
        });

        jQuery( '#carbon_fields_container_one_click_login .bpw_ocl_disable_for_non_premium input:radio' ).each( function() {
            jQuery( this ).prop( 'disabled', 'disabled' );
        });

        jQuery( '#carbon_fields_container_one_click_login .bpw_ocl_gmail_default_value input' ).each( function() {
            jQuery( this ).val('@gmail.com');
        });
    }

    // Call this function again if allow_new_user_registering field's value changes.
    jQuery('#carbon_fields_container_one_click_login').find('select').each( function() {
        if ( jQuery( this ).attr( 'name' ).includes( 'bpw_ocl_allow_new_user_registering' ) ) {
            jQuery( this ).not('[onchange]').attr( 'onchange', 'disablePremiumInputs(); setTimeout( function() { disablePremiumInputs() }, 50);' );
        }
    });
}

// Calls syntax checker when input changes on text fields that have pattern attribute.
function addTitleForPatternFields() {
    jQuery( '#carbon_fields_container_one_click_login' ).find( 'input[type="text"]' ).filter( '[pattern]' ).each( function() {
        if ( jQuery( this ).parent().parent().hasClass( 'bpw_ocl_domain_name' ) ) {
            jQuery( this ).attr( 'oninput', 'syntaxCheckerDomainName(this)' );
        }
        else if ( jQuery( this ).parent().parent().hasClass( 'carbon-field carbon-text bpw_ocl_email_address' ) ) {
            jQuery( this ).attr( 'oninput', 'syntaxCheckerEmailAddress(this)' );
        }
    });
}

// Shows custom validity message if the syntax of the text is wrong.
function syntaxCheckerDomainName( element ) {
    var domain = jQuery(element).val();

    // Check the syntax of the value.
    if ( new RegExp( element.pattern ).test( element.value ) === false ) {
        element.setCustomValidity( 'Field must begin with a @-sign and contain atleast one dot. Example: @gmail.com' );
    }
    // Check for duplicate domains if this field is a domain field.
    else if ( jQuery(element).parents('.bpw_ocl_domain_name').length && duplicateValuesFound( domain, '.bpw_ocl_domain_name' ) ) {
        element.setCustomValidity( 'Duplicate domain found. All domains must be unique.' );
    }
    else {
        element.setCustomValidity( '' );
    }
}

// Shows custom validity message if the syntax of the text is wrong.
function syntaxCheckerEmailAddress( element ) {
    var emailAddress = jQuery(element).val();

    // Check the syntax of the value.
    if ( new RegExp( element.pattern ).test( element.value ) === false ) {
        element.setCustomValidity( 'Field must be a valid email address. Example: mycompany@gmail.com or support@mycompany.com' );
    }
    // Check for duplicate email addresses if this field is a domain field.
    else if ( jQuery(element).parents('.bpw_ocl_email_address').length && duplicateValuesFound( emailAddress, '.bpw_ocl_email_address' ) ) {
        element.setCustomValidity( 'Duplicate email address found. All email addresses must be unique.' );
    }
    else {
        element.setCustomValidity( '' );
    }
}

function addClarificationsToUserList() {
    jQuery( '.bpw_ocl_registered_with_one_click_login' ).find( 'label' ).each( function() {
        jQuery( this ).append( ' <b>' + bpw_ocl.registered_with_one_click_login + '</b>' );
    });
}

function changeButtonText() {
    if ( bpw_ocl.wordpress_version_past_5 ) {
        jQuery( '.bpw_ocl_whitelist_group > .cf-field__body > .cf-complex__actions > .cf-complex__inserter>  button.cf-complex__inserter-button' ).html( bpw_ocl.add_email_address );
        jQuery( '.bpw_ocl_whitelist_group > .cf-field__body > .cf-complex__placeholder > .cf-complex__inserter > button.cf-complex__inserter-button' ).html( bpw_ocl.add_email_address );
    
        jQuery( '.bpw_ocl_domain_group > .cf-field__body > .cf-complex__actions > .cf-complex__inserter > button.cf-complex__inserter-button' ).html( bpw_ocl.add_group );
        jQuery( '.bpw_ocl_domain_group > .cf-field__body > .cf-complex__placeholder > .cf-complex__inserter > button.cf-complex__inserter-button' ).html( bpw_ocl.add_group );
    }
    else {
        jQuery( '.bpw_ocl_domain_group > .field-holder > .carbon-subcontainer > .carbon-actions > .carbon-button:not(.carbon-button-collapse-all) > a.button' ).html( bpw_ocl.add_email_address );
        jQuery( '.bpw_ocl_domain_group > .field-holder > .carbon-subcontainer > .carbon-actions > .carbon-button:not(.carbon-button-collapse-all) > a.button' ).html( bpw_ocl.add_group );
    }
}

function duplicateValuesFound( value, htmlClass ) {
    var uniqueAmount = 0;
    jQuery( '#carbon_fields_container_one_click_login ' + htmlClass + ' input' ).each( function() {
        if ( jQuery( this ).val() === value ) {
            uniqueAmount++;
        }
    });

    if ( uniqueAmount > 1 ) {
        return true;
    }
    else {
        return false;
    }
}
