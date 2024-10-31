var loginInProgress = false;

function oneClickLoginOnSuccess( googleUser ) {
    if ( ! loginInProgress ) {
        loginInProgress = true;

        // Remove any previous One Click Login errors.
        jQuery( '.bpw_ocl_error' ).remove();

        jQuery.ajax({
            url: bpw_ocl.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                'action': 'bpw_ocl_ajax_login',
                'idToken': googleUser.getAuthResponse().id_token,
                'nonce': bpw_ocl.nonce
            },
            success: function ( response ) {
                if ( response.success && response.redirectUrl ) {
                    jQuery('.bpw_ocl_login_button').hide();

                    // After logging in, immeadiately sign out so the user won't be automatically logged in when he goes to the login page (to log out for example).
                    gapi.auth2.getAuthInstance().signOut();

                    window.location.href = response.redirectUrl;
                }
                else if ( response.errorMessage ) {
                    // Prepend a <br> to the error message if there's other error messages showing.
                    if ( jQuery( '#login_error' ).text().length  ) {
                        response.errorMessage = response.errorMessage.replace( '<strong>', '<br><strong>' );
                    }
                    else if ( ! jQuery( '#login_error' ).length ) {
                        // There's no other error messages showing so create the login_error div.
                        jQuery('<div id="login_error"></div>').insertAfter('.bpw_ocl_login_button');
                    }

                    jQuery( '#login_error' ).append( response.errorMessage );

                    // Automatically log out so the user can try again if he selected the wrong account for example.
                    gapi.auth2.getAuthInstance().signOut();
                }

                loginInProgress = false;
            }
        });
    }
}

function oneClickLoginOnFailure() {
    console.log("One Click Login Error");
}

function oneClickLoginInit() {
    gapi.load('auth2', function() {
        gapi.auth2.init({
            client_id: bpw_ocl.client_id
        });

        gapi.signin2.render('oneClickLoginSignInButton', {
            'scope': 'profile email',
            'width': 320,
            'height': 50,
            'longtitle': true,
            'theme': 'dark',
            'ux_mode': 'redirect',
            'redirect_uri': bpw_ocl.login_url,
            'onsuccess': oneClickLoginOnSuccess,
            'onfailure': oneClickLoginOnFailure
          });
    });
  }