/**
 * Stride Analytics — Admin JS
 *
 * Handles:
 * - Tabbed interface (meta box)
 * - Schema section show/hide based on type checkboxes
 * - Repeatable rows (service areas, sameAs)
 * - FAQ drag-to-reorder + add/remove rows
 * - HowTo steps drag-to-reorder + add/remove rows
 * - Address → Lat/Long geocoding (OpenStreetMap Nominatim)
 * - Meta description character counter
 * - WP media picker for image fields
 * - Cascading business type dropdowns (category → type)
 * - OG / Twitter (X) "same as SEO Meta" sync + collapse
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

	// Initialise on page load (pre-checked boxes).
	$('[data-reveals]').each(function () {
		if ($(this).is(':checked')) {
			$('#' + $(this).data('reveals')).addClass('active');
		}
	});

	// ─── Cascading business type dropdowns ────────────────────────────
	var $catDrop  = $('#hsm_business_category');
	var $typeDrop = $('#hsm_schema_type');

	if ($catDrop.length && $typeDrop.length) {

		// Build category → options map from rendered optgroups.
		var catMap = {};
		$typeDrop.find('optgroup').each(function () {
			var cat  = $(this).attr('label');
			var opts = [];
			$(this).find('option').each(function () {
				opts.push({ value: $(this).val(), text: $(this).text() });
			});
			catMap[cat] = opts;
		});

		// Rebuild the type dropdown for a given category (or all if blank).
		function filterTypes(cat) {
			var currentVal = $typeDrop.val();
			$typeDrop.empty();

			if (!cat) {
				// Show all with optgroups.
				$.each(catMap, function (catName, opts) {
					var $grp = $('<optgroup>').attr('label', catName);
					$.each(opts, function (i, o) {
						$grp.append($('<option>').val(o.value).text(o.text));
					});
					$typeDrop.append($grp);
				});
			} else {
				// Show only selected category's types (flat, no optgroup).
				$.each(catMap[cat] || [], function (i, o) {
					$typeDrop.append($('<option>').val(o.value).text(o.text));
				});
			}

			// Restore previously selected value if it still exists in the new list.
			if (currentVal) {
				$typeDrop.val(currentVal);
			}
		}

		// On page load: detect which category the saved type belongs to.
		var savedType = $typeDrop.val();
		if (savedType) {
			$.each(catMap, function (catName, opts) {
				$.each(opts, function (i, o) {
					if (o.value === savedType) {
						$catDrop.val(catName);
						filterTypes(catName);
						$typeDrop.val(savedType);
						return false; // break inner
					}
				});
				if ($catDrop.val() === catName) return false; // break outer
			});
		}

		// On category change: filter the type dropdown.
		$catDrop.on('change', function () {
			filterTypes($(this).val());
		});
	}

	// ─── OG "same as SEO Meta" sync + collapse ─────────────────────────
	function syncOgToMeta() {
		var title = $('#hsm_seo_title').val();
		var desc  = $('#hsm_seo_description').val();
		$('#hsm_og_title').val(title);
		$('#hsm_og_description').val(desc);
	}

	function applyOgSync(animate) {
		var checked = $('#hsm_og_same_as_meta').is(':checked');
		if (checked) {
			syncOgToMeta();
			if (animate) {
				$('#hsm-og-custom-fields').slideUp(200);
			} else {
				$('#hsm-og-custom-fields').hide();
			}
		} else {
			if (animate) {
				$('#hsm-og-custom-fields').slideDown(200);
			} else {
				$('#hsm-og-custom-fields').show();
			}
		}
	}

	$('#hsm_og_same_as_meta').on('change', function () { applyOgSync(true); });
	applyOgSync(false); // initialise on load

	// Live sync when SEO fields change while OG is locked.
	$(document).on('input', '#hsm_seo_title, #hsm_seo_description', function () {
		if ($('#hsm_og_same_as_meta').is(':checked')) {
			syncOgToMeta();
		}
	});

	// ─── Twitter (X) "same as SEO Meta" sync + collapse ──────────────
	function syncTwitterToMeta() {
		var title = $('#hsm_seo_title').val();
		var desc  = $('#hsm_seo_description').val();
		$('#hsm_twitter_title').val(title);
		$('#hsm_twitter_description').val(desc);
	}

	function applyTwitterSync(animate) {
		var checked = $('#hsm_twitter_same_as_meta').is(':checked');
		if (checked) {
			syncTwitterToMeta();
			if (animate) {
				$('#hsm-twitter-custom-fields').slideUp(200);
			} else {
				$('#hsm-twitter-custom-fields').hide();
			}
		} else {
			if (animate) {
				$('#hsm-twitter-custom-fields').slideDown(200);
			} else {
				$('#hsm-twitter-custom-fields').show();
			}
		}
	}

	$('#hsm_twitter_same_as_meta').on('change', function () { applyTwitterSync(true); });
	applyTwitterSync(false); // initialise on load

	// Live sync Twitter when SEO fields change while Twitter is locked.
	$(document).on('input', '#hsm_seo_title, #hsm_seo_description', function () {
		if ($('#hsm_twitter_same_as_meta').is(':checked')) {
			syncTwitterToMeta();
		}
	});

	// ─── Repeatable rows (service areas, sameAs) ──────────────────────
	$(document).on('click', '.hsm-add-area', function () {
		var target      = $(this).data('target');
		var name        = $(this).data('name');
		var type        = $(this).data('type') || 'text';
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
		if ($list.find('.hsm-repeatable-row').length > 1) {
			$(this).closest('.hsm-repeatable-row').remove();
		} else {
			$(this).closest('.hsm-repeatable-row').find('input').val('');
		}
	});

	// ─── Team member rows ─────────────────────────────────────────────
	$(document).on('click', '.hsm-add-team-member', function () {
		var $row = $(
			'<div class="hsm-repeatable-row hsm-team-row">' +
			'<input type="text" name="hsm_team_name[]"      value="" placeholder="e.g. Jane Smith">' +
			'<input type="text" name="hsm_team_job_title[]" value="" placeholder="e.g. CEO / Founder">' +
			'<input type="url"  name="hsm_team_linkedin[]"  value="" placeholder="https://linkedin.com/in/...">' +
			'<input type="url"  name="hsm_team_image[]"     value="" placeholder="optional">' +
			'<button type="button" class="button hsm-remove-team-row">Remove</button>' +
			'</div>'
		);
		$('#' + $(this).data('target')).append($row);
		$row.find('input').first().focus();
	});

	$(document).on('click', '.hsm-remove-team-row', function () {
		var $list = $('#hsm-team-list');
		if ($list.find('.hsm-team-row').length > 1) {
			$(this).closest('.hsm-team-row').remove();
		} else {
			$(this).closest('.hsm-team-row').find('input').val('');
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

	if (typeof $.fn.sortable !== 'undefined') {
		$('#hsm-faq-list').sortable({ handle: '.hsm-faq-handle', axis: 'y', tolerance: 'pointer' });
	}

	// ─── HowTo step rows ──────────────────────────────────────────────
	$('#hsm-add-howto-row').on('click', function () {
		var $row = $(
			'<div class="hsm-howto-row">' +
			'<div class="hsm-faq-handle">&#9776;</div>' +
			'<div class="hsm-faq-fields">' +
			'<input type="text" name="hsm_howto_step_name[]" placeholder="Step title (e.g. Remove damaged shingle)" class="large-text">' +
			'<textarea name="hsm_howto_step_text[]" rows="2" class="large-text" placeholder="Step instructions\u2026"></textarea>' +
			'</div>' +
			'<button type="button" class="button hsm-remove-howto-row">Remove</button>' +
			'</div>'
		);
		$('#hsm-howto-list').append($row);
		$row.find('input').first().focus();
	});

	$(document).on('click', '.hsm-remove-howto-row', function () {
		if ($('#hsm-howto-list .hsm-howto-row').length > 1) {
			$(this).closest('.hsm-howto-row').remove();
		} else {
			$(this).closest('.hsm-howto-row').find('input, textarea').val('');
		}
	});

	if (typeof $.fn.sortable !== 'undefined') {
		$('#hsm-howto-list').sortable({ handle: '.hsm-faq-handle', axis: 'y', tolerance: 'pointer' });
	}

	// ─── Secondary location blocks ────────────────────────────────────
	$('#hsm-add-location').on('click', function () {
		var $block = $(
			'<div class="hsm-location-block">' +
			'<div class="hsm-location-block-header">' +
			'<input type="text" name="hsm_loc_label[]" value="" class="regular-text hsm-location-label-input" placeholder="Location name (e.g. Boalsburg Office)">' +
			'<button type="button" class="button hsm-remove-location">Remove</button>' +
			'</div>' +
			'<div class="hsm-location-block-fields">' +
			'<input type="text" name="hsm_loc_street[]" value="" class="regular-text" placeholder="Street Address">' +
			'<input type="text" name="hsm_loc_city[]"   value="" class="regular-text" placeholder="City">' +
			'<input type="text" name="hsm_loc_state[]"  value="" class="small-text"   placeholder="State">' +
			'<input type="text" name="hsm_loc_zip[]"    value="" class="small-text"   placeholder="ZIP">' +
			'</div>' +
			'</div>'
		);
		$('#hsm-locations-list').append($block);
		$block.find('.hsm-location-label-input').focus();
	});

	$(document).on('click', '.hsm-remove-location', function () {
		var $list = $('#hsm-locations-list');
		if ($list.find('.hsm-location-block').length > 1) {
			$(this).closest('.hsm-location-block').remove();
		} else {
			$(this).closest('.hsm-location-block').find('input').val('');
		}
	});

	// ─── Address → Lat/Long geocoding ─────────────────────────────────
	$('#hsm-find-coords').on('click', function () {
		var street = $('#hsm_street').val().trim();
		var city   = $('#hsm_city').val().trim();
		var state  = $('#hsm_state').val().trim();
		var zip    = $('#hsm_zip').val().trim();

		var parts  = [street, city, state, zip].filter(Boolean);
		if (!parts.length) {
			$('#hsm-geo-status').text('Please fill in an address first.');
			return;
		}

		var query   = parts.join(', ');
		var $btn    = $(this);
		var $status = $('#hsm-geo-status');

		$btn.prop('disabled', true).text('Searching\u2026');
		$status.text('');

		fetch(
			'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' +
			encodeURIComponent(query),
			{ headers: { 'Accept-Language': 'en-US,en' } }
		)
		.then(function (r) { return r.json(); })
		.then(function (data) {
			if (data && data.length > 0) {
				$('#hsm_latitude').val(parseFloat(data[0].lat).toFixed(6));
				$('#hsm_longitude').val(parseFloat(data[0].lon).toFixed(6));
				$status.css('color', '#00a32a').text('\u2713 Coordinates found! Save settings to keep them.');
			} else {
				$status.css('color', '#d63638').text('Address not found. Try a more specific address.');
			}
		})
		.catch(function () {
			$status.css('color', '#d63638').text('Network error. Please try again.');
		})
		.finally(function () {
			$btn.prop('disabled', false).text('Find Coordinates');
		});
	});

	// ─── Character counter for meta description ────────────────────────
	function updateCharCount($textarea) {
		var $counter = $textarea.closest('td').find('.hsm-char-count');
		if (!$counter.length) return;

		var current = $textarea.val().length;
		var max     = parseInt($counter.data('max'), 10) || 160;

		$counter.text(current + ' / ' + max + ' characters');
		$counter.toggleClass('hsm-over-limit', current > max);
	}

	$('#hsm_seo_description').on('input', function () {
		updateCharCount($(this));
	}).trigger('input');

	// ─── WP Media Picker ──────────────────────────────────────────────
	$(document).on('click', '.hsm-upload-image', function (e) {
		e.preventDefault();

		var $btn     = $(this);
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

	// ─── Content Analysis ─────────────────────────────────────────────

	/**
	 * Get the post content as plain text from whichever editor is active.
	 * Handles Gutenberg (block editor), TinyMCE (classic editor), and
	 * plain textarea fallback.
	 */
	function hsmGetEditorText() {
		// Gutenberg block editor.
		if (
			typeof wp !== 'undefined' &&
			wp.data &&
			wp.data.select &&
			wp.data.select('core/editor')
		) {
			try {
				var gb = wp.data.select('core/editor').getEditedPostContent();
				if (gb) return gb.replace(/<[^>]+>/g, ' ');
			} catch (e) {}
		}

		// TinyMCE classic editor.
		if (typeof tinymce !== 'undefined') {
			var ed = tinymce.get('content');
			if (ed && !ed.isHidden()) {
				return ed.getContent({ format: 'text' });
			}
		}

		// Plain textarea fallback.
		var raw = $('#content').val() || '';
		return raw.replace(/<[^>]+>/g, ' ');
	}

	/**
	 * Count non-overlapping case-insensitive occurrences of needle in haystack.
	 */
	function hsmCountOccurrences(haystack, needle) {
		if (!needle) return 0;
		var escaped = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		var re = new RegExp('\\b' + escaped + '\\b', 'gi');
		var matches = haystack.match(re);
		return matches ? matches.length : 0;
	}

	/**
	 * Count words in a string.
	 */
	function hsmWordCount(text) {
		var trimmed = text.replace(/\s+/g, ' ').trim();
		return trimmed ? trimmed.split(' ').length : 0;
	}

	/**
	 * Return a density level label + CSS modifier class.
	 */
	function hsmDensityLevel(pct) {
		if (pct === 0)    return { label: 'Not found',      cls: 'hsm-level-none' };
		if (pct < 0.5)    return { label: 'Too low',        cls: 'hsm-level-low' };
		if (pct < 1)      return { label: 'Could be more',  cls: 'hsm-level-fair' };
		if (pct <= 3)     return { label: 'Optimal',        cls: 'hsm-level-optimal' };
		if (pct <= 5)     return { label: 'High',           cls: 'hsm-level-high' };
		return              { label: 'Over-optimised',   cls: 'hsm-level-over' };
	}

	/**
	 * Schema suggestion rules.
	 * Each rule: { type, label, reason, test(text) → boolean }
	 */
	var hsmSuggestionRules = [
		{
			type: 'FAQPage',
			label: 'FAQPage',
			reason: 'Page contains question-and-answer patterns (multiple "?" or Q&A headings).',
			test: function (t) {
				var questions = (t.match(/\?/g) || []).length;
				return questions >= 3;
			},
		},
		{
			type: 'HowTo',
			label: 'HowTo',
			reason: 'Page contains step-by-step instructions (numbered steps or "Step N" patterns).',
			test: function (t) {
				return /step\s+\d|^\s*\d+\.\s+\w/im.test(t) ||
					/(first|second|third|then|next|finally)[,\s]/i.test(t);
			},
		},
		{
			type: 'Product',
			label: 'Product',
			reason: 'Page mentions prices, availability, or product-buying language.',
			test: function (t) {
				return /\$\s*[\d,]+(\.\d{2})?|\bprice\b|\bbuy\b|\bpurchase\b|\badd to cart\b|\bin stock\b/i.test(t);
			},
		},
		{
			type: 'Review',
			label: 'Review',
			reason: 'Page contains review or rating language.',
			test: function (t) {
				return /\b(stars?|rated|rating|review|recommend|out of 5|[1-5]\/5)\b/i.test(t);
			},
		},
		{
			type: 'Service',
			label: 'Service',
			reason: 'Page describes a service offering (service keywords detected).',
			test: function (t) {
				return /\b(service|repair|installation|replacement|cleaning|inspection|consultation|estimate|quote)\b/i.test(t);
			},
		},
		{
			type: 'LocalBusiness',
			label: 'LocalBusiness',
			reason: 'Page mentions business location, phone, or contact details.',
			test: function (t) {
				return /\b(call us|contact us|visit us|located at|serving|phone|address|\d{3}[-.\s]\d{3}[-.\s]\d{4})\b/i.test(t);
			},
		},
	];

	/**
	 * Build a keyword placement checklist (in title, meta desc, first para, headings).
	 */
	function hsmPlacementChecklist(keyword) {
		if (!keyword) return [];
		var kw  = keyword.toLowerCase();
		var re  = new RegExp('\\b' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
		var items = [];

		// Title tag override.
		var titleVal = $('#hsm_seo_title').val() || document.title || '';
		items.push({
			pass: re.test(titleVal),
			text: 'In page title',
		});

		// Meta description.
		var descVal = $('#hsm_seo_description').val();
		items.push({
			pass: re.test(descVal),
			text: 'In meta description',
		});

		// Focus keyword field itself is set.
		items.push({
			pass: !!keyword,
			text: 'Focus keyword is set',
		});

		return items;
	}

	/**
	 * Run the full content analysis and update the UI.
	 */
	function hsmRunAnalysis() {
		var keyword  = $('#hsm_focus_keyword').val().trim();
		var text     = hsmGetEditorText();
		var wordCnt  = hsmWordCount(text);

		// ── Keyword label ──
		$('#hsm-kd-keyword-label').text(keyword || '(none set)');

		if (!keyword) {
			$('#hsm-kd-results').hide();
			$('#hsm-kd-no-keyword').show();
		} else {
			$('#hsm-kd-no-keyword').hide();
			$('#hsm-kd-results').show();

			var count   = hsmCountOccurrences(text, keyword);
			var density = wordCnt > 0 ? (count / wordCnt * 100) : 0;
			var level   = hsmDensityLevel(density);

			// Bar: clamp at 4 % = 100 % visual width.
			var barPct  = Math.min(density / 4 * 100, 100);
			$('#hsm-kd-bar')
				.css('width', barPct + '%')
				.attr('class', 'hsm-meter-fill ' + level.cls);

			$('#hsm-kd-density').text(density.toFixed(2) + '%');
			$('#hsm-kd-count').text(count);
			$('#hsm-kd-words').text(wordCnt.toLocaleString());

			$('#hsm-kd-badge')
				.text(level.label)
				.attr('class', 'hsm-kd-badge ' + level.cls);

			// Placement checklist.
			var checkItems = hsmPlacementChecklist(keyword);
			var $cl = $('#hsm-kd-checklist').empty();
			$.each(checkItems, function (i, item) {
				$cl.append(
					'<div class="hsm-check-item ' + (item.pass ? 'hsm-check-pass' : 'hsm-check-fail') + '">' +
					(item.pass ? '&#10003;' : '&#10007;') + ' ' + item.text +
					'</div>'
				);
			});
		}

		// ── Schema suggestions ──
		var $list = $('#hsm-suggestions-list').empty();
		var found = [];

		$.each(hsmSuggestionRules, function (i, rule) {
			if (rule.test(text)) {
				found.push(rule);
			}
		});

		if (!found.length) {
			$list.html(
				'<p class="description">' +
				'No strong pattern signals found. Try adding more content, then scan again.' +
				'</p>'
			);
		} else {
			$.each(found, function (i, rule) {
				var alreadyOn = $('[name="hsm_schema_enable_' + rule.type + '"]').is(':checked');
				var $card = $(
					'<div class="hsm-suggestion-card">' +
					'<div class="hsm-suggestion-info">' +
					'<strong class="hsm-suggestion-type">' + rule.label + '</strong>' +
					'<span class="hsm-suggestion-reason">' + rule.reason + '</span>' +
					'</div>' +
					'<div class="hsm-suggestion-action">' +
					( alreadyOn
						? '<span class="hsm-suggestion-active">&#10003; Already enabled</span>'
						: '<button type="button" class="button button-small hsm-apply-suggestion" data-type="' + rule.type + '">Apply</button>'
					) +
					'</div>' +
					'</div>'
				);
				$list.append($card);
			});
		}
	}

	// Scan button click.
	$('#hsm-ca-scan').on('click', hsmRunAnalysis);

	// Apply suggestion → enable schema checkbox + switch to Schema tab.
	$(document).on('click', '.hsm-apply-suggestion', function () {
		var type = $(this).data('type');
		var $cb  = $('[name="hsm_schema_enable_' + type + '"]');
		if ($cb.length) {
			$cb.prop('checked', true).trigger('change');
			// Switch to Schema tab.
			$('[data-tab="hsm-tab-schema"]').trigger('click');
			// Scroll to the section.
			var $section = $('#hsm-schema-section-' + type.toLowerCase());
			if ($section.length) {
				$('html, body').animate({ scrollTop: $section.offset().top - 40 }, 300);
			}
		}
		// Refresh the suggestion card to show "Already enabled".
		hsmRunAnalysis();
	});

})(jQuery);
