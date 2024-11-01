(function ($) {

    var ShowAlert = Backbone.View.extend({
        el: $('.alert-placeholder'),
        initialize: function (title, content, type) {

            if (!type) {
                type = 'success';
            }

            var $template = $('#tpba_alert').html();
            this.$el.html($template);

            this.$el.find('.tpba_alert').attr('class', 'tpba_alert tpba_alert--' + type);
            this.$el.find('strong').html(title);
            this.$el.find('p').html(content);

        }

    });

    var ProgressBar = Backbone.View.extend({
        el: $('.progress-placeholder'),
        initialize: function () {

            var $template = $('#tpba_progress').html();

            this.$el.hide().html($template);

            this.$progressBar = this.$el.find('.progress-bar');
            this.$progressMessage = this.$el.find('.progress_logs__message');
            this.$progressPercent = this.$el.find('.progress-percent');
            this.$progressTotal = this.$el.find('.count_all');
            this.$progressFileCount = this.$el.find('.count_file');
            this.total = 0;
            this.$el.fadeIn();
        },
        setTotal: function (total) {
            this.total = total;
            this.$progressTotal.text(total);
        },
        update: function (index, message, is_error) {

            if (is_error) {
                this.$progressMessage.addClass('msg-error');
            } else {
                this.$progressMessage.removeClass('msg-error');
            }

            this.$progressMessage.html(message);

            if (this.total) {

                var percent = (index * 100) / this.total;

                this.$progressFileCount.text(index);
                this.$progressPercent.text(parseInt(percent) + '%');
                this.$progressBar.width(percent + '%');
            }
        },
        remove: function () {
            this.$el.find('.tpba_progress').remove();
        }
    });

    var FormBackup = Backbone.View.extend({
        el: $('#tpba_dashboard'),
        events: {
            'click .js-backup-manually': 'onBackup',
        },
        initialize: function () {
            this.fileCount = 0;
            this.sessionId = 0;
            this.checkCron();
        },
        initProgressBar: function () {
            this.progressBar = new ProgressBar();
            return this.progressBar;
        },
        onBackup: function (e) {

            var $this = $(e.target);

            if ($this.hasClass('disabled')) {
                return false;
            }

            var r = confirm(tpba_var.confirm_backup);

            if (!r) {
                return false;
            }

            var progressBar = this.initProgressBar();

            var trackBackup = _.bind(this.track, this);

            Backbone.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpba_backup_connect',
                },
                beforeSend: function (e) {
                    $this.addClass('disabled');
                    $this.closest('.tpba_alert').remove();
                    progressBar.update(0, tpba_var.connecting);
                },
                success: function (response) {

                    if (response.success) {
                        progressBar.setTotal(response.total_count);
                        progressBar.update(0, response.data);
                        trackBackup.call(this, 0);

                    } else {
                        progressBar.update(0, response.data, true);
                    }
                }
            });

            e.preventDefault();
        },
        checkCron: function () {

            var trackBackup = _.bind(this.track, this);
            var progress = _.bind(this.initProgressBar, this);

            Backbone.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpba_backup_check_cron',
                },
                success: function (response) {

                    if (_.isObject(response)) {
                        if (response.continue) {
                            var progressBar = progress.call(this);
                            progressBar.setTotal(response.total_count);
                            progressBar.update(response.index, response.data);
                            trackBackup.call(this, response.index);
                        } else {
                            if (response.success) {
                                progressBar.remove();
                                new ShowAlert(tpba_var.done, response.data, 'success');
                            } else {
                                new ShowAlert(tpba_var.done, response.data, 'warning');
                            }
                        }
                    }

                }
            });

        },
        track: function () {

            var progressBar = this.progressBar;
            var trackBackup = _.bind(this.track, this);

            Backbone.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpba_backup_track',
                },
                complete: function (data, status) {

                    if (data.hasOwnProperty('responseJSON')) {

                        var response = data.responseJSON;

                        if (response.continue) {
                            progressBar.update(response.index, response.data);
                            trackBackup.call(this);
                        } else {
                            progressBar.remove();
                            if (response.success) {
                                new ShowAlert(tpba_var.done, response.data, 'success');
                            } else {
                                new ShowAlert(tpba_var.warning, response.data, 'warning');
                            }
                        }

                    } else if (data.responseText != '' && data.responseText != 0) {
                        new ShowAlert(tpba_var.warning, data.responseText, 'error');
                    }

                }
            });

        }
    });

    var FormRestore = Backbone.View.extend({
        el: $('#tpba_backups'),
        events: {
            'click .js-restore': 'onRestore',
        },
        initialize: function () {
            this.checkCron();
        },
        initProgressBar: function () {
            this.progressBar = new ProgressBar();
            return this.progressBar;
        },
        onRestore: function (e) {

            var $this = $(e.target);

            if ($this.hasClass('disabled')) {
                return false;
            }

            var r = confirm(tpba_var.confirm_restore);

            if (!r) {
                return false;
            }

            $this.closest('tr').addClass('current');

            var progressBar = this.initProgressBar();
            var trackRestore = _.bind(this.track, this);
            var session_id = $(e.target).data('session_id');

            var $body = $("html, body");
            $body.stop().animate({scrollTop: 0}, 500, 'swing');

            Backbone.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpba_restore_connect',
                    session_id: session_id
                },
                beforeSend: function (e) {
                    progressBar.update(0, tpba_var.connecting);
                    $('.js-restore').addClass('disabled').attr('disabled', '');
                },
                success: function (response) {

                    if (response.total_count > 0) {
                        progressBar.setTotal(response.total_count);
                        trackRestore.call(this, 0);
                    } else {
                        progressBar.setTotal(1);
                        progressBar.update(0, tpba_var.prepare_file_error, true);
                    }

                }
            });

            e.preventDefault();
        },
        checkCron: function () {

            var trackRestore = _.bind(this.track, this);
            var progress = _.bind(this.initProgressBar, this);

            Backbone.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpba_restore_check_cron',
                },
                success: function (response) {

                    if (_.isObject(response)) {
                        if (response.continue) {

                            $('.js-restore').addClass('disabled').attr('disabled', '');
                            var progressBar = progress.call(this);
                            progressBar.setTotal(response.total_count);
                            progressBar.update(response.index, response.data);
                            trackRestore.call(this, response.index);

                        } else {

                            $('.js-restore').removeClass('disabled').removeAttr('disabled');

                            if (response.success) {
                                progressBar.remove();
                                new ShowAlert(tpba_var.done, response.data, 'success');
                            } else {
                                new ShowAlert(tpba_var.warning, response.data, 'warning');
                            }
                        }
                    }

                }
            });

        },
        track: function () {

            var progressBar = this.progressBar;
            var trackRestore = _.bind(this.track, this);

            Backbone.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpba_restore_track'
                },
                complete: function (data, status) {

                    if (data.hasOwnProperty('responseJSON')) {

                        var response = data.responseJSON;

                        if (response.continue) {
                            progressBar.update(response.index, response.data);
                            trackRestore.call(this);
                        } else {
                            if (response.success) {
                                progressBar.remove();
                                new ShowAlert(tpba_var.done, response.data, 'success');
                            } else {
                                new ShowAlert(tpba_var.done, response.data, 'warning');
                            }
                        }

                    }
                }
            });
        }
    });

    if ($('#tpba_dashboard').length) {
        new FormBackup();
    }

    if ($('#tpba_backups').length) {
        new FormRestore();
    }

    $('.tpba_abort').on('click', function (e) {

        Backbone.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                'action': 'tpba_abort'
            },
            success: function (res) {
                console.log(res);
            }
        });

        e.preventDefault();
    });

})(jQuery);