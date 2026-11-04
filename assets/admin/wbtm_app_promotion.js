/* global wp */
(function (blocks, element, editor, components, i18n, renderer) {
  'use strict';
  var el = element.createElement;
  var __ = function (text) { return i18n.__(text, 'bus-ticket-booking-with-seat-reservation'); };
  var ServerSideRender = renderer.default || renderer;
  var fields = [ ['badge', 'Badge'], ['heading', 'Heading'], ['highlight', 'Highlighted heading'], ['description', 'Description'], ['featureOne', 'First feature'], ['featureTwo', 'Second feature'], ['featureThree', 'Third feature'], ['buttonNote', 'Button caption'], ['buttonLabel', 'Download button'], ['trustNote', 'Note below download button'] ];
  var attributes = {};
  fields.concat([['accentColor'], ['backgroundColor'], ['iconStyle']]).forEach(function (field) { attributes[field[0]] = { type: 'string' }; });
  blocks.registerBlockType('wbtm/app-download', {
    apiVersion: 3, attributes: attributes, supports: { html: false, multiple: false, align: ['wide', 'full'] },
    title: __('Bus App Download'), description: __('Edit the banner here. The app name, logo and download URL follow Bus > Settings > Mobile App.'), category: 'widgets', icon: 'smartphone',
    edit: function (props) {
      var a = props.attributes;
      var blockProps = editor.useBlockProps();
      var controls = fields.map(function (field) {
        var key = field[0];
        return el(key === 'description' ? components.TextareaControl : components.TextControl, {
          key: key, label: __(field[1]), value: a[key] || '',
          help: ['heading', 'description', 'buttonLabel'].indexOf(key) !== -1 && a[key] === undefined ? __('Uses the global Mobile App setting until you enter page-specific text.') : undefined,
          onChange: function (value) { var next = {}; next[key] = value; props.setAttributes(next); }
        });
      });
      return el(element.Fragment, null,
        el(editor.InspectorControls, null,
          el(components.PanelBody, { title: __('Banner content'), initialOpen: true }, el('p', null, __('App name and APK URL are managed in Bus > Settings > Mobile App.')), controls),
          el(components.PanelBody, { title: __('Banner appearance'), initialOpen: false },
            el(components.SelectControl, { label: __('Icon'), value: a.iconStyle || 'bus', options: [{ label: __('Bus icon'), value: 'bus' }, { label: __('Global app logo'), value: 'logo' }], onChange: function (value) { props.setAttributes({ iconStyle: value }); } }),
            el(components.BaseControl, { label: __('Accent color') }, el(components.ColorPalette, { value: a.accentColor, onChange: function (value) { props.setAttributes({ accentColor: value }); } })),
            el(components.BaseControl, { label: __('Soft background color') }, el(components.ColorPalette, { value: a.backgroundColor, onChange: function (value) { props.setAttributes({ backgroundColor: value }); } }))
          )
        ),
        el('div', blockProps,
          el(components.Notice, { status: 'info', isDismissible: false }, __('Select this block and open its settings to edit the banner. Enable App Download Promotion and add an HTTPS APK URL in Mobile App settings to show it.')),
          el('div', { style: { pointerEvents: 'none' } }, el(ServerSideRender, { block: 'wbtm/app-download', attributes: a, httpMethod: 'POST' }))
        )
      );
    },
    save: function () { return null; }
  });
})(wp.blocks, wp.element, wp.blockEditor, wp.components, wp.i18n, wp.serverSideRender);
