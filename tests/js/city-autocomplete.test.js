import { test } from 'node:test';
import assert from 'node:assert/strict';

class StubClassList {
  constructor(element) {
    this.element = element;
    this.classes = new Set();
  }

  add(...classes) {
    classes.forEach((cls) => this.classes.add(cls));
  }

  remove(...classes) {
    classes.forEach((cls) => this.classes.delete(cls));
  }

  contains(cls) {
    return this.classes.has(cls);
  }
}

class StubElement {
  constructor(tagName, document) {
    this.tagName = tagName.toUpperCase();
    this.document = document;
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.dataset = {};
    this.listeners = {};
    this.classList = new StubClassList(this);
    this.textContent = '';
    this._value = '';
  }

  set id(value) {
    this.attributes.id = value;
    if (this.document) {
      this.document.elements.set(value, this);
    }
  }

  get id() {
    return this.attributes.id || '';
  }

  set className(value) {
    this.classList = new StubClassList(this);
    value.split(/\s+/).filter(Boolean).forEach((cls) => this.classList.add(cls));
  }

  get className() {
    return Array.from(this.classList.classes).join(' ');
  }

  setAttribute(name, value) {
    if (name === 'id') {
      this.id = value;
      return;
    }
    this.attributes[name] = String(value);
  }

  getAttribute(name) {
    if (name === 'id') {
      return this.id;
    }
    return this.attributes[name];
  }

  removeAttribute(name) {
    if (name === 'id') {
      if (this.document) {
        this.document.elements.delete(this.id);
      }
      delete this.attributes[name];
      return;
    }
    delete this.attributes[name];
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  removeChild(child) {
    const index = this.children.indexOf(child);
    if (index !== -1) {
      this.children.splice(index, 1);
      child.parentNode = null;
    }
  }

  addEventListener(type, callback) {
    if (!this.listeners[type]) {
      this.listeners[type] = [];
    }
    this.listeners[type].push(callback);
  }

  dispatchEvent(event) {
    const listeners = this.listeners[event.type] || [];
    const eventObject = {
      ...event,
      target: event.target || this,
      preventDefault: event.preventDefault || (() => {}),
    };
    listeners.forEach((listener) => listener.call(this, eventObject));
    if (eventObject.bubbles && this.parentNode) {
      this.parentNode.dispatchEvent(eventObject);
    }
  }

  querySelectorAll(selector) {
    if (selector === '[role="option"]') {
      return this.children.filter((child) => child.getAttribute('role') === 'option');
    }
    return [];
  }

  set innerHTML(value) {
    if (value === '') {
      this.children.forEach((child) => {
        child.parentNode = null;
      });
      this.children = [];
    }
  }

  get innerHTML() {
    return '';
  }

  scrollIntoView() {
    // no-op for tests
  }

  closest() {
    return this.parentNode;
  }

  set value(v) {
    this._value = v;
  }

  get value() {
    return this._value;
  }
}

class StubDocument {
  constructor() {
    this.elements = new Map();
    this.body = new StubElement('body', this);
    this.listeners = {};
  }

  createElement(tag) {
    return new StubElement(tag, this);
  }

  getElementById(id) {
    return this.elements.get(id) || null;
  }

  addEventListener(type, callback) {
    if (!this.listeners[type]) {
      this.listeners[type] = [];
    }
    this.listeners[type].push(callback);
  }

