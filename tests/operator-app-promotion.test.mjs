import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../assets/admin/wbtm_app_promotion.js', import.meta.url), 'utf8');
const metadata = JSON.parse(readFileSync(new URL('../blocks/app-download/block.json', import.meta.url), 'utf8'));
function fixture() {
  let settings;
  const node = (type, props, ...children) => ({ type, props: props || {}, children });
  const wp = {
    blocks: { registerBlockType: (name, value) => { assert.equal(name, 'wbtm/app-download'); settings = value; } },
    element: { createElement: node, Fragment: 'Fragment' },
    blockEditor: { useBlockProps: () => ({ className: 'fixture-block' }), InspectorControls: 'InspectorControls' },
    components: new Proxy({}, { get: (_, key) => key }),
    i18n: { __: text => text },
    serverSideRender: 'ServerSideRender',
  };
  vm.runInNewContext(source, { wp });
  return settings;
}
function flatten(node) {
  if (!node || typeof node !== 'object') return [];
  if (Array.isArray(node)) return node.flatMap(flatten);
  return [node, ...node.children.flatMap(flatten)];
}
test('metadata and actual editor agree on variants and dual block placement', () => {
  const block = fixture();
  assert.deepEqual(JSON.parse(JSON.stringify(block.attributes.appVariant)), metadata.attributes.appVariant);
  assert.equal(block.attributes.appVariant.default, 'customer');
  assert.equal(block.supports.multiple, true);
  assert.equal(metadata.supports.multiple, true);
  assert.equal(block.save(), null);
});
test('operator selection changes only variant and previews correct dynamic block', () => {
  const block = fixture();
  const changes = [];
  const tree = flatten(block.edit({ attributes: { appVariant: 'operator' }, setAttributes: value => changes.push(value) }));
  const selector = tree.find(node => node.type === 'SelectControl' && node.props.label === 'Application');
  assert.equal(selector.props.value, 'operator');
  assert.equal(selector.props.options[1].value, 'operator');
  selector.props.onChange('customer');
  assert.deepEqual(JSON.parse(JSON.stringify(changes)), [{ appVariant: 'customer' }]);
  const preview = tree.find(node => node.type === 'ServerSideRender');
  assert.equal(preview.props.attributes.appVariant, 'operator');
  assert.equal(preview.props.httpMethod, 'POST');
  const notice = tree.find(node => node.type === 'Notice').children.join('');
  assert.match(notice, /PRO, enablement or its own link is unavailable/);
  assert.match(notice, /global operator color/);
  assert.equal(tree.some(node => node.type === 'BaseControl' && node.props.label === 'Accent color'), false);
});
test('legacy customer editor keeps content, appearance and safe setup guidance', () => {
  const tree = flatten(fixture().edit({ attributes: {}, setAttributes: () => {} }));
  assert.equal(tree.find(node => node.type === 'SelectControl' && node.props.label === 'Application').props.value, 'customer');
  assert.ok(tree.some(node => node.type === 'TextControl' && node.props.label === 'Heading'));
  assert.ok(tree.some(node => node.type === 'TextareaControl' && node.props.label === 'Description'));
  assert.ok(tree.some(node => node.type === 'BaseControl' && node.props.label === 'Accent color'));
  assert.match(tree.find(node => node.type === 'Notice').children.join(''), /Enable App Download Promotion/);
});
