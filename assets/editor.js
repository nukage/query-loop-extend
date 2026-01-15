( function( wp ) {
    var addFilter = wp.hooks.addFilter;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextareaControl = wp.components.TextareaControl;
    var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

    var withCustomQueryControls = createHigherOrderComponent( function( BlockEdit ) {
        return function( props ) {
            if ( props.name !== 'core/query' ) {
                return el( BlockEdit, props );
            }

            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var custom_query_php = attributes.custom_query_php;

            return el(
                Fragment,
                {},
                el( BlockEdit, props ),
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'Custom PHP Query', initialOpen: false },
                        el( TextareaControl, {
                            label: 'PHP Code (return array)',
                            value: custom_query_php || '',
                            onChange: function( value ) {
                                console.log('Query Loop Extend: Setting attribute custom_query_php to', value);
                                setAttributes( { custom_query_php: value } );
                            },
                            help: 'Enter PHP code that returns an array of query arguments. e.g., return [\'post_type\' => \'page\'];',
                            rows: 10
                        } )
                    )
                )
            );
        };
    }, 'withCustomQueryControls' );

    addFilter(
        'editor.BlockEdit',
        'query-loop-extend/with-custom-query-controls',
        withCustomQueryControls
    );

    function addAttributes( settings, name ) {
        if ( name !== 'core/query' ) {
            return settings;
        }
        return Object.assign( {}, settings, {
            attributes: Object.assign( {}, settings.attributes, {
                custom_query_php: {
                    type: 'string',
                    default: '',
                },
            } ),
        } );
    }

    addFilter(
        'blocks.registerBlockType',
        'query-loop-extend/custom-query-attributes',
        addAttributes
    );

    // Patch if already registered
    wp.domReady( function() {
        var queryBlock = wp.blocks.getBlockType('core/query');
        if ( queryBlock ) {
            if ( ! queryBlock.attributes.custom_query_php ) {
                console.log('Query Loop Extend: Patching core/query attributes (Direct Patch)');
                queryBlock.attributes.custom_query_php = {
                    type: 'string',
                    default: '',
                };
            } else {
                 console.log('Query Loop Extend: core/query attributes already has custom_query_php');
            }
        } else {
            console.log('Query Loop Extend: core/query block not found even on domReady.');
        }
    } );
} )( window.wp );
