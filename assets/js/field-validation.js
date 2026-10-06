/**
 * Field Validation Widget – Name & Matric Number
 * Property Reporting and Recovery System (PRS)
 * Security Unit, The Polytechnic Ibadan
 *
 * Usage:
 *   class="prs-name-input"   → letters, spaces, hyphens, apostrophes only
 *   class="prs-matric-input" → exactly 13 digits, numbers only
 *
 * Each widget adds live validation feedback under the input.
 */
(function () {
  'use strict';

  /* ────────── FULL-NAME WIDGET ────────── */
  function initNameInput(input) {
    if (input.dataset.prsNameInit) return;
    input.dataset.prsNameInit = '1';

    const errEl = document.createElement('div');
    errEl.className = 'ng-phone-error'; // reuse phone error styling
    input.parentNode.insertBefore(errEl, input.nextSibling);

    function show(msg) {
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.add('visible');
        input.style.borderColor = 'var(--danger)';
      } else {
        errEl.textContent = '';
        errEl.classList.remove('visible');
        input.style.borderColor = '';
      }
    }

    // Prevent non-letter characters while typing
    input.addEventListener('input', function () {
      // Strip anything that is NOT a letter, space, hyphen, or apostrophe
      this.value = this.value.replace(/[^A-Za-z\u00C0-\u024F\s\-']/g, '');

      if (this.value.length > 0 && this.value.length < 3) {
        show('Name must be at least 3 characters.');
      } else {
        show(null);
      }
    });

    input.addEventListener('blur', function () {
      const v = this.value.trim();
      if (v.length === 0) {
        show('Full name is required.');
        this.setCustomValidity('Full name is required.');
      } else if (v.length < 3) {
        show('Name must be at least 3 characters.');
        this.setCustomValidity('Name must be at least 3 characters.');
      } else {
        show(null);
        this.setCustomValidity('');
      }
    });

    input.addEventListener('focus', function () {
      this.setCustomValidity('');
    });
  }

  /* ────────── MATRIC NUMBER WIDGET ────────── */
  function initMatricInput(input) {
    if (input.dataset.prsMatricInit) return;
    input.dataset.prsMatricInit = '1';

    // Force numeric keyboard on mobile
    input.inputMode = 'numeric';
    input.maxLength = 13;
    input.placeholder = 'Enter 13-digit matric number';

    // Add a small counter badge
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative; width:100%;';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    input.style.paddingRight = '50px';

    const counter = document.createElement('span');
    counter.style.cssText = 'position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:11px; color:var(--muted); pointer-events:none; font-variant-numeric:tabular-nums; font-weight:600;';
    counter.textContent = '0/13';
    wrap.appendChild(counter);

    const errEl = document.createElement('div');
    errEl.className = 'ng-phone-error';
    wrap.parentNode.insertBefore(errEl, wrap.nextSibling);

    function show(msg) {
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.add('visible');
        input.style.borderColor = 'var(--danger)';
      } else {
        errEl.textContent = '';
        errEl.classList.remove('visible');
        input.style.borderColor = '';
      }
    }

    function updateCounter() {
      const len = input.value.length;
      counter.textContent = len + '/13';
      counter.style.color = len === 13 ? 'var(--success, #28a745)' : 'var(--muted)';
    }

    input.addEventListener('input', function () {
      // Strip anything that's not a digit
      this.value = this.value.replace(/\D/g, '').slice(0, 13);
      updateCounter();

      if (this.value.length === 13) {
        show(null);
        this.setCustomValidity('');
      } else if (this.value.length > 0) {
        show(null); // don't show error while typing, just update counter
      }
    });

    input.addEventListener('paste', function (e) {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const cleaned = pasted.replace(/\D/g, '').slice(0, 13);
      this.value = cleaned;
      updateCounter();
      if (cleaned.length !== 13) {
        show('Matric number must be exactly 13 digits.');
        this.setCustomValidity('Matric number must be exactly 13 digits.');
      } else {
        show(null);
        this.setCustomValidity('');
      }
    });

    input.addEventListener('blur', function () {
      const len = this.value.length;
      if (len === 0) {
        show('Matric number is required.');
        this.setCustomValidity('Matric number is required.');
      } else if (len !== 13) {
        show('Matric number must be exactly 13 digits. You entered ' + len + '.');
        this.setCustomValidity('Matric number must be exactly 13 digits.');
      } else {
        show(null);
        this.setCustomValidity('');
      }
    });

    input.addEventListener('focus', function () {
      this.setCustomValidity('');
    });

    // Prevent non-digit keystrokes (allow navigation keys)
    input.addEventListener('keypress', function (e) {
      if (!/\d/.test(e.key) && e.key.length === 1) {
        e.preventDefault();
      }
    });

    updateCounter();
  }

  /* ────────── FORM SUBMIT GUARD ────────── */
  function initFormGuards() {
    document.querySelectorAll('form').forEach(function (form) {
      if (form.dataset.prsFieldGuard) return;
      form.dataset.prsFieldGuard = '1';

      form.addEventListener('submit', function (e) {
        let blocked = false;

        // Validate name fields
        form.querySelectorAll('.prs-name-input').forEach(function (inp) {
          const v = inp.value.trim();
          if (v.length === 0 || v.length < 3 || /[^A-Za-z\u00C0-\u024F\s\-']/.test(v)) {
            blocked = true;
            const msg = v.length === 0 ? 'Full name is required.' : 'Enter a valid name (letters only).';
            inp.setCustomValidity(msg);
            inp.reportValidity();
            const errDiv = inp.nextElementSibling;
            if (errDiv && errDiv.classList.contains('ng-phone-error')) {
              errDiv.textContent = msg;
              errDiv.classList.add('visible');
              inp.style.borderColor = 'var(--danger)';
            }
          }
        });

        // Validate matric fields
        form.querySelectorAll('.prs-matric-input').forEach(function (inp) {
          if (!/^\d{13}$/.test(inp.value)) {
            blocked = true;
            const msg = inp.value.length === 0 ? 'Matric number is required.' : 'Matric number must be exactly 13 digits.';
            inp.setCustomValidity(msg);
            inp.reportValidity();
            const wrap = inp.parentNode;
            const errDiv = wrap.nextElementSibling;
            if (errDiv && errDiv.classList.contains('ng-phone-error')) {
              errDiv.textContent = msg;
              errDiv.classList.add('visible');
              inp.style.borderColor = 'var(--danger)';
            }
          }
        });

        // Validate date fields (strictly no future dates allowed)
        form.querySelectorAll('input[type="date"]').forEach(function (inp) {
          const today = getTodayString();
          if (inp.value && inp.value > today) {
            blocked = true;
            inp.value = today;
            const msg = 'Date cannot be in the future. Must be today or an earlier date.';
            inp.setCustomValidity(msg);
            inp.reportValidity();
            const errDiv = inp.nextElementSibling;
            if (errDiv && errDiv.classList.contains('ng-phone-error')) {
              errDiv.textContent = msg;
              errDiv.classList.add('visible');
              inp.style.borderColor = 'var(--danger)';
            }
          }
        });

        if (blocked) e.preventDefault();
      }, { capture: true });
    });
  }


  /* ────────── FACULTY & DEPARTMENT CASCADE WIDGET ────────── */
  const POLY_FACULTIES = {
    'Faculty of Engineering': [
      'Civil Engineering',
      'Computer Engineering',
      'Electrical / Electronic Engineering',
      'Mechanical Engineering',
      'Mechatronics Engineering'
    ],
    'Faculty of Science': [
      'Computer Science',
      'Science Laboratory Technology (SLT)',
      'Statistics',
      'Mathematics',
      'Physics with Electronics',
      'Chemistry / Biochemistry',
      'Biology / Microbiology'
    ],
    'Faculty of Business and Communication Studies (FBCS)': [
      'Business Administration and Management',
      'Mass Communication',
      'Marketing',
      'Office Technology and Management (OTM)',
      'Public Administration',
      'Music Technology',
      'Library and Information Science'
    ],
    'Faculty of Financial Management Studies (FFMS)': [
      'Accountancy',
      'Banking and Finance',
      'Insurance'
    ],
    'Faculty of Environmental Studies (FES)': [
      'Architecture',
      'Building Technology',
      'Estate Management and Valuation',
      'Quantity Surveying',
      'Urban and Regional Planning',
      'Surveying and Geoinformatics',
      'Art and Design'
    ]
  };

  function initFacultyCascade(facultySelect) {
    if (facultySelect.dataset.prsCascadeInit) return;
    facultySelect.dataset.prsCascadeInit = '1';

    const form = facultySelect.closest('form');
    if (!form) return;
    const deptSelect = form.querySelector('.prs-department-select');
    if (!deptSelect) return;

    function populateDepartments(selectedFaculty, preselectedDept) {
      deptSelect.innerHTML = '';
      if (!selectedFaculty || !POLY_FACULTIES[selectedFaculty]) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '-- Select Faculty First --';
        deptSelect.appendChild(opt);
        deptSelect.disabled = true;
        deptSelect.style.background = '#f8f9fb';
        return;
      }

      deptSelect.disabled = false;
      deptSelect.style.background = '#fff';

      const defaultOpt = document.createElement('option');
      defaultOpt.value = '';
      const cleanFacName = selectedFaculty.split('(')[0].trim();
      defaultOpt.textContent = '-- Select Department in ' + cleanFacName + ' --';
      deptSelect.appendChild(defaultOpt);

      const depts = POLY_FACULTIES[selectedFaculty];
      depts.forEach(function (d) {
        const opt = document.createElement('option');
        opt.value = d;
        opt.textContent = d;
        if (preselectedDept && preselectedDept === d) {
          opt.selected = true;
        }
        deptSelect.appendChild(opt);
      });
    }

    facultySelect.addEventListener('change', function () {
      populateDepartments(this.value, null);
    });

    // Initial load: determine preselection
    const preselectedDept = deptSelect.dataset.selected || deptSelect.value;
    if (preselectedDept && !facultySelect.value) {
      for (const [fac, depts] of Object.entries(POLY_FACULTIES)) {
        if (depts.includes(preselectedDept)) {
          facultySelect.value = fac;
          break;
        }
      }
    }

    populateDepartments(facultySelect.value, preselectedDept);
  }

  /* ────────── CATEGORY & FOUND ITEM CASCADE WIDGET ────────── */
  function initFoundItemCascade(catSelect) {
    if (catSelect.dataset.prsFoundCascadeInit) return;
    catSelect.dataset.prsFoundCascadeInit = '1';

    const form = catSelect.closest('form');
    if (!form) return;
    const itemSelect = form.querySelector('.prs-found-item-select');
    if (!itemSelect) return;

    let pool = {};
    if (form.dataset.foundPool) {
      try { pool = JSON.parse(form.dataset.foundPool); } catch (e) {}
    } else if (window.PRS_FOUND_POOL) {
      pool = window.PRS_FOUND_POOL;
    }

    function populateItems(selectedCat, preselectedId) {
      itemSelect.innerHTML = '';
      if (!selectedCat) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '-- Select Category First --';
        itemSelect.appendChild(opt);
        itemSelect.disabled = true;
        itemSelect.style.background = '#f8f9fb';
        return;
      }

      itemSelect.disabled = false;
      itemSelect.style.background = '#fff';

      const items = pool[selectedCat] || [];
      const defaultOpt = document.createElement('option');
      defaultOpt.value = '';

      if (items.length === 0) {
        defaultOpt.textContent = 'No found items in ' + selectedCat;
        itemSelect.appendChild(defaultOpt);
        itemSelect.disabled = true;
        itemSelect.style.background = '#f8f9fb';
        return;
      }

      defaultOpt.textContent = '-- Choose Found ' + selectedCat + ' --';
      itemSelect.appendChild(defaultOpt);

      items.forEach(function (it) {
        const opt = document.createElement('option');
        opt.value = it.id;
        opt.textContent = it.item_name + ' (#' + it.id + (it.location_found ? ' · ' + it.location_found : '') + ')';
        if (preselectedId && String(preselectedId) === String(it.id)) {
          opt.selected = true;
        }
        itemSelect.appendChild(opt);
      });
    }

    catSelect.addEventListener('change', function () {
      populateItems(this.value, null);
    });

    // If on a form with a lost item picker, auto-update category when lost item changes
    const lostSelect = form.querySelector('select[name="lost_item_id"]');
    if (lostSelect) {
      lostSelect.addEventListener('change', function () {
        const selectedOpt = this.options[this.selectedIndex];
        if (selectedOpt && selectedOpt.dataset.category) {
          const cat = selectedOpt.dataset.category;
          catSelect.value = cat;
          populateItems(cat, null);
        }
      });
    }

    // Initial populate
    const initialCat = catSelect.value || catSelect.dataset.selected;
    if (initialCat) {
      catSelect.value = initialCat;
      populateItems(initialCat, itemSelect.dataset.selected || null);
    } else {
      populateItems('', null);
    }
  }

  /* ────────── PAST OR TODAY DATE RESTRICTION WIDGET ────────── */
  function getTodayString() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function initDateInput(input) {
    if (input.dataset.prsDateInit) return;
    input.dataset.prsDateInit = '1';

    const today = getTodayString();
    input.max = today;

    const errEl = document.createElement('div');
    errEl.className = 'ng-phone-error';
    input.parentNode.insertBefore(errEl, input.nextSibling);

    function show(msg) {
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.add('visible');
        input.style.borderColor = 'var(--danger)';
      } else {
        errEl.textContent = '';
        errEl.classList.remove('visible');
        input.style.borderColor = '';
      }
    }

    function enforcePastOrToday() {
      const currentToday = getTodayString();
      input.max = currentToday;
      const v = input.value;

      if (!v) {
        show(null);
        input.setCustomValidity('');
        return;
      }

      if (v > currentToday) {
        // Strictly prevent future dates: automatically clamp back to today!
        input.value = currentToday;
        show('Future date not allowed. Automatically adjusted to today (' + currentToday + ').');
        input.setCustomValidity('Future dates are not allowed. Must be today or earlier.');
      } else {
        show(null);
        input.setCustomValidity('');
      }
    }

    input.addEventListener('input', enforcePastOrToday);
    input.addEventListener('change', enforcePastOrToday);
    input.addEventListener('blur', enforcePastOrToday);
  }

  function initAll() {
    document.querySelectorAll('.prs-name-input').forEach(initNameInput);
    document.querySelectorAll('.prs-matric-input').forEach(initMatricInput);
    document.querySelectorAll('.prs-faculty-select').forEach(initFacultyCascade);
    document.querySelectorAll('.prs-found-cat-select').forEach(initFoundItemCascade);
    document.querySelectorAll('input[type="date"]').forEach(initDateInput);
    initFormGuards();
  }


  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

})();

