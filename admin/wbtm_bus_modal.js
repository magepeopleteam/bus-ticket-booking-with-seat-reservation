/**
 * WBTM Bus Modal - JavaScript
 * Modern Multi-Step Modal for Bus Add/Edit
 * Version: 1.0.0
 */
(function($) {
    'use strict';

    const WBTMModal = {
        currentStep: 0,
        totalSteps: 12,
        isEditing: false,
        hasChanges: false,

        init: function() {
            // First, move the modal wrapper out of the metabox and into the body
            // This ensures it's not constrained by the metabox container
            const modalWrapper = $('#wbtmModalWrapper');
            if (modalWrapper.length) {
                modalWrapper.appendTo('body');
            }
            
            this.bindEvents();
            this.initMediaUploader();
            this.updateProgress();
            this.initAutocomplete();
            this.syncReviewData();
            
            // Auto-open modal on bus add/edit pages
            if ($('.wbtm-modal-wrapper').length) {
                this.hasChanges = false;
                this.updateFooterButtons();
                // IMPORTANT: Actually open the modal!
                this.openModal();
            }
        },

        bindEvents: function() {
            const self = this;

            // Open modal
            $(document).on('click', '#wbtmOpenModal', function(e) {
                e.preventDefault();
                self.openModal();
            });

            // Close modal
            $(document).on('click', '#wbtmModalClose, #wbtmModalOverlay', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Prevent closing when clicking inside modal
            $(document).on('click', '.wbtm-modal-container', function(e) {
                e.stopPropagation();
            });

            // Step navigation
            $(document).on('click', '.wbtm-step-tab', function(e) {
                e.preventDefault();
                const step = parseInt($(this).data('step'));
                self.goToStep(step);
            });

            // Next button
            $(document).on('click', '#wbtmNextBtn', function(e) {
                e.preventDefault();
                if (self.currentStep < self.totalSteps - 1) {
                    self.goToStep(self.currentStep + 1);
                }
            });

            // Previous button
            $(document).on('click', '#wbtmPrevBtn', function(e) {
                e.preventDefault();
                if (self.currentStep > 0) {
                    self.goToStep(self.currentStep - 1);
                }
            });

            // Save button
            $(document).on('click', '#wbtmSaveBtn', function(e) {
                e.preventDefault();
                self.saveBus();
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (!$('#wbtmModalWrapper').hasClass('open')) return;
                
                if (e.key === 'Escape') {
                    self.closeModal();
                } else if (e.key === 'ArrowRight' && e.altKey) {
                    if (self.currentStep < self.totalSteps - 1) {
                        self.goToStep(self.currentStep + 1);
                    }
                } else if (e.key === 'ArrowLeft' && e.altKey) {
                    if (self.currentStep > 0) {
                        self.goToStep(self.currentStep - 1);
                    }
                }
            });

            // Form change detection
            $(document).on('change input', '.wbtm-modal input, .wbtm-modal select, .wbtm-modal textarea', function() {
                self.hasChanges = true;
                self.syncReviewData();
            });

            // Route stop management
            $(document).on('click', '.wbtm-add-stop-btn', function(e) {
                e.preventDefault();
                self.addRouteStop();
            });

            // Service management
            $(document).on('click', '.wbtm-add-service-btn', function(e) {
                e.preventDefault();
                self.addService();
            });

            // Date type toggle
            $(document).on('change', '#wbtmDateType', function() {
                const value = $(this).val();
                if (value === 'particular') {
                    $('#wbtmParticularFields').slideDown(200);
                    $('#wbtmRepeatedFields').slideUp(200);
                } else {
                    $('#wbtmParticularFields').slideUp(200);
                    $('#wbtmRepeatedFields').slideDown(200);
                }
            });

            // Seat type toggle
            $(document).on('change', '#wbtmSeatType', function() {
                const value = $(this).val();
                if (value === 'wbtm_seat_plan') {
                    $('#wbtmSeatPlanConfig').slideDown(200);
                } else {
                    $('#wbtmSeatPlanConfig').slideUp(200);
                }
            });
        },

        openModal: function() {
            $('#wbtmModalWrapper').addClass('open').show();
            $('body').addClass('wbtm-modal-open');
            this.goToStep(0);
            this.hasChanges = false;
        },

        closeModal: function() {
            if (this.hasChanges) {
                if (!confirm(wbtmModal.i18n.closeConfirm || 'You have unsaved changes. Are you sure you want to close?')) {
                    return;
                }
            }
            $('#wbtmModalWrapper').removeClass('open').hide();
            $('body').removeClass('wbtm-modal-open');
        },

        goToStep: function(step) {
            // Validate previous step if moving forward
            if (step > this.currentStep) {
                if (!this.validateStep(this.currentStep)) {
                    return false;
                }
            }

            this.currentStep = step;
            
            // Update panels
            $('.wbtm-step-panel').removeClass('active');
            $(`.wbtm-step-panel[data-step="${step}"]`).addClass('active');

            // Update tabs
            $('.wbtm-step-tab').removeClass('active done');
            $('.wbtm-step-tab').each(function(index) {
                if (index < step) {
                    $(this).addClass('done');
                } else if (index === step) {
                    $(this).addClass('active');
                }
            });

            // Update progress bar
            this.updateProgress();

            // Update footer buttons
            this.updateFooterButtons();

            // Sync review data on last step
            if (step === this.totalSteps - 1) {
                this.syncReviewData();
            }

            return true;
        },

        updateProgress: function() {
            const progress = ((this.currentStep + 1) / this.totalSteps) * 100;
            $('#wbtmProgressFill').css('width', progress + '%');
            $('#wbtmCurrentStep').text(this.currentStep + 1);
        },

        updateFooterButtons: function() {
            const $prevBtn = $('#wbtmPrevBtn');
            const $nextBtn = $('#wbtmNextBtn');
            const $saveBtn = $('#wbtmSaveBtn');

            // Previous button - show if not on first step
            if (this.currentStep > 0) {
                $prevBtn.show();
            } else {
                $prevBtn.hide();
            }

            // Next/Save button - show Save on last step
            if (this.currentStep === this.totalSteps - 1) {
                $nextBtn.hide();
                $saveBtn.show();
            } else {
                $nextBtn.show();
                $saveBtn.hide();
            }
        },

        validateStep: function(step) {
            let isValid = true;
            const $stepPanel = $(`.wbtm-step-panel[data-step="${step}"]`);

            // Clear previous errors
            $stepPanel.find('.wbtm-field-error').remove();
            $stepPanel.find('.wbtm-form-control').removeClass('error');

            // Validate required fields
            $stepPanel.find('[required], .wbtm-required').each(function() {
                const $field = $(this);
                const value = $field.val();

                if (!value || value.trim() === '') {
                    isValid = false;
                    $field.addClass('error');
                    $field.after('<span class="wbtm-field-error">' + (wbtmModal.i18n.required || 'This field is required') + '</span>');
                }
            });

            // Step-specific validations
            if (step === 0) {
                // Validate bus name
                const busName = $('#wbtmBusName').val();
                if (!busName || busName.trim() === '') {
                    $('#wbtmBusName').addClass('error');
                    isValid = false;
                }

                // Validate bus number
                const busNo = $('#wbtmBusNo').val();
                if (!busNo || busNo.trim() === '') {
                    $('#wbtmBusNo').addClass('error');
                    isValid = false;
                }

                // Validate coach type
                const coachType = $('#wbtmCoachType').val();
                if (!coachType || coachType.trim() === '') {
                    $('#wbtmCoachType').addClass('error');
                    isValid = false;
                }
            }

            if (!isValid) {
                // Scroll to first error
                const $firstError = $stepPanel.find('.error').first();
                if ($firstError.length) {
                    $stepPanel.animate({
                        scrollTop: $firstError.offset().top - $stepPanel.offset().top - 20
                    }, 200);
                }
            }

            return isValid;
        },

        saveBus: function() {
            const self = this;

            // Validate last step
            if (!this.validateStep(this.currentStep)) {
                return false;
            }

            // Show loading state
            $('#wbtmSaveBtn').prop('disabled', true).html('<span class="spinner"></span> ' + (wbtmModal.i18n.saving || 'Saving...'));

            // Sync form data to hidden inputs
            this.syncToHiddenFields();

            // Show success panel
            $('.wbtm-step-panel').removeClass('active');
            $('#wbtmSuccessPanel').show();

            // Reset button state
            setTimeout(function() {
                $('#wbtmSaveBtn').prop('disabled', false).html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M20 6L9 17l-5-5"/></svg> ' + (wbtmModal.i18n.saved || 'Save Bus'));
                self.hasChanges = false;

                // Refresh page or close modal after saving
                setTimeout(function() {
                    self.closeModal();
                    if (wbtmModal.isNew) {
                        // For new buses, trigger the WordPress publish
                        $('#publish').click();
                    } else {
                        // For existing buses, just save
                        $('#save-post').click();
                    }
                }, 1000);
            }, 1500);

            return true;
        },

        syncToHiddenFields: function() {
            // Sync bus name to WordPress title
            const busName = $('#wbtmBusName').val();
            $('#title').val(busName);

            // Sync bus number
            const busNo = $('#wbtmBusNo').val();
            $('#wbtmBusNoHidden').val(busNo);
            $('input[name="wbtm_bus_no"]').val(busNo);

            // Sync coach type/category
            const coachType = $('#wbtmCoachType').val();
            $('#wbtmBusCategoryHidden').val(coachType);
            $('input[name="wbtm_bus_category"]').val(coachType);

            // Sync reservation toggle
            const reservation = $('#wbtmReservationToggle').is(':checked') ? 'yes' : 'no';
            $('#wbtmReservationHidden').val(reservation);
            $('input[name="wbtm_registration"]').val(reservation);
        },

        addRouteStop: function() {
            const template = $('#wbtmRouteStopTemplate').html();
            const count = $('#wbtmRouteList .wbtm-route-stop').length;
            const newHtml = template.replace(/{{INDEX}}/g, count);
            $('#wbtmRouteList .wbtm_item_insert_before').before(newHtml);
            this.hasChanges = true;
        },

        removeRouteStop: function(btn) {
            $(btn).closest('.wbtm-route-stop').fadeOut(200, function() {
                $(this).remove();
                this.hasChanges = true;
            });
        },

        addService: function() {
            const count = $('#wbtmServiceList .wbtm-service-row').length;
            const html = `
                <div class="wbtm-service-row">
                    <input type="text" class="wbtm-form-control" name="wbtm_service_name[]" placeholder="Service name">
                    <input type="number" class="wbtm-form-control" name="wbtm_service_price[]" placeholder="0">
                    <input type="number" class="wbtm-form-control" name="wbtm_service_qty[]" placeholder="0">
                    <select class="wbtm-form-control" name="wbtm_service_type[]">
                        <option value="input_box">Input Box</option>
                        <option value="checkbox">Checkbox</option>
                    </select>
                    <button type="button" class="wbtm-icon-btn danger" onclick="WBTMModal.removeService(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            `;
            $('#wbtmServiceList').append(html);
            this.hasChanges = true;
        },

        removeService: function(btn) {
            $(btn).closest('.wbtm-service-row').fadeOut(200, function() {
                $(this).remove();
                this.hasChanges = true;
            });
        },

        initMediaUploader: function() {
            const self = this;

            // Logo upload
            if (typeof wp !== 'undefined' && wp.media) {
                $('#wbtmLogoUpload').on('click', function(e) {
                    e.preventDefault();
                    const mediaUploader = wp.media({
                        title: wbtmModal.i18n.selectLogo || 'Select Bus Logo',
                        button: { text: wbtmModal.i18n.useLogo || 'Use this logo' },
                        multiple: false
                    });
                    mediaUploader.on('select', function() {
                        const attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#wbtmBusLogo').val(attachment.id);
                        $('#wbtmLogoPreview').html('<img src="' + attachment.url + '" class="wbtm-preview-img" alt="logo">');
                        self.hasChanges = true;
                    });
                    mediaUploader.open();
                });

                // Gallery upload
                $('#wbtmGalleryUpload').on('click', function(e) {
                    e.preventDefault();
                    const mediaUploader = wp.media({
                        title: wbtmModal.i18n.selectGallery || 'Select Gallery Images',
                        button: { text: wbtmModal.i18n.useImages || 'Use these images' },
                        multiple: true
                    });
                    mediaUploader.on('select', function() {
                        const attachments = mediaUploader.state().get('selection').toJSON();
                        let previewHtml = '';
                        attachments.forEach(function(attachment) {
                            previewHtml += '<img src="' + attachment.url + '" class="wbtm-preview-img" alt="preview">';
                        });
                        $('#wbtmGalleryPreview').html(previewHtml);
                        self.hasChanges = true;
                    });
                    mediaUploader.open();
                });

                // Feature image upload
                $('#wbtmFeatureUpload').on('click', function(e) {
                    e.preventDefault();
                    const mediaUploader = wp.media({
                        title: wbtmModal.i18n.selectFeature || 'Select Feature Image',
                        button: { text: wbtmModal.i18n.useImage || 'Use this image' },
                        multiple: false
                    });
                    mediaUploader.on('select', function() {
                        const attachment = mediaUploader.state().get('selection').first().toJSON();
                        // Set as featured image via AJAX
                        $.ajax({
                            url: wbtmModal.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'wbtm_set_featured_image',
                                post_id: wbtmModal.postId,
                                image_id: attachment.id,
                                nonce: wbtmModal.nonce
                            }
                        });
                        self.hasChanges = true;
                    });
                    mediaUploader.open();
                });
            }
        },

        initAutocomplete: function() {
            // Autocomplete for route stops
            if (typeof $.ui !== 'undefined' && $.ui.autocomplete) {
                $(document).on('focus', '.wbtm-route-place-auto', function() {
                    if (!$(this).data('ui-autocomplete')) {
                        $(this).autocomplete({
                            source: function(request, response) {
                                const stops = [];
                                $('#wbtmBusStopsList option').each(function() {
                                    stops.push($(this).val());
                                });
                                const matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), 'i');
                                response($.grep(stops, function(item) {
                                    return matcher.test(item);
                                }));
                            },
                            minLength: 1
                        });
                    }
                });
            }
        },

        syncReviewData: function() {
            // Sync bus name
            const busName = $('#wbtmBusName').val() || '—';
            $('#rv-name').text(busName);

            // Sync bus number
            const busNo = $('#wbtmBusNo').val() || '—';
            $('#rv-no').text(busNo);

            // Sync coach type
            const coachType = $('#wbtmCoachType option:selected').text() || '—';
            $('#rv-coach').text(coachType);

            // Sync reservation
            const reservation = $('#wbtmReservationToggle').is(':checked');
            const resLabel = reservation ? 'Enabled' : 'Disabled';
            const resClass = reservation ? 'green' : 'red';
            $('#rv-res').html('<span class="wbtm-badge-pill ' + resClass + '">' + resLabel + '</span>');

            // Sync seat type
            const seatType = $('#wbtmSeatType option:selected').text() || '—';
            $('#rv-seattype').text(seatType);

            // Sync total seats
            const totalSeats = $('#wbtmTotalSeat').val() || '—';
            $('#rv-seats').text(totalSeats);

            // Count routes
            const routeCount = $('#wbtmRouteList .wbtm-route-stop').length;
            $('#rv-stops').text(routeCount);

            // First and last route
            const firstRoute = $('#wbtmRouteList .wbtm-route-stop:first input[name="wbtm_route_place[]"]').val() || '—';
            const lastRoute = $('#wbtmRouteList .wbtm-route-stop:last input[name="wbtm_route_place[]"]').val() || '—';
            $('#rv-origin').text(firstRoute);
            $('#rv-destination').text(lastRoute);

            // Count services
            const serviceCount = $('#wbtmServiceList .wbtm-service-row').length;
            $('#rv-services').text(serviceCount + ' ' + (wbtmModal.i18n.active || 'active'));

            // Date type
            const dateType = $('#wbtmDateType option:selected').text() || 'Repeated';
            $('#rv-datetype').text(dateType);
        }
    };

    // Global functions for inline handlers
    window.toggleDateFields = function(value) {
        if (value === 'particular') {
            $('#wbtmParticularFields').slideDown(200);
            $('#wbtmRepeatedFields').slideUp(200);
        } else {
            $('#wbtmParticularFields').slideUp(200);
            $('#wbtmRepeatedFields').slideDown(200);
        }
    };

    window.toggleSeatPlan = function(value) {
        if (value === 'wbtm_seat_plan') {
            $('#wbtmSeatPlanConfig').slideDown(200);
        } else {
            $('#wbtmSeatPlanConfig').slideUp(200);
        }
    };

    window.wbtmAddRouteStop = function() {
        WBTMModal.addRouteStop();
    };

    window.wbtmRemoveRouteStop = function(btn) {
        WBTMModal.removeRouteStop(btn);
    };

    window.wbtmAddService = function() {
        WBTMModal.addService();
    };

    window.wbtmRemoveService = function(btn) {
        WBTMModal.removeService(btn);
    };

    // Terms & Conditions add/remove handlers
    $(document).on('click', '.wtbm_add_term_condition', function() {
        var $btn = $(this);
        var key = $btn.data('key');
        var title = $btn.data('title');
        var $row = $btn.closest('.wbtm-term-row');
        var $selectedList = $('#wbtmSelectedTermsList');
        
        // Remove from available
        $row.fadeOut(200, function() {
            $(this).remove();
        });
        
        // Add to selected
        var selectedHtml = '<div class="wbtm-selected-term-row" data-key="' + key + '">' +
            '<span class="wbtm-term-title">' + title + '</span>' +
            '<button type="button" class="wbtm-btn-sm danger wtbm_remove_term_condition" data-key="' + key + '" data-title="' + title + '">Remove</button>' +
            '</div>';
        
        $selectedList.find('.wbtm-no-terms').remove();
        $selectedList.append(selectedHtml);
        
        // Update hidden input
        updateTermsInput();
    });

    $(document).on('click', '.wtbm_remove_term_condition', function() {
        var $btn = $(this);
        var key = $btn.data('key');
        var title = $btn.data('title');
        var $row = $btn.closest('.wbtm-selected-term-row');
        var $availableList = $('.wbtm-terms-list');
        
        // Remove from selected
        $row.fadeOut(200, function() {
            $(this).remove();
            if ($('#wbtmSelectedTermsList').children().length === 0) {
                $('#wbtmSelectedTermsList').html('<p class="wbtm-no-terms">No terms added yet.</p>');
            }
        });
        
        // Add back to available
        var availableHtml = '<div class="wbtm-term-row">' +
            '<span class="wbtm-term-title">' + title + '</span>' +
            '<button type="button" class="wbtm-btn-sm wtbm_add_term_condition" data-key="' + key + '" data-title="' + title + '">Add</button>' +
            '</div>';
        
        $availableList.append(availableHtml);
        
        // Update hidden input
        updateTermsInput();
    });

    function updateTermsInput() {
        var selectedTerms = {};
        $('#wbtmSelectedTermsList .wbtm-selected-term-row').each(function() {
            var key = $(this).data('key');
            var title = $(this).find('.wbtm-term-title').text();
            selectedTerms[key] = { title: title };
        });
        $('#wtbm_added_term_condition_input').val(JSON.stringify(selectedTerms));
    }

    // Initialize when document is ready
    $(document).ready(function() {
        WBTMModal.init();
    });

})(jQuery);