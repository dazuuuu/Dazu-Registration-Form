/**
 * Dazu Training Institute — terminal auth boot sequence & ambient code panel
 */
(function () {
  'use strict';

  var LOGIN_BOOT = [
    { text: '[boot] Dazu Training Institute Secure Shell v2.1', type: 'sys' },
    { text: '[boot] initializing auth.dazu.hub subsystem...', type: 'sys' },
    { text: '[ ok ] loading encryption modules', type: 'ok' },
    { text: '[ ok ] verifying TLS handshake', type: 'ok' },
    { text: '[ ok ] session listener ready on :443', type: 'ok' },
    { text: '[info] awaiting credentials — use export EMAIL / export PASS', type: 'info' },
    { text: '[info] run ./signin.sh to authenticate', type: 'info' },
    { text: '', type: 'sys' }
  ];

  var REGISTER_BOOT = [
    { text: '[boot] Dazu Training Institute — user provisioning v2.1', type: 'sys' },
    { text: '[boot] initializing register.dazu.hub...', type: 'sys' },
    { text: '[ ok ] schema validation passed', type: 'ok' },
    { text: '[ ok ] identity service online', type: 'ok' },
    { text: '[info] set export USERNAME, EMAIL, PASS variables', type: 'info' },
    { text: '[info] run ./register.sh to create your account', type: 'info' },
    { text: '', type: 'sys' }
  ];

  var page = (document.body && document.body.getAttribute('data-auth-page')) || 'login';
  var BOOT_LINES = page === 'register' ? REGISTER_BOOT : LOGIN_BOOT;

  var CODE_SNIPPETS = [
    '<span class="cm">// Dazu Training Institute — student portal bootstrap</span>\n<span class="kw">const</span> <span class="fn">initPortal</span> = <span class="kw">async</span> () => {\n  <span class="kw">await</span> <span class="fn">loadModules</span>([<span class="str">\'lms\'</span>, <span class="str">\'payments\'</span>]);\n  <span class="kw">return</span> <span class="fn">authenticate</span>({ secure: <span class="kw">true</span> });\n};',
    '<span class="cm"># admin dev terminal — route map</span>\n<span class="kw">routes</span> = {\n  <span class="str">\'students\'</span>: <span class="str">\'/admin/students\'</span>,\n  <span class="str">\'courses\'</span>: <span class="str">\'/admin/courses\'</span>,\n}\n<span class="fn">open</span>(<span class="str">\'dashboard\'</span>)',
    '<span class="cm">/* course registration handler */</span>\n<span class="kw">if</span> (<span class="fn">validate</span>(payload)) {\n  db.<span class="fn">insert</span>(<span class="str">\'registrations\'</span>, payload);\n  <span class="fn">notify</span>(student.email);\n}',
    '<span class="cm">$ ssh dazu@hub.institute</span>\nWelcome to Dazu Training Institute\nLast login: today\n<span class="fn">dazu@hub</span>:~$ <span class="kw">cd</span> ~/learning'
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
