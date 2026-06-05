/**
 * Custom Video Library - Admin JavaScript
 */

(function($) {
	'use strict';

	const CVLAdmin = {
		/**
		 * Initialize admin functionality
		 */
		init: function() {
			this.bindEvents();
			this.loadAnalytics();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Settings form submission
			$('form.cvl-settings-form').on('submit', this.handleSettingsSubmit);

			// Bulk actions
			$('#cvl-bulk-action-button').on('click', this.handleBulkAction);

			// Video meta boxes
			$('.cvl-video-meta input, .cvl-video-meta select').on('change', this.validateMetaInput);
		},

		/**
		 * Handle settings form submission
		 */
		handleSettingsSubmit: function(e) {
			// Prevent default submission
			e.preventDefault();

			const formData = new FormData(this);

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'cvl_save_settings',
					nonce: $('#cvl_nonce').val(),
					settings: Object.fromEntries(formData)
				},
				success: function(response) {
					if (response.success) {
						alert('Settings saved successfully');
					} else {
						alert('Error saving settings: ' + response.data.message);
					}
				},
				error: function() {
					alert('Error communicating with server');
				}
			});
		},

		/**
		 * Handle bulk actions
		 */
		handleBulkAction: function() {
			const action = $('#cvl-bulk-select').val();
			const selected = $('input[name="video[]"]:checked');

			if (selected.length === 0) {
				alert('Please select at least one video');
				return;
			}

			const videoIds = selected.map(function() {
				return $(this).val();
			}).get();

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'cvl_bulk_action',
					nonce: $('#cvl_nonce').val(),
					bulk_action: action,
					video_ids: videoIds
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + response.data.message);
					}
				}
			});
		},

		/**
		 * Validate meta input
		 */
		validateMetaInput: function() {
			const $this = $(this);
			const value = $this.val();
			const fieldType = $this.data('type');

			// Perform validation based on field type
			if (fieldType === 'url') {
				if (!CVLAdmin.isValidUrl(value)) {
					$this.addClass('error');
					$this.after('<span class="cvl-error">Invalid URL</span>');
				} else {
					$this.removeClass('error');
					$this.siblings('.cvl-error').remove();
				}
			} else if (fieldType === 'number') {
				if (isNaN(value) || value < 0) {
					$this.addClass('error');
				} else {
					$this.removeClass('error');
				}
			}
		},

		/**
		 * Validate URL
		 */
		isValidUrl: function(url) {
			try {
				new URL(url);
				return true;
			} catch (e) {
				return false;
			}
		},

		/**
		 * Load analytics data
		 */
		loadAnalytics: function() {
			const container = $('#cvl-analytics-container');
			if (container.length === 0) return;

			$.ajax({
				type: 'GET',
				url: ajaxurl,
				data: {
					action: 'cvl_get_analytics',
					nonce: $('#cvl_nonce').val()
				},
				success: function(response) {
					if (response.success) {
						CVLAdmin.renderAnalytics(response.data);
					}
				},
				error: function() {
					container.html('<p class="cvl-notice error">Error loading analytics</p>');
				}
			});
		},

		/**
		 * Render analytics data
		 */
		renderAnalytics: function(data) {
			const container = $('#cvl-analytics-container');
			let html = '<div class="cvl-stat-group">';

			const stats = [
				{ label: 'Total Videos', value: data.total_videos },
				{ label: 'Total Views', value: data.total_views },
				{ label: 'Active Users', value: data.active_users },
				{ label: 'Revenue', value: '$' + data.revenue }
			];

			stats.forEach(stat => {
				html += '<div class="cvl-stat-box">' +
					'<p class="cvl-stat-value">' + stat.value + '</p>' +
					'<p class="cvl-stat-label">' + stat.label + '</p>' +
					'</div>';
			});

			html += '</div>';
			container.html(html);
		}
	};

	// Initialize when DOM is ready
	$(document).ready(function() {
		CVLAdmin.init();
	});

	// Export to global scope
	window.CVLAdmin = CVLAdmin;
})(jQuery);
