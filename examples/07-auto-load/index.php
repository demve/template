<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>07 – Auto-load</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>07 – Auto-load</h1>

<p>
  When a component's <code>output</code> section contains
  <code>$this->render('Foo')</code>, the engine automatically
  loads <code>Foo</code> during the parent's load phase — no manual
  <code>&lt;!--load::Foo--&gt;</code> required.
</p>

<p>
  Additionally, calling <code>render()</code> on a component that was never
  loaded triggers <code>load()</code> automatically before rendering.
</p>

<h2>How it works</h2>
<ol>
  <li><code>load('Page')</code> compiles Page's output section.</li>
  <li>Engine scans the compiled cache for <code>$this->render('Card')</code>.</li>
  <li><code>load('Card')</code> is called — which in turn finds <code>render('Badge')</code> and loads Badge too.</li>
  <li><code>render('Page')</code> executes — Card and Badge are already in scope.</li>
</ol>

<h2>Component file (card.php)</h2>
<pre><code>&lt;!--section::style--&gt;
&lt;style&gt; .card { ... } &lt;/style&gt;
&lt;!--section::output--&gt;
&lt;div class="card"&gt;
  &lt;h3&gt;&lt;?= $this->e($title) ?&gt; &lt;?= $this->render('Badge') ?&gt;&lt;/h3&gt;
  &lt;p&gt;&lt;?= $this->e($body) ?&gt;&lt;/p&gt;
&lt;/div&gt;</code></pre>

<p>No <code>&lt;!--load::Badge--&gt;</code> needed — Badge is auto-detected.</p>

<h2>run.php</h2>
<pre><code>$t->load('Page');  // discovers Card → discovers Badge
$t->render('Page');</code></pre>

<h2>Result</h2>
<iframe src="run.php" title="Auto-load output" style="height:200px"></iframe>

</body>
</html>
