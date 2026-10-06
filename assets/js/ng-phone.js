/**
 * Nigerian Phone Number Input Widget
 * Property Reporting and Recovery System (PRS)
 * Security Unit, The Polytechnic Ibadan
 *
 * Usage: Add class="ng-phone-input" to any <input type="tel">
 * The widget wraps it with a fixed +234 flag prefix.
 * Saves value in E.164 format: +2348031234567
 * Hidden sibling input stores the full E.164 value for form submission.
 */
(function () {
  'use strict';

  function normaliseToTenDigits(raw) {
    // Strip all non-digits
    let digits = raw.replace(/\D/g, '');
    // Remove leading country code: 234 or 0
    if (digits.startsWith('234') && digits.length > 10) {
      digits = digits.slice(3);
    } else if (digits.startsWith('0') && digits.length > 10) {
      digits = digits.slice(1);
    }
    return digits.slice(0, 10);
  }

  function validateNgPhone(digits) {
    if (digits.length !== 10) {
      return 'Phone number must be exactly 10 digits after +234.';
    }
    if (!/^[789]/.test(digits)) {
      return 'Enter a valid Nigerian number e.g. 8031234567';
    }
    return null; // valid
  }

  function initWidget(input) {
    // Already initialised
    if (input.dataset.ngPhoneInit) return;
    input.dataset.ngPhoneInit = '1';

    const originalName = input.getAttribute('name');
    const originalValue = input.getAttribute('value') || '';

    // Create wrapper
    const wrap = document.createElement('div');
    wrap.className = 'ng-phone-wrap';

    // Prefix badge
    const prefix = document.createElement('div');
    prefix.className = 'ng-phone-prefix';
    prefix.innerHTML = '<span class="ng-flag">🇳🇬</span><span>+234</span>';

    // The visible 10-digit input
    const visible = document.createElement('input');
    visible.type = 'tel';
    visible.inputMode = 'numeric';
    visible.maxLength = 10;
    visible.placeholder = '8031234567';
    visible.autocomplete = 'tel-national';
    visible.style.letterSpacing = '0.04em';

    // Error message element
    const errEl = document.createElement('div');
    errEl.className = 'ng-phone-error';

    // Hidden input that actually submits the E.164 value
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = originalName;

    // Prefill from existing value (e.g. on POST re-render)
    if (originalValue) {
      const prefilled = normaliseToTenDigits(originalValue);
      visible.value = prefilled;
      hidden.value = prefilled ? '+234' + prefilled : '';
    }

    // Remove name from original so it doesn't double-submit
    input.removeAttribute('name');
    input.style.display = 'none';

    // Replace original in DOM
    const parent = input.parentNode;
    wrap.appendChild(prefix);
    wrap.appendChild(visible);
    parent.insertBefore(wrap, input);
    parent.insertBefore(errEl, wrap.nextSibling);
    parent.insertBefore(hidden, errEl.nextSibling);
    parent.removeChild(input);

    // --- Events ---

    function syncHidden() {
      const digits = normaliseToTenDigits(visible.value);
      visible.value = digits;
      hidden.value = digits ? '+234' + digits : '';
    }

    function showError(msg) {
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.add('visible');
        wrap.style.borderColor = 'var(--danger)';
      } else {
        errEl.textContent = '';
        errEl.classList.remove('visible');
        wrap.style.borderColor = '';
      }
    }

    visible.addEventListener('input', function () {
      // Strip non-digits immediately
      let v = this.value.replace(/\D/g, '');
      // Auto-remove leading 0 if user typed or pasted 0803...
      if (v.startsWith('0')) v = v.slice(1);
      // Cap at 10
      this.value = v.slice(0, 10);
      syncHidden();
      // Live validation after 10 digits
      if (this.value.length === 10) {
        showError(validateNgPhone(this.value));
      } else {
        showError(null);
      }
    });

    visible.addEventListener('paste', function (e) {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const cleaned = normaliseToTenDigits(pasted);
      this.value = cleaned;
      syncHidden();
      showError(validateNgPhone(cleaned));
    });

    visible.addEventListener('blur', function () {
      syncHidden();
      const err = validateNgPhone(this.value);
      showError(err);
      // Prevent form submission via native required if invalid
      this.setCustomValidity(err || '');
    });

    visible.addEventListener('focus', function () {
      this.setCustomValidity('');
      if (this.value.length < 10) showError(null);
    });

    // Make parent form validate before submit
    const form = visible.closest('form');
    if (form && !form.dataset.ngPhoneFormInit) {
      form.dataset.ngPhoneFormInit = '1';
      form.addEventListener('submit', function (e) {
        const inputs = form.querySelectorAll('.ng-phone-wrap input[type="tel"]');
        let blocked = false;
        inputs.forEach(function (inp) {
          const err = validateNgPhone(inp.value);
          if (err) {
            blocked = true;
            inp.setCustomValidity(err);
            inp.reportValidity();
            // show our custom error too
            const errDiv = inp.closest('.ng-phone-wrap').nextElementSibling;
            if (errDiv && errDiv.classList.contains('ng-phone-error')) {
              errDiv.textContent = err;
              errDiv.classList.add('visible');
              inp.closest('.ng-phone-wrap').style.borderColor = 'var(--danger)';
            }
          }
        });
        if (blocked) e.preventDefault();
      }, { capture: true });
    }
  }

  function initAll() {
    document.querySelectorAll('input.ng-phone-input').forEach(initWidget);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
