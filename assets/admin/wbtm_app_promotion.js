/* global wp */
(function (blocks, element, editor, components, i18n, renderer) {
  'use strict';
  var el = element.createElement;
  var __ = function (text) { return i18n.__(text, 'bus-ticket-booking-with-seat-reservation'); };
  var ServerSideRender = renderer.default || renderer;
  var fields = [ ['badge', 'Badge'], ['heading', 'Heading'], ['highlight', 'Highlighted heading'], ['description', 'Description'], ['featureOne', 'First feature'], ['featureTwo', 'Second feature'], ['featureThree', 'Third feature'], ['buttonNote', 'Button caption'], ['buttonLabel', 'Download button'], ['trustNote', 'Note below download button'] ];
  var attributes = { appVariant: { type: 'string', default: 'customer', enum: ['customer', 'operator'] } };
  fields.concat([['accentColor'], ['backgroundColor'], ['iconStyle']]).forEach(function (field) { attributes[field[0]] = { type: 'string' }; });
  blocks.registerBlockType('wbtm/app-download', {
    apiVersion: 3, attributes: attributes, supports: { html: false, multiple: true, align: ['wide', 'full'] },
    title: __('Bus App Download'), description: __('Choose Customer App or Operator App. Branding and the APK link follow that app’s global settings.'), category: 'widgets', icon: 'smartphone',
    edit: function (props) {
      var a = props.attributes;
      var operator = a.appVariant === 'operator';
      var blockProps = editor.useBlockProps();
      var controls = fields.map(function (field) {
        var key = field[0];
        return el(key === 'description' ? components.TextareaControl : components.TextControl, {
          key: key, label: __(field[1]), value: a[key] || '',
          help: ['heading', 'description', 'buttonLabel'].indexOf(key) !== -1 && a[key] === undefined ? __('Uses the selected app’s global promotion setting until you enter page-specific text.') : undefined,
          onChange: function (value) { var next = {}; next[key] = value; props.setAttributes(next); }
        });
      });
      return el(element.Fragment, null,
        el(editor.InspectorControls, null,
          el(components.PanelBody, { title: __('Banner content'), initialOpen: true },
            el(components.SelectControl, { label: __('Application'), value: a.appVariant || 'customer', options: [{ label: __('Customer App'), value: 'customer' }, { label: __('Operator App (PRO)'), value: 'operator' }], onChange: function (value) { props.setAttributes({ appVariant: value }); } }),
            el('p', null, operator ? __('Operator branding, APK link and promotion are managed in Bus > Mobile App > Operator App. Operator App requires compatible PRO.') : __('Customer branding and APK URL are managed in Bus > Mobile App > App branding & settings.')), controls),
          el(components.PanelBody, { title: __('Banner appearance'), initialOpen: false },
            el(components.SelectControl, { label: __('Icon'), value: a.iconStyle || 'bus', options: [{ label: operator ? __('Scanner icon') : __('Bus icon'), value: 'bus' }, { label: __('Global app logo'), value: 'logo' }], onChange: function (value) { props.setAttributes({ iconStyle: value }); } }),
            !operator && el(components.BaseControl, { label: __('Accent color') }, el(components.ColorPalette, { value: a.accentColor, onChange: function (value) { props.setAttributes({ accentColor: value }); } })),
            el(components.BaseControl, { label: __('Soft background color') }, el(components.ColorPalette, { value: a.backgroundColor, onChange: function (value) { props.setAttributes({ backgroundColor: value }); } }))
          )
        ),
        el('div', blockProps,
          el(components.Notice, { status: 'info', isDismissible: false }, operator ? __('Enable Operator App and its website download banner, then add an HTTPS Operator APK URL in Operator App settings. The banner stays hidden when PRO, enablement or its own link is unavailable. Operator branding uses the global operator color.') : __('Select this block and open its settings to edit the banner. Enable App Download Promotion and add an HTTPS APK URL in Mobile App settings to show it.')),
          el('div', { style: { pointerEvents: 'none' } }, el(ServerSideRender, { block: 'wbtm/app-download', attributes: a, httpMethod: 'POST' }))
        )
      );
    },
    save: function () { return null; }
  });
})(wp.blocks, wp.element, wp.blockEditor, wp.components, wp.i18n, wp.serverSideRender);
