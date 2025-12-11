jQuery(document).ready(function($) {
    'use strict';

    const WBTMDashboard = {
        currentPage: 1,
        isLoading: false,
        searchTerm: '',

        init: function() {
            this.bindEvents();
            this.handleSearchTypeChange(); // Initialize search input visibility
            this.loadBookings();
        },

        bindEvents: function() {
            // Search functionality
            $('#wbtm-search-btn').on('click', this.handleSearch.bind(this));
            $('#wbtm-reset-btn').on('click', this.handleReset.bind(this));
            $('#wbtm-search-bookings, #wbtm-search-date').on('keypress', function(e) {
                if (e.which === 13) {
                    WBTMDashboard.handleSearch();
                }
            });

            // Search type change handler
            $('#wbtm-search-type').on('change', this.handleSearchTypeChange.bind(this));

            // Modal events
            $(document).on('click', '.wbtm-view-btn', this.handleViewBooking.bind(this));
            $(document).on('click', '.wbtm-edit-btn', this.handleEditAttendee.bind(this));
            $(document).on('click', '.wbtm-pdf-btn', this.handleDownloadPDF.bind(this));
            
            // Modal close events
            $('#wbtm-modal-close, #wbtm-edit-modal-close').on('click', this.closeModals.bind(this));
            $('.wbtm-modal').on('click', function(e) {
                if (e.target === this) {
                    WBTMDashboard.closeModals();
                }
            });

            // Pagination
            $(document).on('click', '.wbtm-pagination-btn', this.handlePagination.bind(this));

            // Edit form submission
            $(document).on('submit', '#wbtm-edit-form', this.handleEditSubmission.bind(this));
        },

        loadBookings: function(page = 1, search = '') {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.currentPage = page;
            this.searchTerm = search;

            const $container = $('#wbtm-bookings-list');
            $container.html('<div class="wbtm-loading"><i class="fas fa-spinner fa-spin"></i> ' + wbtm_dashboard_ajax.strings.loading + '</div>');

            $.ajax({
                url: wbtm_dashboard_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbtm_get_user_bookings',
                    page: page,
                    search: search,
                    nonce: wbtm_dashboard_ajax.nonce
                },
                success: this.handleBookingsResponse.bind(this),
                error: this.handleError.bind(this),
                complete: function() {
                    WBTMDashboard.isLoading = false;
                }
            });
        },

        handleBookingsResponse: function(response) {
            if (response.success) {
                this.renderBookings(response.data.bookings);
                this.updateStats(response.data.stats);
                this.renderPagination(response.data.pagination);
            } else {
                this.showError(response.data.message || wbtm_dashboard_ajax.strings.error);
            }
        },

        renderBookings: function(bookings) {
            const $container = $('#wbtm-bookings-list');
            
            if (bookings.length === 0) {
                $container.html(this.getEmptyState());
                return;
            }

            let html = '';
            bookings.forEach(booking => {
                html += this.getBookingHTML(booking);
            });

            $container.html(html);
        },

        getBookingHTML: function(booking) {
            const statusClass = booking.status.replace('-', '');
            const journeyDate = booking.journey_date || 'Not specified';
            const route = booking.boarding_point && booking.dropping_point 
                ? `${booking.boarding_point} → ${booking.dropping_point}` 
                : 'Route not specified';
            const price = booking.total ? `$${parseFloat(booking.total).toFixed(2)}` : 'N/A';

            return `
                <div class="wbtm-booking-item" data-order-id="${booking.order_id}">
                    <div class="wbtm-booking-cell wbtm-order-info">
                        <div class="wbtm-order-number">#${booking.order_number}</div>
                        <div class="wbtm-order-date">${booking.order_date}</div>
                    </div>
                    <div class="wbtm-booking-cell wbtm-event-details">
                        <div class="wbtm-bus-name">
                            ${booking.bus_name}
                            ${booking.has_extra_services ? '<span class="wbtm-services-badge" title="Includes extra services"><i class="fas fa-plus-circle"></i></span>' : ''}
                        </div>
                        <div class="wbtm-journey-details">
                            <div class="wbtm-journey-info">
                                <i class="fas fa-calendar-alt"></i>
                                <span>${journeyDate}</span>
                            </div>
                            <div class="wbtm-journey-info">
                                <i class="fas fa-route"></i>
                                <span>${route}</span>
                            </div>
                        </div>
                    </div>
                    <div class="wbtm-booking-cell">
                        <div class="wbtm-ticket-count">${booking.ticket_count}</div>
                    </div>
                    <div class="wbtm-booking-cell">
                        <div class="wbtm-price">${price}</div>
                    </div>
                    <div class="wbtm-booking-cell">
                        <span class="wbtm-status ${statusClass}">${booking.status}</span>
                    </div>
                    <div class="wbtm-booking-cell wbtm-actions">
                        <button class="wbtm-btn wbtm-btn-primary wbtm-view-btn" data-order-id="${booking.order_id}">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${booking.pdf_url ? 
                            `<button class="wbtm-btn wbtm-btn-success wbtm-pdf-btn _themeButton" data-href="${booking.pdf_url}" data-order-id="${booking.order_id}">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>` :
                            `<div class="wbtm-pdf-disabled">
                                <button class="wbtm-btn wbtm-btn-disabled" disabled>
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <div class="wbtm-pro-required-text">Pro Addon Required</div>
                            </div>`
                        }
                    </div>
                </div>
            `;
        },

        getEmptyState: function() {
            return `
                <div class="wbtm-empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>No bookings found</h3>
                    <p>You haven't made any bus bookings yet, or no bookings match your search criteria.</p>
                </div>
            `;
        },

        updateStats: function(stats) {
            $('#total-bookings').text(stats.total);
            $('#upcoming-bookings').text(stats.upcoming);
            $('#completed-bookings').text(stats.completed);
        },

        renderPagination: function(pagination) {
            const $container = $('#wbtm-pagination');
            
            if (pagination.total_pages <= 1) {
                $container.hide();
                return;
            }

            let html = '';
            
            // Previous button
            if (pagination.current_page > 1) {
                html += `<button class="wbtm-pagination-btn" data-page="${pagination.current_page - 1}">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>`;
            }

            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

            if (startPage > 1) {
                html += `<button class="wbtm-pagination-btn" data-page="1">1</button>`;
                if (startPage > 2) {
                    html += '<span>...</span>';
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.current_page ? 'active' : '';
                html += `<button class="wbtm-pagination-btn ${activeClass}" data-page="${i}">${i}</button>`;
            }

            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) {
                    html += '<span>...</span>';
                }
                html += `<button class="wbtm-pagination-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</button>`;
            }

            // Next button
            if (pagination.current_page < pagination.total_pages) {
                html += `<button class="wbtm-pagination-btn" data-page="${pagination.current_page + 1}">
                    Next <i class="fas fa-chevron-right"></i>
                </button>`;
            }

            $container.html(html).show();
        },

        handleSearchTypeChange: function() {
            const searchType = $('#wbtm-search-type').val();
            const $textInput = $('#wbtm-search-bookings');
            const $dateInput = $('#wbtm-search-date');
            
            if (searchType === 'journey_date') {
                $textInput.hide();
                $dateInput.show();
                $dateInput.attr('placeholder', 'Select journey date...');
            } else {
                $dateInput.hide();
                $textInput.show();
                
                // Update placeholder based on search type
                const placeholders = {
                    'order_id': 'Enter order ID...',
                    'bus_name': 'Enter bus name...',
                    'route': 'Enter route (e.g., Hamburg → Berlin)...'
                };
                $textInput.attr('placeholder', placeholders[searchType] || 'Enter search term...');
            }
        },

        handleSearch: function() {
            const searchType = $('#wbtm-search-type').val();
            let searchTerm = '';
            
            if (searchType === 'journey_date') {
                searchTerm = $('#wbtm-search-date').val();
            } else {
                searchTerm = $('#wbtm-search-bookings').val().trim();
            }
            
            const searchData = {
                type: searchType,
                term: searchTerm
            };
            
            this.loadBookings(1, searchData);
        },

        handleReset: function() {
            $('#wbtm-search-bookings').val('');
            $('#wbtm-search-date').val('');
            $('#wbtm-search-type').val('order_id');
            this.handleSearchTypeChange(); // Reset the input visibility
            this.loadBookings(1, '');
        },

        handlePagination: function(e) {
            const page = parseInt($(e.currentTarget).data('page'));
            if (page && page !== this.currentPage) {
                this.loadBookings(page, this.searchTerm);
            }
        },

        handleViewBooking: function(e) {
            const orderId = $(e.currentTarget).data('order-id');
            this.loadBookingDetails(orderId);
        },

        loadBookingDetails: function(orderId) {
            const $modal = $('#wbtm-booking-modal');
            const $body = $('#wbtm-modal-body');
            
            $body.html('<div class="wbtm-loading"><i class="fas fa-spinner fa-spin"></i> ' + wbtm_dashboard_ajax.strings.loading + '</div>');
            $modal.show();

            $.ajax({
                url: wbtm_dashboard_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbtm_get_booking_details',
                    order_id: orderId,
                    nonce: wbtm_dashboard_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WBTMDashboard.renderBookingDetails(response.data);
                    } else {
                        $body.html('<div class="wbtm-error">' + (response.data.message || wbtm_dashboard_ajax.strings.error) + '</div>');
                    }
                },
                error: function() {
                    $body.html('<div class="wbtm-error">' + wbtm_dashboard_ajax.strings.error + '</div>');
                }
            });
        },

        renderBookingDetails: function(data) {
            const $body = $('#wbtm-modal-body');
            
            let html = '<div class="wbtm-booking-details">';
            
            // Order Information
            html += `
                <div class="wbtm-detail-section">
                    <h4>Order Information</h4>
                    <div class="wbtm-detail-grid">
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">Order Number</div>
                            <div class="wbtm-detail-value">#${data.order.number}</div>
                        </div>
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">Order Date</div>
                            <div class="wbtm-detail-value">${data.order.date}</div>
                        </div>
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">Status</div>
                            <div class="wbtm-detail-value">
                                <span class="wbtm-status ${data.order.status.replace('-', '')}">${data.order.status}</span>
                            </div>
                        </div>
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">Total Amount</div>
                            <div class="wbtm-detail-value">$${data.order.total}</div>
                        </div>
                    </div>
                </div>
            `;

            // Journey Information
            html += `
                <div class="wbtm-detail-section">
                    <h4>Journey Information</h4>
                    <div class="wbtm-detail-grid">
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">Bus</div>
                            <div class="wbtm-detail-value">${data.bus.name}</div>
                        </div>
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">Journey Date</div>
                            <div class="wbtm-detail-value">${data.journey.date ? new Date(data.journey.date).toLocaleString() : 'Not specified'}</div>
                        </div>
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">From</div>
                            <div class="wbtm-detail-value">${data.journey.boarding_point || 'Not specified'}</div>
                        </div>
                        <div class="wbtm-detail-item">
                            <div class="wbtm-detail-label">To</div>
                            <div class="wbtm-detail-value">${data.journey.dropping_point || 'Not specified'}</div>
                        </div>
                    </div>
                </div>
            `;

            // Attendees Information
            if (data.attendees && data.attendees.length > 0) {
                html += `
                    <div class="wbtm-detail-section">
                        <h4>Passenger Information</h4>
                        <div class="wbtm-attendee-list">
                `;

                data.attendees.forEach(attendee => {
                    html += `
                        <div class="wbtm-attendee-card">
                            <div class="wbtm-attendee-header">
                                <div class="wbtm-seat-number">Seat: ${attendee.seat || 'Not assigned'}</div>
                                <div class="wbtm-attendee-actions">
                                    <button class="wbtm-btn wbtm-btn-primary wbtm-edit-btn" data-attendee-id="${attendee.id}">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                            </div>
                            <div class="wbtm-attendee-info">
                                <div class="wbtm-detail-item">
                                    <div class="wbtm-detail-label">Fare</div>
                                    <div class="wbtm-detail-value">$${attendee.fare || '0.00'}</div>
                                </div>
                    `;

                    // Add custom fields if available
                    if (attendee.custom_fields) {
                        Object.keys(attendee.custom_fields).forEach(key => {
                            const field = attendee.custom_fields[key];
                            if (field.value) {
                                html += `
                                    <div class="wbtm-detail-item">
                                        <div class="wbtm-detail-label">${field.label || key}</div>
                                        <div class="wbtm-detail-value">${field.value}</div>
                                    </div>
                                `;
                            }
                        });
                    }

                    html += `
                            </div>
                    `;

                    // Add extra services if available
                    if (attendee.extra_services && attendee.extra_services.length > 0) {
                        html += `
                            <div class="wbtm-extra-services">
                                <h5 style="margin: 15px 0 10px 0; color: #374151; font-weight: 600;">Extra Services</h5>
                                <div class="wbtm-services-list">
                        `;
                        
                        attendee.extra_services.forEach(service => {
                            const serviceName = service.name || 'Service';
                            const serviceQty = service.qty || 1;
                            const servicePrice = service.price || 0;
                            const totalPrice = servicePrice * serviceQty;
                            
                            html += `
                                <div class="wbtm-service-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f3f4f6;">
                                    <div class="wbtm-service-name" style="font-weight: 500; color: #111827;">${serviceName}</div>
                                    <div class="wbtm-service-details" style="color: #6b7280; font-size: 0.9rem;">
                                        x${serviceQty} | $${parseFloat(servicePrice).toFixed(2)} = $${parseFloat(totalPrice).toFixed(2)}
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += `
                                </div>
                            </div>
                        `;
                    }

                    html += `
                        </div>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            }

            html += '</div>';
            $body.html(html);
        },

        handleEditAttendee: function(e) {
            const attendeeId = $(e.currentTarget).data('attendee-id');
            this.showEditForm(attendeeId);
        },

        showEditForm: function(attendeeId) {
            const $modal = $('#wbtm-edit-modal');
            const $body = $('#wbtm-edit-modal-body');
            
            // For now, show a simple form. In a real implementation, you'd load the attendee data first
            const html = `
                <form id="wbtm-edit-form" data-attendee-id="${attendeeId}">
                    <div class="wbtm-form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="wbtm-form-control" required>
                    </div>
                    <div class="wbtm-form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="wbtm-form-control">
                    </div>
                    <div class="wbtm-form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="wbtm-form-control">
                    </div>
                    <div class="wbtm-form-actions">
                        <button type="submit" class="wbtm-btn wbtm-btn-primary">
                            <i class="fas fa-save"></i> Update Information
                        </button>
                        <button type="button" class="wbtm-btn wbtm-btn-secondary" id="wbtm-edit-cancel">
                            Cancel
                        </button>
                    </div>
                </form>
            `;
            
            $body.html(html);
            $modal.show();
            
            // Bind cancel button
            $('#wbtm-edit-cancel').on('click', this.closeModals.bind(this));
        },

        handleEditSubmission: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const attendeeId = $form.data('attendee-id');
            const formData = $form.serializeArray();
            
            const fieldData = {};
            formData.forEach(field => {
                fieldData[field.name] = field.value;
            });

            $.ajax({
                url: wbtm_dashboard_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wbtm_update_attendee_info',
                    attendee_id: attendeeId,
                    field_data: fieldData,
                    nonce: wbtm_dashboard_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Information updated successfully!');
                        WBTMDashboard.closeModals();
                        WBTMDashboard.loadBookings(WBTMDashboard.currentPage, WBTMDashboard.searchTerm);
                    } else {
                        alert(response.data.message || wbtm_dashboard_ajax.strings.error);
                    }
                },
                error: function() {
                    alert(wbtm_dashboard_ajax.strings.error);
                }
            });
        },

        handleDownloadPDF: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const pdfUrl = $button.attr('data-href');
            
            if (!pdfUrl) {
                alert('PDF download URL not available.');
                return;
            }
            
            // Show loading indicator (similar to the pro addon)
            const loadingMsg = $('<div id="wbtm-download-loading" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:99999;background:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;"><div style="background:#fff;padding:30px 50px;border-radius:8px;box-shadow:0 2px 8px #ccc;font-size:18px;font-weight:600;color:#2271b1;">Preparing download...</div></div>');
            $('body').append(loadingMsg);
            
            // Trigger download
            window.open(pdfUrl, '_blank');
            
            // Remove loading indicator after a short delay
            setTimeout(function() {
                loadingMsg.remove();
            }, 2000);
        },

        closeModals: function() {
            $('.wbtm-modal').hide();
        },

        handleError: function() {
            this.showError(wbtm_dashboard_ajax.strings.error);
        },

        showError: function(message) {
            const $container = $('#wbtm-bookings-list');
            $container.html(`
                <div class="wbtm-error-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error</h3>
                    <p>${message}</p>
                    <button class="wbtm-btn wbtm-btn-primary" onclick="WBTMDashboard.loadBookings()">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `);
        }
    };

    // Initialize the dashboard
    WBTMDashboard.init();

    // Make it globally accessible for debugging
    window.WBTMDashboard = WBTMDashboard;
});
