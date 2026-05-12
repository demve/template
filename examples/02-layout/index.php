<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>02 – Layout Inheritance</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>02 – Layout Inheritance</h1>

<p>
  A child page declares a parent layout via <code>&lt;!--extends::Layout--&gt;</code>.
  The layout owns the <code>output</code> section (the final HTML) and pulls content
  from the child using <code>$this->slot()</code> and <code>$this->block()</code>.
</p>

<h2>Load order</h2>
<div class="flow">
  <div class="box">render('PageHome')</div>
  <div class="arr">→</div>
  <div class="box">load('PageHome')<br><small>registers title, style, content sections</small></div>
  <div class="arr">→</div>
  <div class="box">auto load('Layout')<br><small>compiles its output section</small></div>
  <div class="arr">→</div>
  <div class="box">include Layout.output.cache.php</div>
</div>
<p>
  <code>render()</code> always includes the <em>root ancestor's</em> output cache.
  The child's own output cache is never rendered directly when it extends a layout.
</p>

<h2>Child component</h2>
<pre><code>&lt;!--extends::Layout--&gt;

&lt;!--section::title--&gt;
Home Page

&lt;!--section::style--&gt;
&lt;style&gt; .hero { ... } &lt;/style&gt;

&lt;!--section::content--&gt;
&lt;div class="hero"&gt;...&lt;/div&gt;</code></pre>
<p>
  Sections auto-close when a new one opens. No explicit <code>&lt;!--endsection--&gt;</code>
  needed.
</p>

<h2>Layout component</h2>
<pre><code>&lt;!--section::output--&gt;
&lt;!DOCTYPE html&gt;
&lt;html&gt;
  &lt;head&gt;
    &lt;title&gt;&lt;!--=slot::title|My App--&gt;&lt;/title&gt;
    &lt;!--=block::style--&gt;
  &lt;/head&gt;
  &lt;body&gt;
    &lt;!--=slot::content--&gt;
    &lt;!--=block::script--&gt;
  &lt;/body&gt;
&lt;/html&gt;</code></pre>

<table>
  <tr>
    <th>Method</th>
    <th>Use for</th>
    <th>Supports default?</th>
    <th>Supports modifier?</th>
  </tr>
  <tr>
    <td><code>$this->slot($name, $default)</code></td>
    <td>title, content, single values</td>
    <td>✓</td>
    <td>✗</td>
  </tr>
  <tr>
    <td><code>$this->block($name, $modifier)</code></td>
    <td>style, script, css/js blocks</td>
    <td>✗</td>
    <td>✓</td>
  </tr>
</table>

<h2>Nested widgets</h2>
<pre><code>&lt;!-- inside PageHome's content section --&gt;
&lt;!--=render::Widgets.Card--&gt;</code></pre>
<p>
  The widget's own <code>style</code> section is automatically collected into the layout's
  <code>$this->block('style')</code> output.
</p>

<div class="note">
  <strong>Multi-level inheritance</strong> works out of the box — just have the middle
  layout also declare <code>&lt;!--extends::BaseLayout--&gt;</code>.
  <code>render()</code> walks up the chain to find the root.
</div>

<h2>Result</h2>

<iframe src="run.php" title="Layout inheritance output" style="height:320px"></iframe>

</body>
</html>
