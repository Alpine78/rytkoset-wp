(function () {
  const SELECTOR = '.woocommerce-ordering select, .variations select';
  const QUANTITY_SELECTOR = '.woocommerce .quantity input.qty, .woocommerce-page .quantity input.qty';
  const ADD_TO_CART_SELECTOR = '.add_to_cart_button, .single_add_to_cart_button';
  let instanceId = 0;

  const focusableKeys = ['ArrowDown', 'ArrowUp', 'Home', 'End'];
  const shopConfig = window.rytkosetShopConfig || {};
  const soldIndividuallyCartProductIds = new Set(
    (shopConfig.soldIndividuallyCartProductIds || []).map((productId) => String(productId))
  );

  const getOptionLabel = (option) => option.textContent.trim() || option.value;

  const getEnabledOptions = (instance) =>
    instance.options.filter((option) => option.getAttribute('aria-disabled') !== 'true');

  const setNativeValue = (select, value) => {
    if (select.value === value) {
      return;
    }

    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const setActiveIndex = (instance, nextIndex) => {
    const optionCount = instance.options.length;

    if (!optionCount) {
      return;
    }

    const clamped = Math.max(0, Math.min(nextIndex, optionCount - 1));
    instance.activeIndex = clamped;
    instance.trigger.setAttribute('aria-activedescendant', instance.options[clamped].id);

    instance.options.forEach((option, index) => {
      option.classList.toggle('is-active', index === clamped);
    });

    instance.options[clamped].scrollIntoView({ block: 'nearest' });
  };

  const getSelectedIndex = (instance) => {
    const index = instance.options.findIndex((option) => option.dataset.value === instance.select.value);
    return index >= 0 ? index : 0;
  };

  const closeSelect = (instance, returnFocus = false) => {
    instance.root.classList.remove('is-open');
    instance.trigger.setAttribute('aria-expanded', 'false');
    instance.menu.hidden = true;

    if (returnFocus) {
      instance.trigger.focus();
    }
  };

  const closeAll = (except = null) => {
    document.querySelectorAll('.rytkoset-shop-select.is-open').forEach((root) => {
      const instance = root.rytkosetShopSelect;

      if (instance && instance !== except) {
        closeSelect(instance);
      }
    });
  };

  const updateSelectedState = (instance) => {
    const selectedOption = instance.select.options[instance.select.selectedIndex];
    const label = selectedOption ? getOptionLabel(selectedOption) : '';

    instance.value.textContent = label;

    instance.options.forEach((option) => {
      const isSelected = option.dataset.value === instance.select.value;
      option.classList.toggle('is-selected', isSelected);
      option.setAttribute('aria-selected', String(isSelected));
    });
  };

  const syncOptionState = (instance) => {
    instance.options.forEach((option, index) => {
      const nativeOption = instance.select.options[index];

      if (!nativeOption) {
        return;
      }

      option.dataset.value = nativeOption.value;
      option.textContent = getOptionLabel(nativeOption);
      option.classList.toggle('is-disabled', nativeOption.disabled);

      if (nativeOption.disabled) {
        option.setAttribute('aria-disabled', 'true');
      } else {
        option.removeAttribute('aria-disabled');
      }
    });

    updateSelectedState(instance);
  };

  const openSelect = (instance) => {
    syncOptionState(instance);
    closeAll(instance);
    instance.root.classList.add('is-open');
    instance.trigger.setAttribute('aria-expanded', 'true');
    instance.menu.hidden = false;
    setActiveIndex(instance, getSelectedIndex(instance));
  };

  const chooseOption = (instance, option) => {
    if (!option || option.getAttribute('aria-disabled') === 'true') {
      return;
    }

    setNativeValue(instance.select, option.dataset.value);
    updateSelectedState(instance);
    closeSelect(instance, true);
  };

  const moveActive = (instance, direction) => {
    const enabledOptions = getEnabledOptions(instance);

    if (!enabledOptions.length) {
      return;
    }

    const currentOption = instance.options[instance.activeIndex];
    const enabledIndex = enabledOptions.indexOf(currentOption);
    const nextEnabledIndex = (enabledIndex + direction + enabledOptions.length) % enabledOptions.length;
    const nextIndex = instance.options.indexOf(enabledOptions[nextEnabledIndex]);

    setActiveIndex(instance, nextIndex);
  };

  const buildCustomSelect = (select) => {
    if (select.dataset.rytkosetShopSelect === 'ready') {
      return;
    }

    const id = select.id || `rytkoset-shop-select-${++instanceId}`;
    select.id = id;
    select.dataset.rytkosetShopSelect = 'ready';
    select.classList.add('rytkoset-shop-select-native');

    const root = document.createElement('div');
    root.className = 'rytkoset-shop-select';

    if (select.closest('.woocommerce-ordering')) {
      root.classList.add('rytkoset-shop-select--align-right');
    }

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'rytkoset-shop-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', `${id}-listbox`);

    const prefix = document.createElement('span');
    prefix.className = 'rytkoset-shop-select__prefix';
    prefix.textContent = select.closest('.woocommerce-ordering') ? 'Lajittele' : '';

    const value = document.createElement('span');
    value.className = 'rytkoset-shop-select__value';

    const caret = document.createElement('span');
    caret.className = 'rytkoset-shop-select__caret';
    caret.setAttribute('aria-hidden', 'true');
    caret.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>';

    if (prefix.textContent) {
      trigger.append(prefix);
    }

    trigger.append(value, caret);

    const menu = document.createElement('ul');
    menu.id = `${id}-listbox`;
    menu.className = 'rytkoset-shop-select__menu';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;

    const options = Array.from(select.options).map((nativeOption, index) => {
      const option = document.createElement('li');
      option.id = `${id}-option-${index}`;
      option.className = 'rytkoset-shop-select__option';
      option.setAttribute('role', 'option');
      option.dataset.value = nativeOption.value;
      option.textContent = getOptionLabel(nativeOption);

      if (nativeOption.disabled) {
        option.classList.add('is-disabled');
        option.setAttribute('aria-disabled', 'true');
      }

      menu.append(option);
      return option;
    });

    root.append(trigger, menu);
    select.insertAdjacentElement('afterend', root);

    const instance = {
      activeIndex: 0,
      menu,
      options,
      root,
      select,
      trigger,
      value,
    };

    root.rytkosetShopSelect = instance;

    updateSelectedState(instance);

    trigger.addEventListener('click', () => {
      if (root.classList.contains('is-open')) {
        closeSelect(instance);
      } else {
        openSelect(instance);
      }
    });

    trigger.addEventListener('keydown', (event) => {
      if (focusableKeys.includes(event.key)) {
        event.preventDefault();

        if (!root.classList.contains('is-open')) {
          openSelect(instance);
        }

        if (event.key === 'ArrowDown') {
          moveActive(instance, 1);
        } else if (event.key === 'ArrowUp') {
          moveActive(instance, -1);
        } else if (event.key === 'Home') {
          setActiveIndex(instance, 0);
        } else if (event.key === 'End') {
          setActiveIndex(instance, options.length - 1);
        }
      }

      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();

        if (!root.classList.contains('is-open')) {
          openSelect(instance);
          return;
        }

        chooseOption(instance, options[instance.activeIndex]);
      }

      if (event.key === 'Escape') {
        closeSelect(instance, true);
      }

      if (event.key === 'Tab') {
        closeSelect(instance);
      }
    });

    options.forEach((option, index) => {
      option.addEventListener('click', () => chooseOption(instance, option));
      option.addEventListener('mouseenter', () => setActiveIndex(instance, index));
    });

    select.addEventListener('change', () => updateSelectedState(instance));
  };

  const initShopSelects = (root = document) => {
    if (root.matches && root.matches(SELECTOR)) {
      buildCustomSelect(root);
    }

    root.querySelectorAll(SELECTOR).forEach(buildCustomSelect);
  };

  const getPrecision = (value) => {
    const stringValue = String(value);
    const dotIndex = stringValue.indexOf('.');

    return dotIndex >= 0 ? stringValue.length - dotIndex - 1 : 0;
  };

  const getQuantityStep = (input) => {
    const step = input.getAttribute('step');

    if (!step || step === 'any') {
      return 1;
    }

    const parsed = Number.parseFloat(step);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
  };

  const getQuantityBounds = (input) => {
    const min = Number.parseFloat(input.getAttribute('min'));
    const max = Number.parseFloat(input.getAttribute('max'));

    return {
      max: Number.isFinite(max) ? max : null,
      min: Number.isFinite(min) ? min : 0,
    };
  };

  const isFixedSingleQuantity = (input) => {
    const bounds = getQuantityBounds(input);
    const value = Number.parseFloat(input.value || input.getAttribute('value') || '1');

    return (
      input.type === 'hidden' ||
      input.disabled ||
      (
        bounds.max !== null &&
        bounds.max <= 1 &&
        Number.isFinite(value) &&
        value <= 1
      )
    );
  };

  const setQuantityValue = (input, value) => {
    const step = getQuantityStep(input);
    const precision = getPrecision(step);
    const bounds = getQuantityBounds(input);
    let nextValue = value;

    if (bounds.max !== null) {
      nextValue = Math.min(nextValue, bounds.max);
    }

    nextValue = Math.max(nextValue, bounds.min);
    input.value = precision > 0 ? nextValue.toFixed(precision) : String(Math.round(nextValue));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const updateQuantityButtons = (control, input) => {
    const bounds = getQuantityBounds(input);
    const value = Number.parseFloat(input.value || '0');
    const decrease = control.querySelector('[data-quantity-action="decrease"]');
    const increase = control.querySelector('[data-quantity-action="increase"]');

    if (decrease) {
      decrease.disabled = Number.isFinite(value) && value <= bounds.min;
    }

    if (increase && bounds.max !== null) {
      increase.disabled = Number.isFinite(value) && value >= bounds.max;
    }
  };

  const buildQuantityControl = (input) => {
    if (input.dataset.rytkosetQuantity === 'ready') {
      return;
    }

    const quantity = input.closest('.quantity');

    if (!quantity) {
      return;
    }

    if (isFixedSingleQuantity(input)) {
      input.dataset.rytkosetQuantity = 'ready';
      quantity.classList.add('rytkoset-quantity-is-fixed');
      return;
    }

    input.dataset.rytkosetQuantity = 'ready';
    input.classList.add('rytkoset-quantity__input');

    const control = document.createElement('div');
    control.className = 'rytkoset-quantity';

    const decrease = document.createElement('button');
    decrease.type = 'button';
    decrease.className = 'rytkoset-quantity__button';
    decrease.dataset.quantityAction = 'decrease';
    decrease.setAttribute('aria-label', 'V\u00e4henn\u00e4 m\u00e4\u00e4r\u00e4\u00e4');
    decrease.textContent = '-';

    const increase = document.createElement('button');
    increase.type = 'button';
    increase.className = 'rytkoset-quantity__button';
    increase.dataset.quantityAction = 'increase';
    increase.setAttribute('aria-label', 'Lis\u00e4\u00e4 m\u00e4\u00e4r\u00e4\u00e4');
    increase.textContent = '+';

    quantity.insertBefore(control, input);
    control.append(decrease, input, increase);

    const changeQuantity = (direction) => {
      const currentValue = Number.parseFloat(input.value || '0');
      const baseValue = Number.isFinite(currentValue) ? currentValue : getQuantityBounds(input).min;
      const nextValue = baseValue + direction * getQuantityStep(input);

      setQuantityValue(input, nextValue);
      updateQuantityButtons(control, input);
    };

    decrease.addEventListener('click', () => changeQuantity(-1));
    increase.addEventListener('click', () => changeQuantity(1));
    input.addEventListener('change', () => updateQuantityButtons(control, input));
    input.addEventListener('input', () => updateQuantityButtons(control, input));
    updateQuantityButtons(control, input);
  };

  const initQuantityControls = (root = document) => {
    if (root.matches && root.matches(QUANTITY_SELECTOR)) {
      buildQuantityControl(root);
    }

    root.querySelectorAll(QUANTITY_SELECTOR).forEach(buildQuantityControl);
  };

  const getProductIdFromCartButton = (button) => {
    const dataProductId = button.getAttribute('data-product_id');

    if (dataProductId) {
      return dataProductId;
    }

    if (button.name === 'add-to-cart' && button.value) {
      return button.value;
    }

    const form = button.closest('form.cart');

    if (!form) {
      return '';
    }

    const addToCartField = form.querySelector('[name="add-to-cart"]');

    if (addToCartField && addToCartField.value) {
      return addToCartField.value;
    }

    const productIdField = form.querySelector('input[name="product_id"]');
    return productIdField ? productIdField.value : '';
  };

  const disableSoldIndividuallyCartButton = (button) => {
    if (button.dataset.rytkosetSoldIndividuallyDisabled === 'ready') {
      return;
    }

    const label = shopConfig.soldIndividuallyInCartText || 'Jo ostoskorissa';
    button.dataset.rytkosetSoldIndividuallyDisabled = 'ready';
    button.dataset.rytkosetOriginalText = button.textContent.trim();
    button.classList.add('disabled', 'rytkoset-sold-individually-in-cart');
    button.setAttribute('aria-disabled', 'true');
    button.setAttribute('title', label);
    button.textContent = label;

    if (button.tagName === 'BUTTON' || button.tagName === 'INPUT') {
      button.disabled = true;
    } else {
      button.dataset.rytkosetOriginalHref = button.getAttribute('href') || '';
      button.removeAttribute('href');
      button.setAttribute('tabindex', '-1');
    }
  };

  const initSoldIndividuallyCartButtons = (root = document) => {
    if (!soldIndividuallyCartProductIds.size) {
      return;
    }

    const maybeDisable = (button) => {
      const productId = getProductIdFromCartButton(button);

      if (productId && soldIndividuallyCartProductIds.has(String(productId))) {
        disableSoldIndividuallyCartButton(button);
      }
    };

    if (root.matches && root.matches(ADD_TO_CART_SELECTOR)) {
      maybeDisable(root);
    }

    root.querySelectorAll(ADD_TO_CART_SELECTOR).forEach(maybeDisable);
  };

  document.addEventListener('click', (event) => {
    const disabledCartButton = event.target.closest('.rytkoset-sold-individually-in-cart');

    if (disabledCartButton) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    if (!event.target.closest('.rytkoset-shop-select')) {
      closeAll();
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    initShopSelects();
    initQuantityControls();
    initSoldIndividuallyCartButtons();

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            initShopSelects(node);
            initQuantityControls(node);
            initSoldIndividuallyCartButtons(node);
          }
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  });
})();
