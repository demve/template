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
  <code>&lt;!--=block::style--&gt;</code> has already fired.
</p>

<h2>The timing problem</h2>
<p>
  In a typical layout, <code>$this->block('style')</code> runs early
  (inside <code>&lt;head&gt;</code>) before the dynamic blocks in
  <code>&lt;body&gt;</code> have been loaded. Without deferred injection the
  blocks' styles would be missing.
</p>

<h2>How deferred inject solves it</h2>
<ol>
  <li>
    The top-level <code>render()</code> call wraps execution in an output buffer
    and sets an internal <code>$isRendering</code> flag.
  </li>
  <li>
    While <code>$isRendering</code> is true, <code>$this->block()</code>
    emits an HTML-comment placeholder instead of the real CSS:
    <code>&lt;!-- __DMV:block:style:__ --&gt;</code>.
  </li>
  <li>
    The loop runs: <code>&lt;?php foreach ($bloques as $b) echo $this->render($b->componente, $b->data); ?&gt;</code>.
    Each block is auto-loaded, its <code>style</code> section registered.
  </li>
  <li>
    After the buffer is captured, <code>inject()</code> iterates
    <code>$sectionQueue</code> — now with all sections accumulated — and
    <code>str_replace</code>s each placeholder with the real content.
  </li>
</ol>

<h2>Variable component name</h2>
<p>
  Since we are in a PHP file, we just pass the variable to the <code>render()</code> method:
</p>
<pre><code>&lt;?php foreach ($bloques as $bloque): ?&gt;
  &lt;?= $this->render($bloque->componente, $bloque->data) ?&gt;
&lt;?php endforeach; ?&gt;</code></pre>

<h2>page.html (output section)</h2>
<pre><code>&lt;!--section::output--&gt;
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
  &lt;!--=block::style--&gt;  &lt;!-- placeholder at render time --&gt;
&lt;/head&gt;
&lt;body&gt;
  &lt;?php foreach ($bloques as $bloque): ?&gt;
    &lt;?= $this->render($bloque->componente, $bloque->data) ?&gt;
  &lt;?php endforeach; ?&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h2>run.php</h2>
<pre><code>$bloques = [ /* array of CMS blocks, shuffled */ ];
shuffle($bloques);
echo $t->render('Page', ['bloques' => $bloques]);</code></pre>

<p>Each reload uses a random subset in a random order — styles are always correct.</p>

<h2>Result</h2>
<iframe src="run.php" title="Content Blocks output" style="height:520px;width:100%"></iframe>

</body>
</html>
