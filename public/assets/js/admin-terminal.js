/**
 * Dazu Hub Admin — Dev Terminal mode
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'dazu_admin_ui_mode';
  var outputEl, inputEl, shellEl;
  var history = [];
  var historyIdx = -1;
  var username = 'admin';

  var routes = window.ADMIN_TERMINAL_ROUTES || {};

  function init() {
    shellEl = document.getElementById('adminTerminalShell');
    outputEl = document.getElementById('adminTerminalOutput');
    inputEl = document.getElementById('adminTerminalInput');
    if (!shellEl || !outputEl || !inputEl) return;

    applyMode(localStorage.getItem(STORAGE_KEY) || 'simple');

    document.querySelectorAll('[data-admin-mode]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyMode(btn.getAttribute('data-admin-mode'));
      });
    });

    var simpleBtn = document.getElementById('adminTerminalToSimple');
    if (simpleBtn) {
      simpleBtn.addEventListener('click', function () { applyMode('simple'); });
    }

    inputEl.addEventListener('keydown', onKeyDown);

    if (getMode() === 'dev') {
      bootMessage();
    }
  }

  function getMode() {
    return document.body.classList.contains('admin-mode-dev') ? 'dev' : 'simple';
  }

  function applyMode(mode) {
    mode = mode === 'dev' ? 'dev' : 'simple';
    localStorage.setItem(STORAGE_KEY, mode);
    document.body.classList.remove('admin-mode-simple', 'admin-mode-dev');
    document.body.classList.add(mode === 'dev' ? 'admin-mode-dev' : 'admin-mode-simple');

    document.querySelectorAll('[data-admin-mode]').forEach(function (btn) {
      var active = btn.getAttribute('data-admin-mode') === mode;
      btn.classList.toggle('is-active', active && mode === 'simple');
      btn.classList.toggle('dev-active', active && mode === 'dev');
    });

    if (mode === 'dev') {
      bootMessage();
      setTimeout(function () { inputEl && inputEl.focus(); }, 100);
    }
  }

  function bootMessage() {
    if (!outputEl || outputEl.dataset.booted) return;
    outputEl.dataset.booted = '1';
    print([
      'Dazu Hub Institute Admin Terminal v1.0',
      'Type `help` or `menu` to get started.',
      'Switch back anytime: `mode simple` or click Simple UI.',
      ''
    ], 'system');
  }

  function print(lines, type) {
    lines = Array.isArray(lines) ? lines : [lines];
    lines.forEach(function (line) {
      var div = document.createElement('div');
      div.className = 'line line-' + (type || 'system');
      div.textContent = line;
      outputEl.appendChild(div);
    });
    outputEl.scrollTop = outputEl.scrollHeight;
  }

  function onKeyDown(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      var cmd = inputEl.value.trim();
      if (cmd) {
        history.push(cmd);
        historyIdx = history.length;
        print('dazu@hub:~$ ' + cmd, 'prompt');
        runCommand(cmd);
      }
      inputEl.value = '';
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (historyIdx > 0) {
        historyIdx--;
        inputEl.value = history[historyIdx];
      }
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (historyIdx < history.length - 1) {
        historyIdx++;
        inputEl.value = history[historyIdx];
      } else {
        historyIdx = history.length;
        inputEl.value = '';
      }
    }
  }

  function runCommand(raw) {
    var parts = raw.trim().split(/\s+/);
    var cmd = (parts[0] || '').toLowerCase();
    var arg = parts.slice(1).join(' ').toLowerCase();

    switch (cmd) {
      case 'help':
      case 'menu':
        showMenu();
        break;
      case 'clear':
      case 'cls':
        outputEl.innerHTML = '';
        outputEl.dataset.booted = '';
        bootMessage();
        break;
      case 'mode':
        if (arg === 'dev' || arg === 'terminal') applyMode('dev');
        else if (arg === 'simple' || arg === 'normal') applyMode('simple');
        else print('Usage: mode simple | mode dev', 'error');
        break;
      case 'open':
      case 'go':
      case 'cd':
        navigate(arg);
        break;
      case 'edit':
        if (!arg) {
          print('Usage: edit <section>  e.g. edit courses, edit students', 'warn');
          break;
        }
        navigate(arg);
        break;
      case 'save':
        print('On the open page, use the form Save / Submit button to persist changes.', 'info');
        print('Terminal mode handles navigation — forms still save in the browser.', 'dim');
        break;
      case 'logout':
      case 'exit':
        if (routes.logout) window.location.href = routes.logout;
        else print('Logout route not configured.', 'error');
        break;
      case 'whoami':
        print('Logged in as: ' + (window.ADMIN_TERMINAL_USER || username), 'info');
        break;
      default:
        if (routes[cmd]) {
          window.location.href = routes[cmd];
        } else {
          print('Unknown command: ' + cmd + '. Type `menu` for options.', 'error');
        }
    }
  }

  function showMenu() {
    print([
      '── NAVIGATION (type: open <name>) ──',
      '  dashboard    registrations   students    courses',
      '  tutors       payments        orders      enquiries',
      '  projects     services        blogs       gallery',
      '  testimonials settings        profile',
      '',
      '── ACTIONS ──',
      '  edit <name>   → open a section (same as open)',
      '  save          → reminder to use form buttons on page',
      '  clear         → clear terminal output',
      '  mode simple   → switch to normal admin UI',
      '  mode dev      → stay in terminal mode',
      '  logout        → sign out',
      '',
      '── EXAMPLES ──',
      '  open students',
      '  open courses',
      '  edit registrations',
      ''
    ], 'info');
  }

  function navigate(target) {
    if (!target) {
      print('Usage: open <section>  — try `menu` for list', 'warn');
      return;
    }
    target = target.replace(/\//g, ' ').trim();
    var aliases = {
      'dash': 'dashboard', 'home': 'dashboard', 'regs': 'registrations',
      'pending': 'registrations', 'approvals': 'registrations',
      'course': 'courses', 'tutor': 'tutors', 'pay': 'payments',
      'blog': 'blogs', 'shop': 'store', 'store': 'store-products',
      'products': 'store-products', 'categories': 'store-categories',
      'config': 'settings', 'me': 'profile'
    };
    var key = aliases[target] || target.replace(/\s+/g, '-');
    if (routes[key]) {
      print('Opening ' + key + '...', 'system');
      setTimeout(function () { window.location.href = routes[key]; }, 350);
    } else {
      print('No route for "' + target + '". Type `menu` to see available sections.', 'error');
    }
  }

  window.DazuAdminTerminal = { applyMode: applyMode, getMode: getMode };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
