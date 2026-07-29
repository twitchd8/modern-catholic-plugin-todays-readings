( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'usccb-todays-readings/todays-readings', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Readings display', 'usccb-todays-readings' ),
							initialOpen: true
						},
						el( TextControl, {
							label: __( 'Heading', 'usccb-todays-readings' ),
							value: attributes.heading || '',
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'usccb-todays-readings' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Text and links', 'usccb-todays-readings' ), value: 'compact' },
								{ label: __( 'Cards', 'usccb-todays-readings' ), value: 'cards' }
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show date', 'usccb-todays-readings' ),
							checked: attributes.showDate,
							onChange: function ( value ) {
								setAttributes( { showDate: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show liturgical color', 'usccb-todays-readings' ),
							checked: attributes.showLiturgicalColor,
							onChange: function ( value ) {
								setAttributes( { showLiturgicalColor: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show lectionary number', 'usccb-todays-readings' ),
							checked: attributes.showLectionary,
							onChange: function ( value ) {
								setAttributes( { showLectionary: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show reading citations', 'usccb-todays-readings' ),
							checked: attributes.showCitations,
							onChange: function ( value ) {
								setAttributes( { showCitations: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show RSS description', 'usccb-todays-readings' ),
							checked: attributes.showDescription,
							onChange: function ( value ) {
								setAttributes( { showDescription: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show USCCB source link', 'usccb-todays-readings' ),
							checked: attributes.showSourceLink,
							onChange: function ( value ) {
								setAttributes( { showSourceLink: value } );
							}
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el(
						'p',
						{ className: 'usccb-todays-readings__editor-note' },
						__( 'Dynamic preview — the saved page contains only this placeholder. Cached readings populate automatically.', 'usccb-todays-readings' )
					),
					el( ServerSideRender, {
						block: 'usccb-todays-readings/todays-readings',
						attributes: attributes
					} )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n,
	window.wp.serverSideRender
);
