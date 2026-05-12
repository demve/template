<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>01 – Basic Usage</title>
  <link rel="stylesheet" href="../shared.css">
</head>
<body>

<h1>01 – Basic Usage</h1>

<p>
  The minimum setup: instantiate <code>Template</code> with a components path and a
  cache path, push some variables, then call <code>render()</code>.
</p>

<h2>1. Instantiate</h2>
<pre><code>$t = new Template(
    path:  __DIR__ . '/components',
    cache: __DIR__ . '/cache'
);</code></pre>
<p>
  The cache directory is created automatically. It stores several PHP files per component:
  a <code>.load.cache.php</code> (metadata and dependencies) and several <code>.{section}.cache.php</code>
  files (the content of each section).
</p>

<h2>2. Pass variables</h2>
<pre><code>$t->set('name', 'World');
$t->set('year', date('Y'));</code></pre>
<p>
  Variables set here are available inside any component via
  <code>$this->get('key')</code>, <code>$this->print('key')</code> or the comment directives.
</p>

<h2>3. Comment directives</h2>
<pre><code>&lt;!-- inside greeting.html --&gt;
&lt;!--section::output--&gt;
&lt;h1&gt;Hello, &lt;!--print::name--&gt;!&lt;/h1&gt;</code></pre>
<p>
  Directives use HTML comment syntax so IDEs highlight and format the file as normal HTML.
  <code>&lt;!--fn::arg--&gt;</code> compiles to <code>&lt;?php $this->fn("arg"); ?&gt;</code>.
  Use <code>&lt;!--=fn::arg--&gt;</code> to echo the result.
</p>

<h2>4. Render</h2>
<pre><code>$html = $t->render('Greeting'); // returns string</code></pre>

<div class="note">
  <strong>Component naming:</strong> <code>Greeting</code> maps to
  <code>components/greeting.php</code> (or .html). <code>PageHeader</code> maps to
  <code>components/page/header.php</code>. PascalCase is split on capital letters into directory separators.
</div>

<h2>Result</h2>

<iframe src="run.php" title="Basic example output" style="height:260px"></iframe>

</body>
</html>
