/**
 * ECHoS SEO Analytics — Admin JS
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
	$(document).on('click', '.echs-tab-link', function () {
		var target = $(this).data('tab');
		$('.echs-tab-link').removeClass('active');
		$('.echs-tab-panel').removeClass('active');
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
	var $catDrop  = $('#echs_business_category');
	var $typeDrop = $('#echs_schema_type');

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
		var title = $('#echs_seo_title').val();
		var desc  = $('#echs_seo_description').val();
		$('#echs_og_title').val(title);
		$('#echs_og_description').val(desc);
	}

	function applyOgSync(animate) {
		var checked = $('#echs_og_same_as_meta').is(':checked');
		if (checked) {
			syncOgToMeta();
			if (animate) {
				$('#echs-og-custom-fields').slideUp(200);
			} else {
				$('#echs-og-custom-fields').hide();
			}
		} else {
			if (animate) {
				$('#echs-og-custom-fields').slideDown(200);
			} else {
				$('#echs-og-custom-fields').show();
			}
		}
	}

	$('#echs_og_same_as_meta').on('change', function () { applyOgSync(true); });
	applyOgSync(false); // initialise on load

	// Live sync when SEO fields change while OG is locked.
	$(document).on('input', '#echs_seo_title, #echs_seo_description', function () {
		if ($('#echs_og_same_as_meta').is(':checked')) {
			syncOgToMeta();
		}
	});

	// ─── Twitter (X) "same as SEO Meta" sync + collapse ──────────────
	function syncTwitterToMeta() {
		var title = $('#echs_seo_title').val();
		var desc  = $('#echs_seo_description').val();
		$('#echs_twitter_title').val(title);
		$('#echs_twitter_description').val(desc);
	}

	function applyTwitterSync(animate) {
		var checked = $('#echs_twitter_same_as_meta').is(':checked');
		if (checked) {
			syncTwitterToMeta();
			if (animate) {
				$('#echs-twitter-custom-fields').slideUp(200);
			} else {
				$('#echs-twitter-custom-fields').hide();
			}
		} else {
			if (animate) {
				$('#echs-twitter-custom-fields').slideDown(200);
			} else {
				$('#echs-twitter-custom-fields').show();
			}
		}
	}

	$('#echs_twitter_same_as_meta').on('change', function () { applyTwitterSync(true); });
	applyTwitterSync(false); // initialise on load

	// Live sync Twitter when SEO fields change while Twitter is locked.
	$(document).on('input', '#echs_seo_title, #echs_seo_description', function () {
		if ($('#echs_twitter_same_as_meta').is(':checked')) {
			syncTwitterToMeta();
		}
	});

	// ─── Repeatable rows (service areas, sameAs) ──────────────────────
	$(document).on('click', '.echs-add-area', function () {
		var target      = $(this).data('target');
		var name        = $(this).data('name');
		var type        = $(this).data('type') || 'text';
		var placeholder = type === 'url' ? 'https://' : '';

		var $row = $(
			'<div class="echs-repeatable-row">' +
			'<input type="' + type + '" name="' + name + '" value="" class="regular-text" placeholder="' + placeholder + '">' +
			'<button type="button" class="button echs-remove-row">Remove</button>' +
			'</div>'
		);

		$('#' + target).append($row);
		$row.find('input').focus();
	});

	$(document).on('click', '.echs-remove-row', function () {
		var $list = $(this).closest('[id]');
		if ($list.find('.echs-repeatable-row').length > 1) {
			$(this).closest('.echs-repeatable-row').remove();
		} else {
			$(this).closest('.echs-repeatable-row').find('input').val('');
		}
	});

	// ─── Team member rows ─────────────────────────────────────────────
	$(document).on('click', '.echs-add-team-member', function () {
		var $row = $(
			'<div class="echs-repeatable-row echs-team-row">' +
			'<input type="text" name="echs_team_name[]"      value="" placeholder="e.g. Jane Smith">' +
			'<input type="text" name="echs_team_job_title[]" value="" placeholder="e.g. CEO / Founder">' +
			'<input type="url"  name="echs_team_linkedin[]"  value="" placeholder="https://linkedin.com/in/...">' +
			'<input type="url"  name="echs_team_image[]"     value="" placeholder="optional">' +
			'<button type="button" class="button echs-remove-team-row">Remove</button>' +
			'</div>'
		);
		$('#' + $(this).data('target')).append($row);
		$row.find('input').first().focus();
	});

	$(document).on('click', '.echs-remove-team-row', function () {
		var $list = $('#echs-team-list');
		if ($list.find('.echs-team-row').length > 1) {
			$(this).closest('.echs-team-row').remove();
		} else {
			$(this).closest('.echs-team-row').find('input').val('');
		}
	});

	// ─── FAQ rows ─────────────────────────────────────────────────────
	$('#echs-add-faq-row').on('click', function () {
		var $row = $(
			'<div class="echs-faq-row">' +
			'<div class="echs-faq-handle">&#9776;</div>' +
			'<div class="echs-faq-fields">' +
			'<input type="text" name="echs_faq_question[]" placeholder="Question" class="large-text">' +
			'<textarea name="echs_faq_answer[]" rows="2" class="large-text" placeholder="Answer"></textarea>' +
			'</div>' +
			'<button type="button" class="button echs-remove-faq-row">Remove</button>' +
			'</div>'
		);
		$('#echs-faq-list').append($row);
		$row.find('input').first().focus();
	});

	$(document).on('click', '.echs-remove-faq-row', function () {
		if ($('#echs-faq-list .echs-faq-row').length > 1) {
			$(this).closest('.echs-faq-row').remove();
		} else {
			$(this).closest('.echs-faq-row').find('input, textarea').val('');
		}
	});

	if (typeof $.fn.sortable !== 'undefined') {
		$('#echs-faq-list').sortable({ handle: '.echs-faq-handle', axis: 'y', tolerance: 'pointer' });
	}

	// ─── HowTo step rows ──────────────────────────────────────────────
	$('#echs-add-howto-row').on('click', function () {
		var $row = $(
			'<div class="echs-howto-row">' +
			'<div class="echs-faq-handle">&#9776;</div>' +
			'<div class="echs-faq-fields">' +
			'<input type="text" name="echs_howto_step_name[]" placeholder="Step title (e.g. Remove damaged shingle)" class="large-text">' +
			'<textarea name="echs_howto_step_text[]" rows="2" class="large-text" placeholder="Step instructions\u2026"></textarea>' +
			'</div>' +
			'<button type="button" class="button echs-remove-howto-row">Remove</button>' +
			'</div>'
		);
		$('#echs-howto-list').append($row);
		$row.find('input').first().focus();
	});

	$(document).on('click', '.echs-remove-howto-row', function () {
		if ($('#echs-howto-list .echs-howto-row').length > 1) {
			$(this).closest('.echs-howto-row').remove();
		} else {
			$(this).closest('.echs-howto-row').find('input, textarea').val('');
		}
	});

	if (typeof $.fn.sortable !== 'undefined') {
		$('#echs-howto-list').sortable({ handle: '.echs-faq-handle', axis: 'y', tolerance: 'pointer' });
	}

	// ─── Secondary location blocks ────────────────────────────────────
	$('#echs-add-location').on('click', function () {
		var $block = $(
			'<div class="echs-location-block">' +
			'<div class="echs-location-block-header">' +
			'<input type="text" name="echs_loc_label[]" value="" class="regular-text echs-location-label-input" placeholder="Location name (e.g. Boalsburg Office)">' +
			'<button type="button" class="button echs-remove-location">Remove</button>' +
			'</div>' +
			'<div class="echs-location-block-fields">' +
			'<input type="text" name="echs_loc_street[]" value="" class="regular-text" placeholder="Street Address">' +
			'<input type="text" name="echs_loc_city[]"   value="" class="regular-text" placeholder="City">' +
			'<input type="text" name="echs_loc_state[]"  value="" class="small-text"   placeholder="State">' +
			'<input type="text" name="echs_loc_zip[]"    value="" class="small-text"   placeholder="ZIP">' +
			'</div>' +
			'</div>'
		);
		$('#echs-locations-list').append($block);
		$block.find('.echs-location-label-input').focus();
	});

	$(document).on('click', '.echs-remove-location', function () {
		var $list = $('#echs-locations-list');
		if ($list.find('.echs-location-block').length > 1) {
			$(this).closest('.echs-location-block').remove();
		} else {
			$(this).closest('.echs-location-block').find('input').val('');
		}
	});

	// ─── Address → Lat/Long geocoding ─────────────────────────────────
	$('#echs-find-coords').on('click', function () {
		var street = $('#echs_street').val().trim();
		var city   = $('#echs_city').val().trim();
		var state  = $('#echs_state').val().trim();
		var zip    = $('#echs_zip').val().trim();

		var parts  = [street, city, state, zip].filter(Boolean);
		if (!parts.length) {
			$('#echs-geo-status').text('Please fill in an address first.');
			return;
		}

		var query   = parts.join(', ');
		var $btn    = $(this);
		var $status = $('#echs-geo-status');

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
				$('#echs_latitude').val(parseFloat(data[0].lat).toFixed(6));
				$('#echs_longitude').val(parseFloat(data[0].lon).toFixed(6));
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
		var $counter = $textarea.closest('td').find('.echs-char-count');
		if (!$counter.length) return;

		var current = $textarea.val().length;
		var max     = parseInt($counter.data('max'), 10) || 160;

		$counter.text(current + ' / ' + max + ' characters');
		$counter.toggleClass('echs-over-limit', current > max);
	}

	$('#echs_seo_description').on('input', function () {
		updateCharCount($(this));
	}).trigger('input');

	// ─── Content Analysis ─────────────────────────────────────────────

	/**
	 * Get the post body as plain text from whichever editor is active.
	 * Handles Gutenberg, TinyMCE, and plain textarea.
	 */
	function echsGetEditorText() {
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

		if (typeof tinymce !== 'undefined') {
			var ed = tinymce.get('content');
			if (ed && !ed.isHidden()) {
				return ed.getContent({ format: 'text' });
			}
		}

		return ($('#content').val() || '').replace(/<[^>]+>/g, ' ');
	}

	function echsCountOccurrences(haystack, needle) {
		if (!needle) return 0;
		var escaped = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		var matches = haystack.match(new RegExp('\\b' + escaped + '\\b', 'gi'));
		return matches ? matches.length : 0;
	}

	function echsWordCount(text) {
		var trimmed = text.replace(/\s+/g, ' ').trim();
		return trimmed ? trimmed.split(' ').length : 0;
	}

	function echsDensityLevel(pct) {
		if (pct === 0) return { label: 'Not found',     cls: 'echs-level-none' };
		if (pct < 0.5) return { label: 'Too low',       cls: 'echs-level-low' };
		if (pct < 1)   return { label: 'Could be more', cls: 'echs-level-fair' };
		if (pct <= 3)  return { label: 'Optimal',       cls: 'echs-level-optimal' };
		if (pct <= 5)  return { label: 'High',          cls: 'echs-level-high' };
		return               { label: 'Over-optimised', cls: 'echs-level-over' };
	}

	function echsPlacementChecklist(keyword) {
		if (!keyword) return [];
		var re    = new RegExp('\\b' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
		var items = [];
		items.push({ pass: re.test($('#echs_seo_title').val() || document.title || ''), text: 'In page title' });
		items.push({ pass: re.test($('#echs_seo_description').val() || ''), text: 'In meta description' });
		items.push({ pass: true, text: 'Focus keyword is set' });
		return items;
	}

	var echsSuggestionRules = [
		{
			type: 'FAQPage', label: 'FAQPage',
			reason: 'Page contains question-and-answer patterns (multiple "?" or Q&A headings).',
			test: function (t) { return (t.match(/\?/g) || []).length >= 3; },
		},
		{
			type: 'HowTo', label: 'HowTo',
			reason: 'Page contains step-by-step instructions (numbered steps or "Step N" patterns).',
			test: function (t) {
				return /step\s+\d|^\s*\d+\.\s+\w/im.test(t) ||
					/(first|second|third|then|next|finally)[,\s]/i.test(t);
			},
		},
		{
			type: 'Product', label: 'Product',
			reason: 'Page mentions prices, availability, or product-buying language.',
			test: function (t) {
				return /\$\s*[\d,]+(\.\d{2})?|\bprice\b|\bbuy\b|\bpurchase\b|\badd to cart\b|\bin stock\b/i.test(t);
			},
		},
		{
			type: 'Review', label: 'Review',
			reason: 'Page contains review or rating language.',
			test: function (t) {
				return /\b(stars?|rated|rating|review|recommend|out of 5|[1-5]\/5)\b/i.test(t);
			},
		},
		{
			type: 'Service', label: 'Service',
			reason: 'Page describes a service offering (service keywords detected).',
			test: function (t) {
				return /\b(service|repair|installation|replacement|cleaning|inspection|consultation|estimate|quote)\b/i.test(t);
			},
		},
		{
			type: 'LocalBusiness', label: 'LocalBusiness',
			reason: 'Page mentions business location, phone, or contact details.',
			test: function (t) {
				return /\b(call us|contact us|visit us|located at|serving|phone|address|\d{3}[-.\s]\d{3}[-.\s]\d{4})\b/i.test(t);
			},
		},
	];

	/** Render keyword density and schema suggestions from a plain-text string. */
	function echsRenderAnalysis(text) {
		var keywords = echsGetKeywords();
		var keyword = keywords[0] || '';
		var wordCnt = echsWordCount(text);

		$('#echs-kd-keyword-label').text(keyword || '(none set)');

		if (!keyword) {
			$('#echs-kd-results').hide();
			$('#echs-kd-no-keyword').show();
			$('#echs-cluster-results').hide();
		} else {
			$('#echs-kd-no-keyword').hide();
			$('#echs-kd-results').show();

			var count   = echsCountOccurrences(text, keyword);
			var density = wordCnt > 0 ? (count / wordCnt * 100) : 0;
			var level   = echsDensityLevel(density);
			var barPct  = Math.min(density / 4 * 100, 100);

			$('#echs-kd-bar').css('width', barPct + '%').attr('class', 'echs-meter-fill ' + level.cls);
			$('#echs-kd-density').text(density.toFixed(2) + '%');
			$('#echs-kd-count').text(count);
			$('#echs-kd-words').text(wordCnt.toLocaleString());
			$('#echs-kd-badge').text(level.label).attr('class', 'echs-kd-badge ' + level.cls);

			var $cl = $('#echs-kd-checklist').empty();
			$.each(echsPlacementChecklist(keyword), function (i, item) {
				$cl.append(
					'<div class="echs-check-item ' + (item.pass ? 'echs-check-pass' : 'echs-check-fail') + '">' +
					(item.pass ? '&#10003;' : '&#10007;') + ' ' + item.text + '</div>'
				);
			});

			echsRenderCluster(text, keywords);
		}

		var $list = $('#echs-suggestions-list').empty();
		var found = $.grep(echsSuggestionRules, function (rule) { return rule.test(text); });

		if (!found.length) {
			$list.html('<p class="description">No strong pattern signals found. Try adding more content, then scan again.</p>');
		} else {
			$.each(found, function (i, rule) {
				var alreadyOn = $('[name="echs_schema_enable_' + rule.type + '"]').is(':checked');
				$list.append(
					'<div class="echs-suggestion-card">' +
					'<div class="echs-suggestion-info">' +
					'<strong class="echs-suggestion-type">' + rule.label + '</strong>' +
					'<span class="echs-suggestion-reason">' + rule.reason + '</span>' +
					'</div>' +
					'<div class="echs-suggestion-action">' +
					(alreadyOn
						? '<span class="echs-suggestion-active">&#10003; Already enabled</span>'
						: '<button type="button" class="button button-small echs-apply-suggestion" data-type="' + rule.type + '">Apply</button>'
					) +
					'</div></div>'
				);
			});
		}
	}

	/**
	 * Orchestrate the scan.
	 * For classic-editor pages: read content directly from the editor (instant).
	 * For ACF Flexible Content pages (editor is empty/tiny): call the AJAX
	 * scan endpoint so the rendered HTML — which includes all ACF output — is
	 * used for keyword density and schema suggestions.
	 */
	function echsRunAnalysis() {
		var $btn       = $('#echs-ca-scan');
		var editorText = echsGetEditorText();

		// If the editor has enough content, use it directly — no AJAX needed.
		if (editorText.trim().length >= 100) {
			echsRenderAnalysis(editorText);
			echsRenderReadability(editorText);
			return;
		}

		// ACF Flexible Content (or page builders) — editor will be near-empty.
		// Fall back to the rendered page via the AJAX scan endpoint.
		var postId = $('[data-post-id]').first().data('post-id');
		if (!postId) {
			echsRenderAnalysis(editorText);
			echsRenderReadability(editorText);
			return;
		}

		$btn.prop('disabled', true).text('Scanning\u2026');

		$.post(echsData.ajaxurl, {
			action:  'echs_scan_content',
			nonce:   echsData.nonce,
			post_id: postId,
		})
		.done(function (response) {
			var text = (response.success && response.data && response.data.page_text)
				? response.data.page_text
				: editorText;
			echsRenderAnalysis(text);
			echsRenderReadability(text);
		})
		.fail(function () {
			echsRenderAnalysis(editorText);
			echsRenderReadability(editorText);
		})
		.always(function () {
			$btn.prop('disabled', false).text('\u21bb Scan Content');
		});
	}

	$('#echs-ca-scan').on('click', echsRunAnalysis);

	$(document).on('click', '.echs-apply-suggestion', function () {
		var type = $(this).data('type');
		var $cb  = $('[name="echs_schema_enable_' + type + '"]');
		if ($cb.length) {
			$cb.prop('checked', true).trigger('change');
			$('[data-tab="echs-tab-schema"]').trigger('click');
			var $section = $('#echs-schema-section-' + type.toLowerCase());
			if ($section.length) {
				$('html, body').animate({ scrollTop: $section.offset().top - 40 }, 300);
			}
		}
		echsRunAnalysis();
	});

	// ─── Scan Content (Schema tab — AJAX) ────────────────────────────

	function echsEsc(str) {
		return $('<div>').text(String(str)).html().replace(/"/g, '&quot;');
	}

	function echsScanField(label, value, targetSelector) {
		var display = value.length > 200 ? value.substring(0, 200) + '…' : value;
		return '<div class="echs-scan-field">' +
			'<div class="echs-scan-field-label">' + echsEsc(label) + '</div>' +
			'<div class="echs-scan-field-value">' + echsEsc(display) + '</div>' +
			'<button type="button" class="button button-small echs-apply-field" ' +
				'data-target="' + echsEsc(targetSelector) + '" ' +
				'data-value="' + echsEsc(value) + '">' +
				'Apply to field' +
			'</button>' +
		'</div>';
	}

	$('#echs-scan-content').on('click', function () {
		var $btn     = $(this);
		var $status  = $('#echs-scan-status');
		var $results = $('#echs-scan-results');
		var postId   = $btn.data('post-id');

		$btn.prop('disabled', true).text('Scanning\u2026');
		$status.removeClass('echs-scan-ok echs-scan-err').text('');
		$results.hide().html('');

		$.post(echsData.ajaxurl, {
			action:  'echs_scan_content',
			nonce:   echsData.nonce,
			post_id: postId
		})
		.done(function (response) {
			$btn.prop('disabled', false).text('\uD83D\uDD0D Deep Scan');

			if (!response.success) {
				$status.addClass('echs-scan-err').text(response.data || 'Scan failed.');
				return;
			}

			var d    = response.data;
			var html = '<div class="echs-scan-panel">';
			html += '<p class="echs-scan-source">Source: <strong>' + echsEsc(d.source || 'post content') + '</strong></p>';

			// ── SEO fields ──
			var seoHtml = '';
			if (d.seo_title) {
				seoHtml += echsScanField('SEO Title', d.seo_title, '#echs_seo_title');
			}
			if (d.seo_descriptions && d.seo_descriptions.length) {
				$.each(d.seo_descriptions, function (i, desc) {
					var label = d.seo_descriptions.length > 1
						? 'Meta Description ' + (i + 1)
						: 'Meta Description';
					seoHtml += echsScanField(label, desc.substring(0, 160), '#echs_seo_description');
				});
			} else if (d.seo_description) {
				seoHtml += echsScanField('Meta Description', d.seo_description, '#echs_seo_description');
			}
			if (seoHtml) html += '<div class="echs-scan-group"><h4>SEO</h4>' + seoHtml + '</div>';

			// ── Service fields ──
			var svcHtml = '';
			if (d.service_name)        svcHtml += echsScanField('Service Name',        d.service_name,        '#echs_service_name');
			if (d.service_description) svcHtml += echsScanField('Service Description', d.service_description, '#echs_service_description');
			if (svcHtml) html += '<div class="echs-scan-group"><h4>Service</h4>' + svcHtml + '</div>';

			// ── Product fields ──
			var prodHtml = '';
			if (d.product_name)        prodHtml += echsScanField('Product Name',        d.product_name,        '#echs_product_name');
			if (d.product_description) prodHtml += echsScanField('Product Description', d.product_description, '#echs_product_description');
			if (prodHtml) html += '<div class="echs-scan-group"><h4>Product</h4>' + prodHtml + '</div>';

			// ── FAQs ──
			if (d.faqs && d.faqs.length) {
				html += '<div class="echs-scan-group">';
				html += '<h4>FAQs found (' + d.faqs.length + ')' +
					' <button type="button" class="button button-small echs-apply-faqs">Apply all FAQs</button></h4>';
				html += '<ol class="echs-scan-list">';
				$.each(d.faqs, function (i, faq) {
					html += '<li><em>' + echsEsc(faq.question) + '</em></li>';
				});
				html += '</ol></div>';
			}

			// ── HowTo steps ──
			if (d.howto_steps && d.howto_steps.length) {
				html += '<div class="echs-scan-group">';
				html += '<h4>HowTo Steps found (' + d.howto_steps.length + ')' +
					' <button type="button" class="button button-small echs-apply-steps">Apply all Steps</button></h4>';
				html += '<ol class="echs-scan-list">';
				$.each(d.howto_steps, function (i, step) {
					html += '<li>' + echsEsc(step.name) + '</li>';
				});
				html += '</ol></div>';
			}

			// ── Suggested Keywords ──
			if (d.suggested_keywords && d.suggested_keywords.length) {
				html += '<div class="echs-scan-group">';
				html += '<h4>Suggested Keywords</h4>';
				html += '<div class="echs-scan-keywords">';
				$.each(d.suggested_keywords, function (i, kw) {
					html += '<div class="echs-scan-keyword-row">' +
						'<span class="echs-scan-keyword-text">' + echsEsc(kw) + '</span>' +
						'<button type="button" class="button button-small echs-apply-keyword" ' +
							'data-value="' + echsEsc(kw) + '">' +
							'Use as keyword' +
						'</button>' +
					'</div>';
				});
				html += '</div></div>';
			}

			var hasContent = !!(d.seo_title || (d.seo_descriptions && d.seo_descriptions.length) ||
				d.seo_description || d.service_name || d.product_name ||
				(d.faqs && d.faqs.length) || (d.howto_steps && d.howto_steps.length) ||
				(d.suggested_keywords && d.suggested_keywords.length));
			if (!hasContent) {
				html += '<p>No content patterns detected. The page may not be published yet or uses a custom layout not readable by the scanner.</p>';
			}

			html += '</div>';

			$results.data('scan-data', d).html(html).show();
			$status.addClass('echs-scan-ok').text('\u2713 Scan complete');
		})
		.fail(function () {
			$btn.prop('disabled', false).text('\uD83D\uDD0D Scan Content');
			$status.addClass('echs-scan-err').text('Request failed. Check your connection.');
		});
	});

	// Apply single field value.
	$(document).on('click', '.echs-apply-field', function () {
		var $target = $($(this).data('target'));
		var value   = $(this).data('value');
		if ($target.length) {
			$target.val(value).trigger('input');
			$(this).text('\u2713 Applied').prop('disabled', true);
		}
	});

	// Apply all FAQs — enables FAQPage toggle, populates #echs-faq-list.
	$(document).on('click', '.echs-apply-faqs', function () {
		var d = $('#echs-scan-results').data('scan-data');
		if (!d || !d.faqs || !d.faqs.length) return;

		$('[name="echs_schema_enable_FAQPage"]').prop('checked', true).trigger('change');
		$('#echs-faq-list').empty();

		$.each(d.faqs, function (i, faq) {
			var $row = $(
				'<div class="echs-faq-row">' +
				'<div class="echs-faq-handle">&#9776;</div>' +
				'<div class="echs-faq-fields">' +
				'<input type="text" name="echs_faq_question[]" class="large-text">' +
				'<textarea name="echs_faq_answer[]" rows="2" class="large-text"></textarea>' +
				'</div>' +
				'<button type="button" class="button echs-remove-faq-row">Remove</button>' +
				'</div>'
			);
			$row.find('[name="echs_faq_question[]"]').val(faq.question);
			$row.find('[name="echs_faq_answer[]"]').val(faq.answer);
			$('#echs-faq-list').append($row);
		});

		$(this).text('\u2713 Applied').prop('disabled', true);
	});

	// Apply keyword suggestion — sets primary focus keyword field.
	$(document).on('click', '.echs-apply-keyword', function () {
		var kw      = $(this).data('value');
		var $primary = $('#echs-keywords-list .echs-keyword-input').first();
		if ($primary.length) {
			$primary.val(kw).trigger('input');
			$(this).text('✓ Applied').prop('disabled', true);
		}
	});

	// Apply all HowTo steps — enables HowTo toggle, populates #echs-howto-list.
	$(document).on('click', '.echs-apply-steps', function () {
		var d = $('#echs-scan-results').data('scan-data');
		if (!d || !d.howto_steps || !d.howto_steps.length) return;

		$('[name="echs_schema_enable_HowTo"]').prop('checked', true).trigger('change');
		$('#echs-howto-list').empty();

		$.each(d.howto_steps, function (i, step) {
			var $row = $(
				'<div class="echs-howto-row">' +
				'<div class="echs-faq-handle">&#9776;</div>' +
				'<div class="echs-faq-fields">' +
				'<input type="text" name="echs_howto_step_name[]" class="large-text" placeholder="Step title">' +
				'<textarea name="echs_howto_step_text[]" rows="2" class="large-text" placeholder="Step instructions\u2026"></textarea>' +
				'</div>' +
				'<button type="button" class="button echs-remove-howto-row">Remove</button>' +
				'</div>'
			);
			$row.find('[name="echs_howto_step_name[]"]').val(step.name);
			$row.find('[name="echs_howto_step_text[]"]').val(step.text);
			$('#echs-howto-list').append($row);
		});

		$(this).text('\u2713 Applied').prop('disabled', true);
	});

	// ─── WP Media Picker ──────────────────────────────────────────────
	$(document).on('click', '.echs-upload-image', function (e) {
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

	// ─── Multi-keyword UI ─────────────────────────────────────────────────

	function echsGetKeywords() {
		var keywords = [];
		$('#echs-keywords-list .echs-keyword-input').each(function () {
			var val = $(this).val().trim();
			if (val) keywords.push(val);
		});
		if (!keywords.length) {
			var legacy = ($('#echs_focus_keyword').val() || '').trim();
			if (legacy) keywords.push(legacy);
		}
		return keywords;
	}

	$('#echs-add-keyword').on('click', function () {
		var $list = $('#echs-keywords-list');
		if ($list.find('.echs-keyword-row').length >= 5) return;
		var $row = $(
			'<div class="echs-keyword-row">' +
			'<span class="echs-keyword-label">Secondary</span>' +
			'<input type="text" name="echs_focus_keywords[]" value="" class="regular-text echs-keyword-input" placeholder="Secondary keyword">' +
			'<button type="button" class="button button-small echs-remove-keyword">Remove</button>' +
			'</div>'
		);
		$list.append($row);
		$row.find('input').focus();
		if ($list.find('.echs-keyword-row').length >= 5) {
			$('#echs-add-keyword').prop('disabled', true);
		}
	});

	$(document).on('click', '.echs-remove-keyword', function () {
		$(this).closest('.echs-keyword-row').remove();
		$('#echs-add-keyword').prop('disabled', false);
	});

	function echsRenderCluster(text, keywords) {
		var $c = $('#echs-cluster-results');
		if (!$c.length || keywords.length <= 1) { $c.hide(); return; }
		$c.show().empty();
		keywords.forEach(function (kw, i) {
			var count   = echsCountOccurrences(text, kw);
			var wordCnt = echsWordCount(text);
			var density = wordCnt > 0 ? (count / wordCnt * 100) : 0;
			var level   = echsDensityLevel(density);
			var barPct  = Math.min(density / 4 * 100, 100);
			$c.append(
				'<div class="echs-cluster-card">' +
				'<div class="echs-cluster-label">' + (i === 0 ? 'Primary' : 'Secondary') + ' keyword</div>' +
				'<div class="echs-cluster-keyword">' + $('<span>').text(kw).html() + '</div>' +
				'<div class="echs-cluster-mini-bar-track"><div class="echs-cluster-mini-bar-fill ' + level.cls + '" style="width:' + barPct.toFixed(1) + '%"></div></div>' +
				'<div class="echs-cluster-stats">' +
				'<span>Density: <strong>' + density.toFixed(2) + '%</strong></span> ' +
				'<span>Uses: <strong>' + count + '</strong></span> ' +
				'<span class="' + level.cls + '">' + level.label + '</span>' +
				'</div></div>'
			);
		});
	}

	// ─── Readability Analysis ─────────────────────────────────────────────

	function echsSyllables(word) {
		word = word.toLowerCase().replace(/[^a-z]/g, '');
		if (!word) return 1;
		if (!/le$/.test(word)) word = word.replace(/e$/, '');
		var matches = word.match(/[aeiouy]{1,2}/g);
		return Math.max(1, matches ? matches.length : 1);
	}

	function echsSentences(text) {
		return text.split(/[.!?]+(?:\s|$)/).filter(function (s) { return s.trim() !== ''; });
	}

	function echsWords(text) {
		return text.match(/\b[a-z']+\b/gi) || [];
	}

	function echsFleschScore(text) {
		var words     = echsWords(text);
		var sentences = echsSentences(text);
		if (sentences.length < 3 || words.length < 10) return null;
		var totalSyllables = 0;
		for (var i = 0; i < words.length; i++) totalSyllables += echsSyllables(words[i]);
		var score = 206.835
			- 1.015  * (words.length / sentences.length)
			- 84.6   * (totalSyllables / words.length);
		return Math.min(100, Math.max(0, score));
	}

	function echsPassivePct(sentences) {
		if (!sentences.length) return 0;
		var re = /\b(am|are|is|was|were|be|been|being)\s+\w+ed\b/i;
		var count = sentences.filter(function (s) { return re.test(s); }).length;
		return Math.round((count / sentences.length) * 100);
	}

	function echsTransitionPct(sentences) {
		if (!sentences.length) return 0;
		var transitions = [
			'however','therefore','furthermore','additionally','moreover',
			'consequently','meanwhile','nevertheless','subsequently','alternatively',
			'although','because','besides','conversely','eventually','finally',
			'firstly','generally','hence','in addition','in conclusion','in contrast',
			'in fact','in other words','in particular','in short','in summary',
			'indeed','instead','likewise','next','nonetheless','notably','otherwise',
			'overall','particularly','previously','rather','secondly','similarly',
			'since','specifically','still','thirdly','thus','ultimately',
			'whereas','while','yet'
		];
		var count = sentences.filter(function (s) {
			var lower = s.toLowerCase();
			return transitions.some(function (t) { return lower.indexOf(t) !== -1; });
		}).length;
		return Math.round((count / sentences.length) * 100);
	}

	function echsLongSentPct(sentences) {
		if (!sentences.length) return 0;
		var count = sentences.filter(function (s) {
			return echsWords(s).length > 20;
		}).length;
		return Math.round((count / sentences.length) * 100);
	}

	function echsLongParaPct(text) {
		var paras = text.split(/\n\n+/).filter(function (p) { return p.trim() !== ''; });
		if (!paras.length) return 0;
		var count = paras.filter(function (p) { return echsWords(p).length > 150; }).length;
		return Math.round((count / paras.length) * 100);
	}

	function echsRenderReadability(text) {
		var $container = $('#echs-readability-results');
		if (!$container.length) return;

		if (text.trim().length < 100) {
			$container.html('<p class="description">Add more content for a readability assessment.</p>');
			return;
		}

		var sentences    = echsSentences(text);
		var fleschScore  = echsFleschScore(text);
		var longSentPct  = echsLongSentPct(sentences);
		var passivePct   = echsPassivePct(sentences);
		var transitionPct = echsTransitionPct(sentences);
		var longParaPct  = echsLongParaPct(text);

		var checks = [];

		if (fleschScore !== null) {
			var fleschPass  = fleschScore >= 60;
			var fleschLabel = fleschScore >= 70 ? 'Easy to read'
				: fleschScore >= 60 ? 'Fairly easy'
				: fleschScore >= 50 ? 'Standard'
				: fleschScore >= 30 ? 'Difficult'
				: 'Very difficult';
			checks.push({
				pass:    fleschPass,
				display: 'Reading ease: ' + Math.round(fleschScore) + '/100 — ' + fleschLabel,
				hint:    'Use shorter sentences and simpler words.'
			});
		}

		checks.push({
			pass:    longSentPct <= 25,
			display: longSentPct + '% of sentences are over 20 words',
			hint:    'Break long sentences into two.'
		});

		checks.push({
			pass:    passivePct <= 10,
			display: passivePct + '% of sentences use passive voice',
			hint:    'Rewrite passive constructions in active voice.'
		});

		checks.push({
			pass:    transitionPct >= 30,
			display: transitionPct + '% of sentences use transition words',
			hint:    "Add words like ‘however’, ‘therefore’, ‘additionally’."
		});

		checks.push({
			pass:    longParaPct <= 20,
			display: longParaPct + '% of paragraphs exceed 150 words',
			hint:    'Break long paragraphs into shorter chunks.'
		});

		var passed = checks.filter(function (c) { return c.pass; }).length;
		var total  = checks.length;
		var ratio  = passed / total;
		var badgeClass, badgeLabel;
		if (ratio === 1) {
			badgeClass = 'echs-level-optimal';
			badgeLabel = 'Good';
		} else if (ratio >= 0.5) {
			badgeClass = 'echs-level-fair';
			badgeLabel = 'Needs work';
		} else {
			badgeClass = 'echs-level-low';
			badgeLabel = 'Poor';
		}

		var html = '<div class="echs-readability-card">'
			+ '<span class="echs-kd-badge ' + badgeClass + '">' + badgeLabel + '</span>'
			+ '<span class="echs-readability-score">' + passed + ' of ' + total + ' checks passed</span>'
			+ '<ul class="echs-readability-list">';

		checks.forEach(function (c) {
			var cls  = c.pass ? 'echs-check-pass' : 'echs-check-fail';
			var icon = c.pass ? '✓' : '✗';
			html += '<li class="echs-check-item ' + cls + '">'
				+ '<span class="echs-check-icon">' + icon + '</span> '
				+ '<span class="echs-check-text">' + c.display + '</span>';
			if (!c.pass) {
				html += ' <span class="echs-check-hint">' + c.hint + '</span>';
			}
			html += '</li>';
		});

		html += '</ul></div>';
		$container.html(html);
	}

})(jQuery);

