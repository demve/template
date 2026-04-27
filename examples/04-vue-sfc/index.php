<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>04 – Vue SFC Pattern</title>
  <style>
    body  { font-family: sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; color: #222; }
    h1    { border-bottom: 2px solid #4f46e5; padding-bottom: .4rem; }
    h2    { margin-top: 2rem; color: #4f46e5; }
    pre   { background: #f5f5f5; border-left: 4px solid #4f46e5; padding: 1rem; overflow-x: auto; border-radius: 4px; font-size: .85rem; }
    code  { font-family: monospace; font-size: .9rem; background: #f0f0f0; padding: .1rem .3rem; border-radius: 3px; }
    iframe { width: 100%; height: 480px; border: 1px solid #ddd; border-radius: 6px; margin-top: 1rem; }
    .note { background: #fffbe6; border-left: 4px solid #f59e0b; padding: .75rem 1rem; border-radius: 4px; margin: 1rem 0; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0; }
    .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
    .card h3 { margin: 0 0 .5rem; font-size: .95rem; color: #4f46e5; }
    .card p  { margin: 0; font-size: .875rem; color: #475569; }
    table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .9rem; }
    th, td { text-align: left; padding: .5rem .75rem; border: 1px solid #e2e8f0; }
    th { background: #f8fafc; }
  </style>
</head>
<body>

<h1>04 – Vue SFC Pattern</h1>

<p>
  This example mirrors how Vue Single-File Components work: each view component is a
  self-contained <code>.html</code> file with three named sections —
  <code>style</code>, <code>template</code>, and <code>script</code>.
  The main <code>App</code> component loads both views, and the layout collects every
  section into the right place in the final HTML.
</p>

<h2>Component structure</h2>
<div class="grid">
  <div class="card">
    <h3>views/login.html</h3>
    <p>Style → login form CSS<br>Template → hidden <code>&lt;script type="text/template"&gt;</code><br>Script → <code>loginSubmit()</code> handler</p>
  </div>
  <div class="card">
    <h3>views/dashboard.html</h3>
    <p>Style → dashboard grid CSS<br>Template → hidden <code>&lt;script type="text/template"&gt;</code><br>Script → dashboard-specific JS</p>
  </div>
</div>
<p>
  Neither view has an <code>output</code> section — they don't render themselves.
  They only <em>register</em> sections into the global collector.
</p>

<h2>The loading chain</h2>
<pre><code><!-- app.html -->
&lt;!--extends::Layout--&gt;
&lt;!--load::Views.Login--&gt;
&lt;!--load::Views.Dashboard--&gt;</code></pre>
<p>
  <code>load()</code> never outputs anything. It just executes the component file so its
  <code>section()</code> calls run and the content is stored. After both views are loaded,
  all their sections are sitting in the collector waiting to be rendered.
</p>

<h2>What the collector holds after loading</h2>
<table>
  <tr><th>Section key</th><th>Contents</th></tr>
  <tr><td><code>style</code></td><td>Login CSS + Dashboard CSS (in load order)</td></tr>
  <tr><td><code>template</code></td><td><code>&lt;script type="text/template" id="view-login"&gt;</code> + dashboard equivalent</td></tr>
  <tr><td><code>script</code></td><td>App router script + Login handler + Dashboard JS</td></tr>
</table>

<h2>The layout places everything</h2>
<pre><code>&lt;!-- layout.html --&gt;
&lt;head&gt;
  &lt;style&gt;/* base reset */&lt;/style&gt;
  @renderSectionBlock('style')       &lt;!-- all view CSS, in one block --&gt;
&lt;/head&gt;
&lt;body&gt;
  &lt;nav&gt;...&lt;/nav&gt;
  &lt;div id="app"&gt;&lt;/div&gt;            &lt;!-- router swaps content here --&gt;

  @renderSectionBlock('template')    &lt;!-- all hidden view templates --&gt;
  @renderSectionBlock('script')      &lt;!-- all JS, after DOM is ready --&gt;
&lt;/body&gt;</code></pre>

<h2>The JS router (in app.html)</h2>
<pre><code>function navigate() {
  var name = location.hash.replace('#', '') || 'login';
  var tpl  = document.getElementById('view-' + name);
  document.getElementById('app').innerHTML = tpl ? tpl.innerHTML : '';
}
window.addEventListener('hashchange', navigate);
navigate();</code></pre>
<p>
  Each view's <code>template</code> section wraps its HTML in
  <code>&lt;script type="text/template" id="view-login"&gt;</code>.
  The browser does not render <code>type="text/template"</code> scripts.
  The router reads <code>.innerHTML</code> and injects it into <code>#app</code>.
</p>

<div class="note">
  <strong>Naming convention:</strong> <code>Views.Login</code> and <code>widgetsLogin</code>
  both resolve to the same path (<code>components/views/login.html</code>).
  PascalCase segments are split on capital letters; dots and camelCase humps both insert a
  path separator. Use whichever reads more naturally in context.
</div>

<h2>Result — use the nav links to switch views</h2>

<iframe src="run.php" title="Vue SFC pattern output"></iframe>

</body>
</html>
