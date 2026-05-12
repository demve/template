<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>04 – Vue SFC Pattern</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>04 – Vue SFC Pattern</h1>

<p>
  This example mirrors how Vue Single-File Components work: each view component is a
  self-contained <code>.php</code> (or .html) file with three named sections —
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
<pre><code>&lt;!-- app.php --&gt;
&lt;!--extends::Layout--&gt;
&lt;!--load::Views.Login--&gt;
&lt;!--load::Views.Dashboard--&gt;</code></pre>
<p>
  <code>load()</code> never outputs anything. It just executes the component file so its
  sections are stored. After both views are loaded,
  all their sections are sitting in the collector waiting to be rendered.
</p>

<h2>What the collector holds after loading</h2>
<table>
  <tr><th>Section key</th><th>Contents</th></tr>
  <tr><td><code>style</code></td><td>Login CSS + Dashboard CSS (in load order)</td></tr>
  <tr><td><code>template</code></td><td><code>&lt;script type="text/template" id="template-Views.Login"&gt;</code> + dashboard equivalent</td></tr>
  <tr><td><code>script</code></td><td>App router script + Login handler + Dashboard JS</td></tr>
</table>

<h2>The layout places everything</h2>
<pre><code>&lt;!-- layout.php --&gt;
&lt;!--section::output--&gt;
&lt;head&gt;
  &lt;style&gt;/* base reset */&lt;/style&gt;
  &lt;!--=block::style--&gt;       &lt;!-- all view CSS, in one block --&gt;
&lt;/head&gt;
&lt;body&gt;
  &lt;nav&gt;...&lt;/nav&gt;
  &lt;div id="app"&gt;&lt;/div&gt;            &lt;!-- router swaps content here --&gt;

  &lt;!--=block::template--&gt;    &lt;!-- all hidden view templates --&gt;
  &lt;!--=block::script--&gt;      &lt;!-- all JS, after DOM is ready --&gt;
&lt;/body&gt;</code></pre>

<h2>The JS router (in app.php)</h2>
<pre><code>function navigate() {
  var name = location.hash.replace('#', '') || 'Views.Login';
  var tpl  = document.getElementById('template-' + name);
  document.getElementById('app').innerHTML = tpl ? tpl.innerHTML : '';
}
window.addEventListener('hashchange', navigate);
navigate();</code></pre>
<p>
  Each view's <code>template</code> section uses the <code>&lt;dmv-template&gt;</code> tag
  which compiles to <code>&lt;script type="text/template" id="template-..."&gt;</code>.
  The browser does not render these scripts.
  The router reads <code>.innerHTML</code> and injects it into <code>#app</code>.
</p>

<div class="note">
  <strong>Naming convention:</strong> <code>Views.Login</code>
  resolves to <code>components/views/login.php</code> (or .html).
  PascalCase segments are split on capital letters; dots also insert a
  path separator.
</div>

<h2>Result — use the nav links to switch views</h2>

<iframe src="run.php" title="Vue SFC pattern output" style="height:480px"></iframe>

</body>
</html>
