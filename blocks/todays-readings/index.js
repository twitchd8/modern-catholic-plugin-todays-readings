( function ( blocks, blockEditor, element, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'usccb-todays-readings/todays-readings', {
		edit: function ( props ) {
			return el(
				'div',
				useBlockProps(),
				el( ServerSideRender, {
					block: 'usccb-todays-readings/todays-readings',
					attributes: props.attributes
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.element,
	window.wp.serverSideRender
);

