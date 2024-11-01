(function ($) {

    $(function () {

        var Token = Backbone.Model.extend({
            defaults: {
                token: '',
                email: ''
            },
            validate: function (attributes) {

                if (_.isEmpty(attributes.email)) {
                    return tpba_var.invalid_email;
                }

                if (_.isEmpty(attributes.token)) {
                    return tpba_var.invalid_token;
                }
            }
        });

        var InstallerView = Backbone.View.extend({
            el: $('.tp_installer'),
            events: {
                'click [data-forward]': 'onForward',
                'click [data-back]': 'onBack',
                'click .js-get-token': 'onRegisterToken',
                'click .js-validate-token': 'onValidateToken',
                'click .js-change-token': 'onChangeToken',
                'click .cancel_update_token': 'onCancelUpdate',
                'click .redirect_alert': 'onJoinNow'

            },
            initialize: function () {

                this.$steps = this.$el.find('[data-step]');
                this.$forms = this.$el.find('[data-form]');

                this.stepCurrent = this.$el.data('step') ? parseInt(this.$el.data('step')) : 0;
                this.stepCount = this.$steps.length;

                this.move(this.stepCurrent);



            },
            move: function (index) {
                if (index < this.stepCount && index >= 0) {
                    this.$forms.removeClass('active');
                    this.$steps.removeClass('active');
                    this.$steps.eq(index).addClass('active');
                    this.$form = this.$el.find('[data-form="' + index + '"]');
                    this.$form.addClass('active');
                    //Save step current
                    this.stepCurrent = parseInt(index);
                    this.$el.attr('data-step', index);

                    if (index === 2) {
                        this.$steps.parent().hide();
                    } else {
                        this.$steps.parent().show();
                    }
                }
            },
            onForward: function (e) {

                var jumpIndex = $(e.target).attr('data-forward');

                if (_.isEmpty(jumpIndex)) {
                    jumpIndex = this.stepCurrent + 1;
                }

                this.move(jumpIndex);

                e.preventDefault();
                e.stopPropagation();
            },
            onBack: function (e) {

                var jumpIndex = $(e.target).attr('data-back');

                if (_.isEmpty(jumpIndex)) {
                    jumpIndex = this.stepCurrent - 1;
                }

                this.move(jumpIndex);

                e.preventDefault();
                e.stopPropagation();
            },
            onRegisterToken: function (e) {

                var $forms = this.$forms;
                var email = $forms.find('input[name="email"]').val();
                var nonce = $forms.find('#nonce_field_get_token').val();

                this.model = new Token();
                this.model.set({'email': email, nonce: nonce, action: 'tpba_register_token', token: '0'}, {validate: true});



                if (this.model.isValid()) {
                    var formSuccess = _.bind(this.formSuccess, this);
                    var nextStep = _.bind(this.move, this);
                    var printError = _.bind(this.printErrors, this);

                    Backbone.ajax({
                        method: 'POST',
                        url: ajaxurl,
                        data: this.model.toJSON(),
                        beforeSend: _.bind(this.formSending, this),
                        success: function (res) {
                            if(typeof res=='string'){
                                res = JSON.parse(res);
                            }
                            
                            formSuccess.call();

                            if (res.status == 200) {
                                nextStep.call(this, 1);

                                $forms.find('input[name="email"]').val(res.data.email).change();
                            } else {
                                printError.call(this, res.errors);
                            }

                        }
                    });

                } else {
                    this.printErrors(this.model.validationError);
                }

                e.preventDefault();
                e.stopPropagation();
            },
            onValidateToken: function (e) {

                var email = this.$form.find('input[name="email"]').val();
                var nonce = this.$form.find('#nonce_field_validate_token').val();
                var token = this.$form.find('input[name="token"]').val();

                this.model = new Token();
                this.model.set({'email': email, nonce: nonce, action: 'tpba_validate_token', token: token}, {validate: true});

                var $frmSuccess = this.$el.find('.frm-change-code');

                if (this.model.isValid()) {

                    var formSuccess = _.bind(this.formSuccess, this);
                    var nextStep = _.bind(this.move, this);
                    var printError = _.bind(this.printErrors, this);

                    Backbone.ajax({
                        method: 'POST',
                        url: ajaxurl,
                        data: this.model.toJSON(),
                        beforeSend: _.bind(this.formSending, this),
                        success: function (res) {

                            formSuccess.call();
                            
                            if(typeof res=='string'){
                                res = JSON.parse(res);
                            }
                            
                            if (res.status == 200) {

                                nextStep.call(this, 2);

                                if (res.data.hasOwnProperty('token')) {
                                    $frmSuccess.find('input[name="token"]').val(res.data.token);
                                    $frmSuccess.find('input[name="email"]').val(res.data.email);
                                    $frmSuccess.hide();
                                    
                                    /**
                                     * Count down
                                     */
                                    $('.redirect_alert').show();
                                    var countDown = 5;
                                    var interval = setInterval(function () {
                                        $('.redirect_alert span').text(countDown);

                                        if (countDown == 0) {
                                            clearInterval(interval);
                                            location.reload();
                                        }

                                        countDown--;
                                    }, 1000);
                                }

                            } else {
                                printError.call(this, res.errors);
                            }
                        }
                    });

                } else {
                    this.printErrors(this.model.validationError);
                }

                e.preventDefault();
                e.stopPropagation();
            },
            setUpdateTokenStatus: function (status) {
                this.isUpdateToken = status;
            },
            onChangeToken: function (e) {

                var $this = $(e.target);

                var $form = this.$form;

                var $token = $form.find('input[name="token"]');

                if (!this.isUpdateToken) {

                    this.isUpdateToken = true;

                    $token.removeAttr('disabled');
                    $this.attr('data-value', $this.text());
                    $this.text($this.data('update'));
                    $token.attr('data-token', $token.val()).val('').focus();
                    $form.find('.cancel_update_token,.create_new_token').css('display', 'inline-block');
                } else {

                    var token = $token.val();
                    var email = $form.find('input[name="email"]').val();
                    var nonce = $form.find('#nonce_field_change_token').val();
                    this.model = new Token();
                    this.model.set({email: email, nonce: nonce, action: 'tpba_validate_token', token: token}, {validate: true});

                    if (this.model.isValid()) {
                        var formSuccess = _.bind(this.formSuccess, this);

                        var $button = $form.find('button');

                        var statusToken = _.bind(this.setUpdateTokenStatus, this);

                        Backbone.ajax({
                            method: 'POST',
                            url: ajaxurl,
                            data: this.model.toJSON(),
                            beforeSend: _.bind(this.formSending, this),
                            success: function (res) {

                                formSuccess.call();

                                if (res.status == 200) {

                                    statusToken.call(this, false);
                                    $form.find('input[name="token"]').attr('disabled', '');
                                    $button.text($button.attr('data-value'));
                                    $form.find('.validate-result--failure').removeClass('validate-result--failure');
                                    $form.find('.cancel_update_token,.create_new_token').css('display', 'none');
                                    $form.find('.redirect_alert').show();
                                } else if (res.status == 400) {
                                    $form.find('.statusToken').hide();
                                } else {
                                    $form.find('.validate-result').addClass('validate-result--failure');
                                    $form.find('.cancel_update_token,.create_new_token,.redirect_alert').css('display', 'none');
                                }

                            }
                        });

                    } else {
                        $token.focus();
                        this.printErrors(this.model.validationError);
                    }
                }

                e.preventDefault();
                e.stopPropagation();
            },
            onCancelUpdate: function (e) {
                var $form = this.$form;
                var $button = $form.find('button');
                var $token = $form.find('input[name="token"]');
                $token.val($token.attr('data-token')).attr('disabled', '');
                $button.text($button.attr('data-value'));
                $form.find('.tp-errors').empty();
                $form.find('.cancel_update_token,.create_new_token').css('display', 'none');
                $form.find('.validate-result--failure').removeClass('validate-result--failure');
                this.isUpdateToken = false;
                e.preventDefault();
                e.stopPropagation();
            },
            onJoinNow: function (e) {
                location.reload();
                e.preventDefault();
            },
            formSending: function (e) {

                var html = '<div class="tp-spinner">\n\
                            <div class="bounce1"></div>\n\
                            <div class="bounce2"></div>\n\
                            <div class="bounce3"></div>\n\
                          </div>';

                var $button = this.$form.find('button');

                this.$form.find('input,textarea').attr('readonly', '');
                var oldtext = $button.text();
                $button.attr('data-text', oldtext).attr('disabled', '').html(html);

            },
            formSuccess: function (e) {
                this.$form.find('input,textarea').removeAttr('readonly');
                this.$form.find('.tp-errors').empty();
                var $button = this.$form.find('button');
                var oldtext = $button.attr('data-text');
                $button.text(oldtext).removeAttr('disabled');
            },
            onError: function (model, error) {
                this.printErrors(error);
            },
            printErrors: function (errors) {

                var html = '';
                if (_.isString(errors)) {
                    html = '<li>' + errors + '</li>';
                } else {
                    _.each(errors, function (error) {
                        html += '<li>' + error + '</li>';
                    });
                }

                this.$form.find('.tp-errors').html(html);
            },
        });


        var install = new InstallerView();

    });



})(jQuery);