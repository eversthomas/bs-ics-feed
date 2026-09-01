/**
 * Gutenberg Block Script für BS WP ICS Feed Reader.
 * Registriert den Block 'bs-ics/calendar' in der Kategorie 'bs-plugins'
 * mit useBlockProps für zuverlässige Selektion und vollständigen InspectorControls.
 */
(function (wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el                = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var ServerSideRender  = wp.serverSideRender || wp.components.ServerSideRender;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var RangeControl      = wp.components.RangeControl;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;
	var FormTokenField    = wp.components.FormTokenField;
	var Placeholder       = wp.components.Placeholder;

	var blockData = (typeof bsIcsBlockData !== 'undefined') ? bsIcsBlockData : { feeds: [], i18n: {} };
	var i18n      = blockData.i18n || {};
	var feedsList = blockData.feeds || [];

	// Hilfsfunktionen für das "Weitere Kalender kombinieren"-Feld: FormTokenField
	// arbeitet mit sichtbaren Feed-Titeln (Tokens), das Attribut speichert dagegen eine
	// kommagetrennte Liste von Feed-IDs (wie vom Renderer beim Zusammenführen erwartet).
	function feedIdsToTitleTokens(csv) {
		if (!csv) {
			return [];
		}
		var titleById = {};
		feedsList.forEach(function (f) { if (f.value) { titleById[f.value] = f.label; } });
		return csv.split(',')
			.map(function (s) { return parseInt(s, 10); })
			.filter(function (id) { return !!id; })
			.map(function (id) { return titleById[id] || ('#' + id); });
	}

	function titleTokensToIdsCsv(tokens, excludeId) {
		var idByTitle = {};
		feedsList.forEach(function (f) { if (f.value) { idByTitle[f.label] = f.value; } });
		var ids = tokens
			.map(function (t) { return idByTitle[t]; })
			.filter(function (id) { return id && id !== excludeId; });
		return ids.join(',');
	}

	registerBlockType('bs-ics/calendar', {
		apiVersion: 2,
		title: i18n.title || 'ICS Kalender-Feed',
		description: i18n.description || 'Zeigt Termine aus einem konfigurierten ICS-Kalender-Feed an.',
		icon: 'calendar-alt',
		category: 'bs-plugins',
		keywords: ['ics', 'ical', 'kalender', 'calendar', 'events', 'termine', 'bs'],
		supports: {
			html: false,
			align: ['wide', 'full']
		},
		attributes: {
			id: {
				type: 'number',
				default: 0
			},
			ids: {
				type: 'string',
				default: ''
			},
			layout: {
				type: 'string',
				default: ''
			},
			columns: {
				type: 'number',
				default: 0
			},
			limit: {
				type: 'number',
				default: 0
			},
			sort: {
				type: 'string',
				default: ''
			},
			only_future: {
				type: 'boolean',
				default: true
			},
			style: {
				type: 'string',
				default: ''
			},
			inherit_theme_colors: {
				type: 'boolean',
				default: false
			},
			accent: {
				type: 'string',
				default: ''
			},
			bg_color: {
				type: 'string',
				default: ''
			},
			shadow_style: {
				type: 'string',
				default: ''
			},
			card_padding: {
				type: 'string',
				default: ''
			},
			border_radius: {
				type: 'number',
				default: -1
			},
			border_width: {
				type: 'number',
				default: -1
			},
			border_color: {
				type: 'string',
				default: ''
			},
			mode: {
				type: 'string',
				default: ''
			},
			filter: {
				type: 'boolean',
				default: true
			},
			export: {
				type: 'boolean',
				default: true
			},
			csv: {
				type: 'boolean',
				default: true
			}
		},

		edit: function (props) {
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;

			var hasFeedSelected = attributes.id && attributes.id > 0;

			// useBlockProps stellt sicher, dass der Block in Gutenberg sauber ausgewählt, fokussiert
			// und in der rechten Seitenleiste (Inspector) angezeigt werden kann.
			var blockProps = useBlockProps ? useBlockProps({
				className: 'bs-ics-gutenberg-block'
			}) : { className: 'bs-ics-gutenberg-block' };

			// Sidebar Inspector Controls
			var inspector = el(
				InspectorControls,
				{},
				// Panel 1: Feed-Auswahl
				el(
					PanelBody,
					{ title: i18n.feedSelect || 'Kalender-Feed', initialOpen: true },
					el(SelectControl, {
						label: i18n.feedSelect || 'Kalender-Feed',
						value: attributes.id,
						options: feedsList,
						onChange: function (val) {
							var newId = parseInt(val, 10) || 0;
							// Falls der neu gewählte primäre Feed bereits in der Zusatzliste steckt, dort entfernen.
							setAttributes({
								id: newId,
								ids: titleTokensToIdsCsv(feedIdsToTitleTokens(attributes.ids), newId)
							});
						}
					}),
					hasFeedSelected ? el(FormTokenField, {
						label: i18n.additionalFeeds || 'Weitere Kalender kombinieren (optional)',
						value: feedIdsToTitleTokens(attributes.ids),
						suggestions: feedsList
							.filter(function (f) { return f.value && f.value !== attributes.id; })
							.map(function (f) { return f.label; }),
						onChange: function (tokens) {
							setAttributes({ ids: titleTokensToIdsCsv(tokens, attributes.id) });
						},
						__experimentalExpandOnFocus: true,
						__next40pxDefaultSize: true
					}) : null,
					hasFeedSelected ? el(
						'p',
						{ className: 'components-base-control__help', style: { marginTop: '-8px' } },
						i18n.additionalFeedsDesc || 'Termine aus zusätzlich ausgewählten Kalendern werden mit dem oben gewählten Kalender zusammengeführt und farblich unterschieden angezeigt.'
					) : null
				),
				// Panel 2: Darstellung & Sortierung
				el(
					PanelBody,
					{ title: i18n.displaySettings || 'Darstellungs-Optionen', initialOpen: false },
					el(SelectControl, {
						label: i18n.layout || 'Layout',
						value: attributes.layout,
						options: [
							{ value: '', label: i18n.layoutDefault || 'Standard aus Feed' },
							{ value: 'grid', label: i18n.grid || 'Kachel-Raster (Grid)' },
							{ value: 'list', label: i18n.list || 'Listenansicht (List)' }
						],
						onChange: function (val) { setAttributes({ layout: val }); }
					}),
					el(RangeControl, {
						label: i18n.columns || 'Spalten (Desktop Grid)',
						value: attributes.columns || 0,
						min: 0,
						max: 4,
						help: '0 = Standard aus Feed-Einstellung',
						onChange: function (val) { setAttributes({ columns: parseInt(val, 10) || 0 }); }
					}),
					el(TextControl, {
						label: i18n.limit || 'Maximale Anzahl Termine (0 = alle)',
						type: 'number',
						value: attributes.limit,
						onChange: function (val) { setAttributes({ limit: parseInt(val, 10) || 0 }); }
					}),
					el(SelectControl, {
						label: i18n.sort || 'Sortierung',
						value: attributes.sort,
						options: [
							{ value: '', label: i18n.layoutDefault || 'Standard aus Feed' },
							{ value: 'asc', label: i18n.sortAsc || 'Chronologisch aufsteigend' },
							{ value: 'desc', label: i18n.sortDesc || 'Absteigend (späteste zuerst)' }
						],
						onChange: function (val) { setAttributes({ sort: val }); }
					}),
					el(ToggleControl, {
						label: i18n.onlyFuture || 'Nur anstehende Termine',
						checked: attributes.only_future,
						onChange: function (val) { setAttributes({ only_future: val }); }
					}),
					el(ToggleControl, {
						label: i18n.filter || 'Such- & Kategoriefilter anzeigen',
						checked: attributes.filter,
						onChange: function (val) { setAttributes({ filter: val }); }
					}),
					el(ToggleControl, {
						label: i18n.export || '„In Kalender eintragen“-Buttons',
						checked: attributes.export,
						onChange: function (val) { setAttributes({ export: val }); }
					}),
					el(ToggleControl, {
						label: i18n.csvExport || 'CSV-Export-Button anzeigen',
						checked: attributes.csv,
						onChange: function (val) { setAttributes({ csv: val }); }
					})
				),
				// Panel 3: Kachel-Design & Farben
				el(
					PanelBody,
					{ title: i18n.designSettings || 'Kachel-Design & Farben', initialOpen: false },
					el(SelectControl, {
						label: i18n.style || 'Design-Stil / Preset',
						value: attributes.style,
						options: [
							{ value: '', label: i18n.layoutDefault || 'Standard aus Feed' },
							{ value: 'card', label: i18n.styleCard || 'Klassisch (Card)' },
							{ value: 'flat', label: i18n.styleFlat || 'Minimal / Flat' },
							{ value: 'accent_header', label: i18n.styleHeader || 'Accent Header' }
						],
						onChange: function (val) { setAttributes({ style: val }); }
					}),
					el(ToggleControl, {
						label: i18n.inheritThemeColors || 'Theme-Farben erben (Text & Links)',
						checked: attributes.inherit_theme_colors,
						onChange: function (val) { setAttributes({ inherit_theme_colors: val }); }
					}),
					el(TextControl, {
						label: i18n.accentColor || 'Akzentfarbe (z. B. #0073aa)',
						value: attributes.accent,
						placeholder: '#0073aa',
						onChange: function (val) { setAttributes({ accent: val }); }
					}),
					el(TextControl, {
						label: i18n.bgColor || 'Kachel-Hintergrundfarbe (z. B. #ffffff)',
						value: attributes.bg_color,
						placeholder: '#ffffff',
						onChange: function (val) { setAttributes({ bg_color: val }); }
					}),
					el(SelectControl, {
						label: i18n.shadowStyle || 'Schatten-Stärke',
						value: attributes.shadow_style,
						options: [
							{ value: '', label: i18n.shadowDefault || 'Standard aus Feed' },
							{ value: 'none', label: i18n.shadowNone || 'Kein Schatten' },
							{ value: 'subtle', label: i18n.shadowSubtle || 'Dezent' },
							{ value: 'prominent', label: i18n.shadowProminent || 'Ausgeprägt' }
						],
						onChange: function (val) { setAttributes({ shadow_style: val }); }
					}),
					el(SelectControl, {
						label: i18n.cardPadding || 'Kachel-Innenabstand',
						value: attributes.card_padding,
						options: [
							{ value: '', label: i18n.padDefault || 'Standard aus Feed' },
							{ value: 'compact', label: i18n.padCompact || 'Kompakt (12px)' },
							{ value: 'normal', label: i18n.padNormal || 'Standard (20px)' },
							{ value: 'spacious', label: i18n.padSpacious || 'Großzügig (28px)' }
						],
						onChange: function (val) { setAttributes({ card_padding: val }); }
					}),
					el(RangeControl, {
						label: i18n.borderRadius || 'Rahmenradius (px)',
						value: attributes.border_radius !== undefined ? attributes.border_radius : -1,
						min: -1,
						max: 40,
						help: '-1 = Standard aus Feed',
						onChange: function (val) { setAttributes({ border_radius: parseInt(val, 10) }); }
					}),
					el(RangeControl, {
						label: i18n.borderWidth || 'Rahmenbreite (px)',
						value: attributes.border_width !== undefined ? attributes.border_width : -1,
						min: -1,
						max: 10,
						help: '-1 = Standard aus Feed',
						onChange: function (val) { setAttributes({ border_width: parseInt(val, 10) }); }
					}),
					el(TextControl, {
						label: i18n.borderColor || 'Rahmenfarbe (z. B. #e2e8f0)',
						value: attributes.border_color,
						placeholder: '#e2e8f0',
						onChange: function (val) { setAttributes({ border_color: val }); }
					})
				)
			);

			// Editor Canvas Vorschau
			var content;
			if (!hasFeedSelected) {
				content = el(
					Placeholder,
					{
						icon: 'calendar-alt',
						label: i18n.title || 'ICS Kalender-Feed',
						instructions: i18n.placeholder || 'Bitte wähle in der rechten Seitenleiste oder im Dropdown einen Kalender-Feed aus.'
					},
					el(SelectControl, {
						value: attributes.id,
						options: feedsList,
						onChange: function (val) {
							setAttributes({ id: parseInt(val, 10) || 0 });
						}
					})
				);
			} else {
				content = el(
					'div',
					{ className: 'bs-ics-block-preview' },
					el(ServerSideRender, {
						block: 'bs-ics/calendar',
						attributes: attributes
					})
				);
			}

			return el('div', blockProps, inspector, content);
		},

		save: function () {
			return null; // Dynamischer Server-Side Render Block
		}
	});
})(window.wp);
