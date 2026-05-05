<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>08 – Content Blocks</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>08 – Content Blocks</h1>

<p>
  CMS-style rendering: the component name is a runtime value, not a literal.
  Each block contributes its own <code>section::style</code> — even though those
  components are loaded <em>after</em> the layout's
  <code>&lt;dmv-renderSectionBlock name="style" /&gt;</code> has already fired.
</p>

<h2>The timing problem</h2>
<p>
  In a typical layout, <code>renderSectionBlock('style')</code> runs early
  (inside <code>&lt;head&gt;</code>) before the dynamic blocks in
  <code>&lt;body&gt;</code> have been loaded. Without deferred injection the
  blocks' styles would be missing.
</p>

<h2>How deferred inject solves it</h2>
<ol>
  <li>
    The top-level <code>render()</code> call wraps execution in an output buffer
    and increments an internal <code>$renderDepth</code> counter.
  </li>
  <li>
    While <code>$renderDepth &gt; 0</code>, <code>renderSectionBlock()</code>
    emits an HTML-comment placeholder instead of the real CSS:
    <code>&lt;!-- __DMV:block:style:__ --&gt;</code>. The same placeholder key
    is reused for duplicate calls, so the <code>$sectionQueue</code> stays
    deduplicated.
  </li>
  <li>
    The <code>&lt;dmv-foreach&gt;</code> / <code>&lt;dmv-render component="$b"&gt;</code>
    loop runs. Each block is auto-loaded, its <code>style</code> section
    registered in <code>$sections['style']</code>.
  </li>
  <li>
    After the buffer is captured, <code>inject()</code> iterates
    <code>$sectionQueue</code> — now with all sections accumulated — and
    <code>str_replace</code>s each placeholder with the real content.
  </li>
</ol>

<h2>Variable component name</h2>
<p>
  <code>&lt;dmv-render component="$b" /&gt;</code> — when the value starts with
  <code>$</code>, OutputParser emits a PHP expression instead of a string literal:
</p>
<pre><code>&lt;dmv-render component="$bloque-&gt;componente" data="$bloque-&gt;data" /&gt;
       ↓
&lt;?php $builder-&gt;render($bloque-&gt;componente, $bloque-&gt;data); ?&gt;</code></pre>

<h2>page.html (output section)</h2>
<pre><code>&lt;!--section::output--&gt;
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
  &lt;dmv-renderSectionBlock name="style" /&gt;  &lt;!-- placeholder at render time --&gt;
&lt;/head&gt;
&lt;body&gt;
  &lt;dmv-foreach on="$bloques as $bloque"&gt;
    &lt;dmv-render component="$bloque-&gt;componente" data="$bloque-&gt;data" /&gt;
  &lt;/dmv-foreach&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h2>run.php</h2>
<pre><code>$bloques = [ /* array of CMS blocks, shuffled */ ];
shuffle($bloques);
$t-&gt;render('Page', ['bloques' =&gt; $bloques]);</code></pre>

<p>Each reload uses a random subset in a random order — styles are always correct.</p>

<h2>Result</h2>
<iframe src="run.php" title="Content Blocks output" style="height:520px;width:100%"></iframe>

</body>
</html>