  dispatchEvent(event) {
    (this.listeners[event.type] || []).forEach((listener) => listener(event));
  }
}

function setupDom() {
  const documentStub = new StubDocument();
  global.document = documentStub;
  global.window = global;

  const wrapper = documentStub.createElement('div');
  wrapper.id = 'cidade-combobox';
  wrapper.classList.add('hidden');

  const input = documentStub.createElement('input');
  input.id = 'cidade';
  wrapper.appendChild(input);

  const ufInput = documentStub.createElement('input');
  ufInput.id = 'estado';

  const list = documentStub.createElement('div');
  list.id = 'cidade-options';
  list.classList.add('hidden');
  wrapper.appendChild(list);

  const status = documentStub.createElement('p');
  status.id = 'cidade-status';
  status.classList.add('hidden');
  wrapper.appendChild(status);

  documentStub.body.appendChild(wrapper);

  return { documentStub, wrapper, input, ufInput, list, status };
}

await import('../../assets/js/city-autocomplete.js');
const { CityAutocomplete } = global;

function flushTimers() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

async function waitFor(condition, attempts = 10) {
  for (let i = 0; i < attempts; i += 1) {
    if (condition()) {
      return;
    }
    await flushTimers();
  }
  throw new Error('Condition not met within allotted attempts');
}

test('input "aba" displays city list', async () => {
  const { wrapper, input, ufInput, list, status } = setupDom();
  let fetchCalls = 0;
  const fetchCities = async () => {
    fetchCalls += 1;
    return [{ cNome: 'Abadia dos Dourados', cUF: 'MG' }];
  };

  const autocomplete = CityAutocomplete.init({
    input,
    ufInput,
    list,
    statusElement: status,
    wrapper,
    fetchCities,
    debounceDelay: 0,
  });

  input.value = 'aba';
  await autocomplete.performSearch('aba', null);
  await waitFor(() => fetchCalls > 0);
  await waitFor(() => autocomplete.cities.length > 0);
  await flushTimers();

  assert.equal(list.children.length, 1, 'Should render one option');
  assert.equal(list.classList.contains('hidden'), false, 'List should be visible');
  assert.equal(status.classList.contains('hidden'), true, 'Status message should be hidden when results exist');
  autocomplete.closeList();
});

test('selecting a city fills city and UF fields', async () => {
  const { wrapper, input, ufInput, list, status } = setupDom();
  let fetchCalls = 0;
  const fetchCities = async () => {
    fetchCalls += 1;
    return [{ cNome: 'Abadia dos Dourados', cUF: 'MG' }];
  };

  const autocomplete = CityAutocomplete.init({
    input,
    ufInput,
    list,
    statusElement: status,
    wrapper,
    fetchCities,
    debounceDelay: 0,
  });

  input.value = 'abadia';
  await autocomplete.performSearch('abadia', null);
  await waitFor(() => fetchCalls > 0);
  await waitFor(() => autocomplete.cities.length > 0);
  await flushTimers();

  const option = list.children[0];
  option.dispatchEvent({ type: 'mousedown', target: option, preventDefault() {} });

  assert.equal(input.value, 'Abadia dos Dourados');
  assert.equal(ufInput.value, 'MG');
  assert.equal(list.classList.contains('hidden'), true, 'List should be hidden after selection');
});

test('onSelect and onClear callbacks are triggered', async () => {
  const { wrapper, input, ufInput, list, status } = setupDom();
  const fetchCities = async () => [{ cNome: 'Abadia dos Dourados', cUF: 'MG' }];
  let selectedCity = null;
  let clearedCount = 0;

  const autocomplete = CityAutocomplete.init({
    input,
    ufInput,
    list,
    statusElement: status,
    wrapper,
    fetchCities,
    debounceDelay: 0,
    onSelect: (city) => {
      selectedCity = city;
    },
    onClear: () => {
      clearedCount += 1;
    },
  });

  input.value = 'abadia';
  await autocomplete.performSearch('abadia', null);
  await waitFor(() => autocomplete.cities.length > 0);
  const option = list.children[0];
  option.dispatchEvent({ type: 'mousedown', target: option, preventDefault() {} });

  assert.ok(selectedCity, 'onSelect should capture the selected city');
  assert.equal(selectedCity.cNome, 'Abadia dos Dourados');

  input.value = 'aba';
  autocomplete.handleInput({ target: input });
  assert.ok(clearedCount >= 1, 'onClear should be called when typing a new value');
});

