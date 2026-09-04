var recaptchaRendered = false;

$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip();

    if ($.validator) {
        $.validator.addMethod('strongPassword', function(value, element) {
            return this.optional(element) || (value.length >= 8 && /[a-z]/.test(value) && /[A-Z]/.test(value) && /\d/.test(value));
        }, 'Use at least 8 characters with upper- and lower-case letters and a number.');
        $.validator.addMethod('safeUsername', function(value, element) {
            return this.optional(element) || /^[A-Za-z0-9._-]+$/.test(value);
        }, 'Use only letters, numbers, dots, underscores, or hyphens.');
    }

    // registration form steps start
    if ($('#business_register_form').length) {
        var form = $('#business_register_form').show();
        form.steps({
            headerTag: 'h3',
            bodyTag: 'fieldset',
            transitionEffect: 'slideLeft',
            labels: {
                finish: LANG.register,
                next: LANG.next,
                previous: LANG.previous,
            },
            onStepChanging: function(event, currentIndex, newIndex) {
                // Allways allow previous action even if the current form is not valid!
                if (currentIndex > newIndex) {
                    return true;
                }
                // Needed in some cases if the user went back (clean up)
                if (currentIndex < newIndex) {
                    // To remove error styles
                    form.find('.body:eq(' + newIndex + ') label.error').remove();
                    form.find('.body:eq(' + newIndex + ') .error').removeClass('error');
                }
                form.validate().settings.ignore = ':disabled,:hidden';
                var valid = form.valid();
                if (!valid) {
                    var firstError = form.find('.error:visible, [aria-invalid="true"]:visible').first();
                    if (firstError.length) {
                        firstError.trigger('focus');
                    }
                }
                return valid;
            },
            onStepChanged: function(event, currentIndex, priorIndex) {
                // Render reCAPTCHA on last step
                if (currentIndex === 2 && !recaptchaRendered) { // change 2 to your last step index
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.render('recaptcha-container', {
                            'sitekey': window.RECAPTCHA_SITE_KEY
                        });
                        recaptchaRendered = true;
                    }
                }
            },
            onFinishing: function(event, currentIndex) {
                form.validate().settings.ignore = ':disabled';
                return form.valid();
            },
            onFinished: function(event, currentIndex) {
                form.find('a[href="#finish"]').addClass('disabled').attr('aria-disabled', 'true');
                form.submit();
            },
        });
        form.find('a[href="#previous"]').addClass('tw-dw-btn');
        form.find('a[href="#next"]').addClass('tw-dw-btn tw-dw-btn-primary');
        form.find('a[href="#finish"]').addClass('tw-dw-btn tw-dw-btn-primary');
    }
    // registration form steps end

    //Date picker
    $('.start-date-picker').datepicker({
        autoclose: true,
        endDate: 'today',
    });

    $('form#business_register_form').validate({
        errorPlacement: function(error, element) {
            if (element.parent('.input-group').length) {
                error.insertAfter(element.parent());
            } else if (element.hasClass('input-icheck') && element.parent().hasClass('icheckbox_square-blue')) {
                error.insertAfter(element.parent().parent().parent());
            } else {
                error.insertAfter(element);
            }
        },
        rules: {
            name: 'required',
            email: {
                required: true,
                email: true,
                remote: {
                    url: window.CASHERP_CHECK_EMAIL_URL || '/business/register/check-email',
                    type: 'post',
                    data: {
                        email: function() {
                            return $('#email').val();
                        },
                        is_disposable_email: true,
                    }
                },
            },
            password: {
                required: true,
                minlength: 8,
                strongPassword: true,
            },
            confirm_password: {
                equalTo: '#password',
            },
            username: {
                required: true,
                minlength: 4,
                maxlength: 50,
                safeUsername: true,
                remote: {
                    url: window.CASHERP_CHECK_USERNAME_URL || '/business/register/check-username',
                    type: 'post',
                    data: {
                        username: function() {
                            return $('#username').val();
                        },
                    },
                },
            },
            website: {
                url: true,
            },
        },
        messages: {
            name: LANG.specify_business_name,
            password: {
                minlength: LANG.password_min_length,
            },
            confirm_password: {
                equalTo: LANG.password_mismatch,
            },
            username: {
                remote: LANG.invalid_username,
            },
            // Do not override email.remote; we use server response via dataFilter
        },
    });

    $('#business_logo').fileinput({
        showUpload: false,
        showPreview: false,
        browseLabel: LANG.file_browse_label,
        removeLabel: LANG.remove,
    });
});
