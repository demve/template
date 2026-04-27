<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>02 – Layout Inheritance</title>
  <style>
    body  { font-family: sans-serif; max-width: 860px; margin: 2rem auto; padding: 0 1rem; color: #222; }
    h1    { border-bottom: 2px solid #4f46e5; padding-bottom: .4rem; }
    h2    { margin-top: 2rem; color: #4f46e5; }
    pre   { background: #f5f5f5; border-left: 4px solid #4f46e5; padding: 1rem; overflow-x: auto; border-radius: 4px; }
    code  { font-family: monospace; font-size: .9rem; }
    iframe { width: 100%; height: 320px; border: 1px solid #ddd; border-radius: 6px; margin-top: 1rem; }
    .note { background: #fffbe6; border-left: 4px solid #f59e0b; padding: .75rem 1rem; border-radius: 4px; }
    .flow { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin: 1rem 0; }
    .box  { background: #ede9fe; border: 1px solid #4f46e5; border-radius: 6px; padding: .5rem 1rem; font-family: monospace; }
    .arr  { font-size: 1.4rem; color: #6b7280; }
  </style>
</head>
<body>

<h1>02 – Layout Inheritance</h1>

<p>
  A child page declares a parent layout via <code>&lt;!--extends::Layout--&gt;</code>.
  The layout owns the <code>output</code> section (the final HTML) and pulls content
  from the child using <code>renderSection()</code> and <code>renderSectionBlock()</code>.
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
  needed — the engine calls <code>sectionStop()</code> between consecutive
  <code>section()</code> calls and once more at the end of <code>load()</code>.
</p>

<h2>Layout component</h2>
<pre><code>&lt;!--section::output--&gt;
&lt;!DOCTYPE html&gt;
&lt;html&gt;
  &lt;head&gt;
    &lt;title&gt;@renderSection('title', 'My App')&lt;/title&gt;
    @renderSectionBlock('style')
  &lt;/head&gt;
  &lt;body&gt;
    @renderSection('content')
    @renderSectionBlock('script')
  &lt;/body&gt;
&lt;/html&gt;</code></pre>

<table style="border-collapse:collapse;width:100%;margin-top:.5rem">
  <tr style="background:#f5f5f5">
    <th style="text-align:left;padding:.5rem;border:1px solid #ddd">Method</th>
    <th style="text-align:left;padding:.5rem;border:1px solid #ddd">Use for</th>
    <th style="text-align:left;padding:.5rem;border:1px solid #ddd">Supports default?</th>
    <th style="text-align:left;padding:.5rem;border:1px solid #ddd">Supports modifier?</th>
  </tr>
  <tr>
    <td style="padding:.5rem;border:1px solid #ddd"><code>renderSection($slot, $default)</code></td>
    <td style="padding:.5rem;border:1px solid #ddd">title, content, single values</td>
    <td style="padding:.5rem;border:1px solid #ddd">✓</td>
    <td style="padding:.5rem;border:1px solid #ddd">✗</td>
  </tr>
  <tr>
    <td style="padding:.5rem;border:1px solid #ddd"><code>renderSectionBlock($slot, $modifier)</code></td>
    <td style="padding:.5rem;border:1px solid #ddd">style, script, css/js arrays</td>
    <td style="padding:.5rem;border:1px solid #ddd">✗</td>
    <td style="padding:.5rem;border:1px solid #ddd">✓</td>
  </tr>
</table>

<h2>Nested widgets</h2>
<pre><code>&lt;!-- inside PageHome's content section --&gt;
&lt;!--load::Widgets.Card--&gt;
@render('Widgets.Card')</code></pre>
<p>
  Load the widget first, then render it inline. The widget's own
  <code>style</code> section is automatically collected into the layout's
  <code>renderSectionBlock('style')</code> output.
</p>

<div class="note">
  <strong>Multi-level inheritance</strong> works out of the box — just have the middle
  layout also declare <code>&lt;!--extends::BaseLayout--&gt;</code>.
  <code>render()</code> walks up the chain to find the root.
</div>

<h2>Result</h2>

<iframe src="run.php" title="Layout inheritance output"></iframe>

</body>
</html>
