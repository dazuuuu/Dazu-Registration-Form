/**
 * Dazu Hub — terminal auth boot sequence & ambient code panel
 */
(function () {
  'use strict';

  var LOGIN_BOOT = [
    { text: '[boot] Dazu Hub Institute Secure Shell v2.1', type: 'sys' },
    { text: '[boot] initializing auth.dazu.hub subsystem...', type: 'sys' },
    { text: '[ ok ] loading encryption modules', type: 'ok' },
    { text: '[ ok ] verifying TLS handshake', type: 'ok' },
    { text: '[ ok ] session listener ready on :443', type: 'ok' },
    { text: '[info] awaiting credentials — use export EMAIL / export PASS', type: 'info' },
    { text: '[info] run ./signin.sh to authenticate', type: 'info' },
    { text: '[info] lost access? run ./recovery.sh', type: 'info' },
    { text: '', type: 'sys' }
  ];

  var REGISTER_BOOT = [
    { text: '[boot] Dazu Hub Institute — user provisioning v2.1', type: 'sys' },
    { text: '[boot] initializing register.dazu.hub...', type: 'sys' },
    { text: '[ ok ] schema validation passed', type: 'ok' },
    { text: '[ ok ] identity service online', type: 'ok' },
    { text: '[info] set export USERNAME, EMAIL, PASS variables', type: 'info' },
    { text: '[info] run ./register.sh to create your account', type: 'info' },
    { text: '', type: 'sys' }
  ];

  var RECOVER_BOOT = [
    { text: '[boot] Dazu Hub Institute — credential recovery v2.1', type: 'sys' },
    { text: '[boot] initializing auth.recover subsystem...', type: 'sys' },
    { text: '[ ok ] OTP generator online', type: 'ok' },
    { text: '[ ok ] mail relay connected', type: 'ok' },
    { text: '[info] export EMAIL to receive a 6-digit verification code', type: 'info' },
    { text: '[info] run ./send-otp.sh to start recovery', type: 'info' },
    { text: '', type: 'sys' }
  ];

  var RESET_BOOT = [
    { text: '[boot] Dazu Hub Institute — password reset v2.1', type: 'sys' },
    { text: '[boot] verifying recovery session...', type: 'sys' },
    { text: '[ ok ] OTP channel active', type: 'ok' },
    { text: '[info] export OTP, NEW_PASS, and PASS_CONFIRM', type: 'info' },
    { text: '[info] run ./reset-password.sh to complete reset', type: 'info' },
    { text: '', type: 'sys' }
  ];

  var ADMIN_BOOT = [
    { text: '[boot] Dazu Hub Institute — admin portal v2.1', type: 'sys' },
    { text: '[boot] elevating privileges...', type: 'sys' },
    { text: '[ ok ] RBAC policy loaded', type: 'ok' },
    { text: '[ ok ] audit logger armed', type: 'ok' },
    { text: '[warn] restricted to admin / superadmin roles', type: 'warn' },
    { text: '[info] run ./sudo-portal.sh with admin credentials', type: 'info' },
    { text: '', type: 'sys' }
  ];

  var page = (document.body && document.body.getAttribute('data-auth-page')) || 'login';
  var BOOT_LINES = LOGIN_BOOT;

  if (page === 'register') {
    BOOT_LINES = REGISTER_BOOT;
  } else if (page === 'recover') {
    var path = window.location.pathname || '';
    BOOT_LINES = path.indexOf('reset_password') !== -1 ? RESET_BOOT : RECOVER_BOOT;
  } else if (page === 'admin') {
    BOOT_LINES = ADMIN_BOOT;
  }

  var CODE_SNIPPETS = [
    '<span class="cm">// Dazu Hub — student portal bootstrap</span>\n<span class="kw">const</span> <span class="fn">initPortal</span> = <span class="kw">async</span> () => {\n  <span class="kw">await</span> <span class="fn">loadModules</span>([<span class="str">\'lms\'</span>, <span class="str">\'payments\'</span>]);\n  <span class="kw">return</span> <span class="fn">authenticate</span>({ secure: <span class="kw">true</span> });\n};',
    '<span class="cm"># admin dev terminal — route map</span>\n<span class="kw">routes</span> = {\n  <span class="str">\'students\'</span>: <span class="str">\'/admin/students\'</span>,\n  <span class="str">\'courses\'</span>: <span class="str">\'/admin/courses\'</span>,\n}\n<span class="fn">open</span>(<span class="str">\'dashboard\'</span>)',
    '<span class="cm">/* password recovery handler */</span>\n<span class="kw">const</span> otp = <span class="fn">generateOtp</span>({ digits: <span class="str">6</span> });\n<span class="fn">sendMail</span>(user.email, otp);\n<span class="fn">await</span> <span class="fn">verify</span>(otp);',
    '<span class="cm">$ ssh dazu@hub.institute</span>\nWelcome to Dazu Hub Institute\nLast login: today\n<span class="fn">dazu@hub</span>:~$ <span class="kw">cd</span> ~/learning'
  ];

  function typeBoot() {
    var bootEl = document.getElementById('authBootLog');
    if (!bootEl) return;

    var idx = 0;
    var charIdx = 0;
    var currentLine = null;

    function nextChar() {
      if (idx >= BOOT_LINES.length) {
        var cursor = document.createElement('span');
        cursor.className = 'auth-terminal-cursor';
        bootEl.appendChild(cursor);
        return;
      }

      var item = BOOT_LINES[idx];
      if (!currentLine) {
        currentLine = document.createElement('div');
        currentLine.className = 'line line-' + (item.type || 'sys');
        bootEl.appendChild(currentLine);
        charIdx = 0;
      }

      if (charIdx < item.text.length) {
        currentLine.textContent += item.text.charAt(charIdx);
        charIdx++;
        setTimeout(nextChar, 12 + Math.random() * 18);
      } else {
        idx++;
        currentLine = null;
        setTimeout(nextChar, item.text === '' ? 0 : 80 + Math.random() * 120);
      }
    }

    nextChar();
  }

  function rotateCodePanel() {
    var panel = document.getElementById('authCodePanel');
    if (!panel) return;

    var i = 0;
    function show() {
      panel.innerHTML = '<pre>' + CODE_SNIPPETS[i % CODE_SNIPPETS.length] + '</pre>';
      i++;
    }
    show();
    setInterval(show, 5200);
  }

  function wirePasswordMask() {
    var pass = document.getElementById('password');
    var echo = document.getElementById('passEcho');
    if (!pass || !echo) return;

    pass.addEventListener('input', function () {
      echo.textContent = pass.value ? '•'.repeat(Math.min(pass.value.length, 24)) : '';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      typeBoot();
      rotateCodePanel();
      wirePasswordMask();
    });
  } else {
    typeBoot();
    rotateCodePanel();
    wirePasswordMask();
  }
})();
