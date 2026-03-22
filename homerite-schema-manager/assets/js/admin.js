/**
 * HomeRite Schema Manager — Admin JS
 *
 * Handles:
 * - Tabbed interface (meta box)
 * - Schema section show/hide based on type checkboxes
 * - Repeatable rows (service areas, sameAs)
 * - FAQ drag-to-reorder + add/remove rows
 * - Meta description character counter
 * - WP media picker for image fields
 */

(function ($) {
	'use strict';

	// ─── Tab switching ────────────────────────────────────────────────
	$(document).on('click', '.hsm-tab-link', function () {
		var target = $(this).data('tab');
		$('.hsm-tab-link').removeClass('active');
		$('.hsm-tab-panel').removeClass('active');
		$(this).addClass('active');
		$('#' + target).addClass('active');
	});

	// ─── Schema type toggles → show/hide sections ─────────────────────
	$(document).on('change', '[data-reveals]', function () {
		var sectionId = '#' + $(this).data('reveals');
		if ($(this).is(':checked')) {
			$(sectionId).addClass('active');
		} else {
			$(sectionId).removeClass('active');
		}
	});

	// Initialise on page load (in case of pre-checked boxes).
	$('[data-reveals]').each(function () {
		if ($(this).is(':checked')) {
			$('#' + $(this).data('reveals')).addClass('active');
		}
	});

	// ─── Repeatable rows (service areas, sameAs) ──────────────────────
	$(document).on('click', '.hsm-add-area', function () {
		var target  = $(this).data('target');
		var name    = $(this).data('name');
		var type    = $(this).data('type') || 'text';
		var placeholder = type === 'url' ? 'https://' : '';

		var $row = $(
			'<div class="hsm-repeatable-row">' +
			'<input type="' + type + '" name="' + name + '" value="" class="regular-text" placeholder="' + placeholder + '">' +
			'<button type="button" class="button hsm-remove-row">Remove</button>' +
			'</div>'
		);

		$('#' + target).append($row);
		$row.find('input').focus();
	});

	$(document).on('click', '.hsm-remove-row', function () {
		var $list = $(this).closest('[id]');
		// Keep at least one row.
		if ($list.find('.hsm-repeatable-row').length > 1) {
			$(this).closest('.hsm-repeatable-row').remove();
		} else {
			$(this).closest('.hsm-repeatable-row').find('input').val('');
		}
	});

	// ─── FAQ rows ─────────────────────────────────────────────────────
	$('#hsm-add-faq-row').on('click', function () {
		var $row = $(
			'<div class="hsm-faq-row">' +
			'<div class="hsm-faq-handle">&#9776;</div>' +
			'<div class="hsm-faq-fields">' +
			'<input type="text" name="hsm_faq_question[]" placeholder="Question" class="large-text">' +
			'<textarea name="hsm_faq_answer[]" rows="2" class="large-text" placeholder="Answer"></textarea>' +
			'</div>' +
			'<button type="button" class="button hsm-remove-faq-row">Remove</button>' +
			'</div>'
		);
		$('#hsm-faq-list').append($row);
		$row.find('input').first().focus();
	});

	$(document).on('click', '.hsm-remove-faq-row', function () {
		if ($('#hsm-faq-list .hsm-faq-row').length > 1) {
			$(this).closest('.hsm-faq-row').remove();
		} else {
			$(this).closest('.hsm-faq-row').find('input, textarea').val('');
		}
	});

	// Drag-to-reorder FAQ rows.
	if (typeof $.fn.sortable !== 'undefined') {
		$('#hsm-faq-list').sortable({
			handle: '.hsm-faq-handle',
			axis: 'y',
			tolerance: 'pointer',
		});
	}

	// ─── Character counter for meta description ────────────────────────
	function updateCharCount($textarea) {
		var $counter = $textarea.closest('td').find('.hsm-char-count');
		if (!$counter.length) return;

		var current = $textarea.val().length;
		var max     = parseInt($counter.data('max'), 10) || 160;

		$counter.text(current + ' / ' + max + ' characters');

		if (current > max) {
			$counter.addClass('hsm-over-limit');
		} else {
			$counter.removeClass('hsm-over-limit');
		}
	}

	$('#hsm_seo_description').on('input', function () {
		updateCharCount($(this));
	}).trigger('input');

	// ─── WP Media Picker ──────────────────────────────────────────────
	$(document).on('click', '.hsm-upload-image', function (e) {
		e.preventDefault();

		var $btn    = $(this);
		var targetId = $btn.data('target');

		var frame = wp.media({
			title:    'Select Image',
			button:   { text: 'Use this image' },
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#' + targetId).val(attachment.url).trigger('change');
		});

		frame.open();
	});

})(jQuery);
