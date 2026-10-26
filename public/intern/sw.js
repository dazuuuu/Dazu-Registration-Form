/* Installable intern app — scope /intern/. Offline page, saved resources and push alerts come from the shared worker.
 * Clock-in while offline is queued on the page (assets/js/intern-app.js) and sent when the connection returns. */
importScripts('../assets/js/portal-sw.js');
